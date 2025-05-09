<?php

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Contracts\V2\IWallet;
use Providers\Aix\AixService;
use Providers\Aix\AixRepository;
use Providers\Aix\AixCredentials;
use Providers\Aix\Credentials\Staging;
use Providers\Aix\Contracts\ICredentials;
use Providers\Aix\Exceptions\WalletErrorException;
use Providers\Aix\Exceptions\PlayerNotFoundException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;

class AixServiceTest extends TestCase
{
    private function makeService($repository = null, $credentials = null, $wallet = null): AixService
    {
        $repository ??= $this->createStub(AixRepository::class);
        $credentials ??= $this->createStub(AixCredentials::class);
        $wallet ??= $this->createStub(IWallet::class);

        return new AixService(repository: $repository, credentials: $credentials, wallet: $wallet);
    }

    public function test_getBalance_mockRepository_getPlayerByUserIDProvider()
    {
        $request = new Request([
            'user_id' => 27,
            'prd_id' => 1
        ]);

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('getPlayerByUserIDProvider')
            ->with(userID: $request->user_id)
            ->willReturn((object)[
                'play_id' => 'testPlayID',
                'currency' => 'IDR'
            ]);

        $stubWallet = $this->createStub(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);
        
        $service = $this->makeService(repository: $mockRepository, wallet: $stubWallet);
        $service->getBalance(request: $request);    
    }

    public function test_getBalance_playerNotFound_PlayerNotFoundException()
    {
        $this->expectException(PlayerNotFoundException::class);

        $request = new Request([
            'user_id' => 27,
            'prd_id' => 1
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByUserIDProvider')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_mockCredentials_getCredentialsByCurrency()
    {
        $request = new Request([
            'user_id' => 27,
            'prd_id' => 1
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByUserIDProvider')
            ->willReturn((object)[
                'play_id' => 'testPlayID',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $mockCredentials = $this->createMock(AixCredentials::class);
        $mockCredentials->expects($this->once())
            ->method('getCredentialsByCurrency')
            ->with(currency: 'IDR')
            ->willReturn($credentials);

        $stubWallet = $this->createStub(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $service = $this->makeService(repository: $stubRepository,credentials: $mockCredentials, wallet: $stubWallet);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_invalidSecretKey_invalidSecretKeyException()
    {
        $this->expectException(InvalidSecretKeyException::class);

        $request = new Request([
            'user_id' => 27,
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'invalidSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByUserIDProvider')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
                'currency' => 'IDR'
            ]);

        $stubProviderCredentials = $this->createMock(Staging::class);
        $stubProviderCredentials->method('getSecretKey')
            ->willReturn('testSecretKey');
            
        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($stubProviderCredentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 100.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            wallet: $stubWallet,
            credentials: $stubCredentials
        );
        $service->getBalance(request: $request);
    }

    public function test_getBalance_mockWallet_balance()
    {
        $request = new Request([
            'user_id' => 27,
            'prd_id' => 1
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByUserIDProvider')
            ->willReturn((object)[
                'play_id' => 'testPlayID',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);  
        $stubCredentials = $this->createMock(AixCredentials::class);  
        $stubCredentials->method('getCredentialsByCurrency')  
            ->willReturn($credentials);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('balance')
            ->with(
                credentials: $credentials,
                playID: 'testPlayID'
            )
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $service = $this->makeService(repository: $stubRepository, wallet: $mockWallet, credentials: $stubCredentials);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_walletStatusCodeNot2100_WalletException()
    {
        $this->expectException(WalletErrorException::class);

        $request = new Request([
            'user_id' => 27,
            'prd_id' => 1
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByUserIDProvider')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
                'currency' => 'IDR'
            ]);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn(['status_code' => 'invalid']);

        $service = $this->makeService(repository: $stubRepository, wallet: $stubWallet);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_stubWallet_expectedData()
    {
        $request = new Request([
            'user_id' => 27,
            'prd_id' => 1
        ]);

        $expected = 1000.0;

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByUserIDProvider')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);  
        $stubCredentials = $this->createMock(AixCredentials::class);  
        $stubCredentials->method('getCredentialsByCurrency')  
            ->willReturn($credentials);

        $stubWallet = $this->createStub(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $service = $this->makeService(repository: $stubRepository, wallet: $stubWallet, credentials: $stubCredentials);
        $result = $service->getBalance(request: $request);

        $this->assertEquals(expected: $expected, actual: $result);
    }
}