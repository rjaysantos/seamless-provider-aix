<?php

use Tests\TestCase;
use Providers\Aix\AixApi;
use Illuminate\Http\Request;
use App\Contracts\V2\IWallet;
use Providers\Aix\AixService;
use Providers\Aix\AixRepository;
use Providers\Aix\AixCredentials;
use Providers\Aix\Contracts\ICredentials;
use App\Exceptions\Casino\WalletErrorException;
use Providers\Aix\Exceptions\PlayerNotFoundException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\WalletErrorException as WalletException;

class AixServiceTest extends TestCase
{
    public function makeService($repository = null, $credentials = null, $wallet = null, $api = null)
    {
        $repository ??= $this->createMock(AixRepository::class);
        $credentials ??= $this->createMock(AixCredentials::class);
        $wallet ??= $this->createMock(IWallet::class);
        $api ??= $this->createMock(AixApi::class);

        return new AixService($repository, $credentials, $wallet, $api);
    }

    public function test_getLaunchUrl_mockRepository_createIgnorePlayer()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('createIgnorePlayer')
            ->with('test-play-id', 'IDR');

        $stubWallet = $this->createMock(originalClassName: IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $service = $this->makeService(repository: $mockRepository, wallet: $stubWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockCredentials_getCredentialsByCurrency()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $mockCredentials = $this->createMock(AixCredentials::class);
        $mockCredentials->expects($this->once())
            ->method('getCredentialsByCurrency')
            ->with('IDR');

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $service = $this->makeService(credentials: $mockCredentials, wallet: $stubWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockWallet_getBalance()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $credentials = $this->createMock(ICredentials::class);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('balance')
            ->with($credentials, 'test-play-id')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $service = $this->makeService(credentials: $stubCredentials, wallet: $mockWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockWalletStatusCodeNot2100_WalletErrorException()
    {
        $this->expectException(WalletErrorException::class);

        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $credentials = $this->createMock(ICredentials::class);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->method('balance')
            ->willReturn([
                'status_code' => 1234
            ]);

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $service = $this->makeService(credentials: $stubCredentials, wallet: $mockWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockApi_auth()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $credentials = $this->createMock(ICredentials::class);

        $mockApi = $this->createMock(AixApi::class);
        $mockApi->expects($this->once())
            ->method('auth')
            ->with($credentials, $request, 100);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $service = $this->makeService(api: $mockApi, credentials: $stubCredentials, wallet: $stubWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_stubApi_expected()
    {
        $expected = 'test-url';

        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $stubApi = $this->createMock(AixApi::class);
        $stubApi->method('auth')
            ->willReturn('test-url');

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $service = $this->makeService(api: $stubApi, wallet: $stubWallet);
        $result = $service->getLaunchUrl($request);

        $this->assertSame($expected, $result);
    }

    public function test_getBalance_mockRepository_getPlayerByPlayID()
    {
        $request = new Request([
            'user_id' => 12345,
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('getPlayerByPlayID')
            ->with(userID: $request->user_id)
            ->willReturn((object)[
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class); 
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');
            
        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createStub(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);
        
        $service = $this->makeService(repository: $mockRepository, wallet: $stubWallet, credentials: $stubCredentials);
        $service->getBalance(request: $request);    
    }

    public function test_getBalance_playerNotFound_PlayerNotFoundException()
    {
        $this->expectException(PlayerNotFoundException::class);

        $request = new Request([
            'user_id' => 12345,
            'prd_id' => 1
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_mockCredentials_getCredentialsByCurrency()
    {
        $request = new Request([
            'user_id' => 12345,
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object)[
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class); 
        $credentials->method('getSecretKey')
                ->willReturn('testSecretKey');
                
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
            'user_id' => 12345,
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'invalidSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');
            
        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

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
            'user_id' => 12345,
            'prd_id' => 1
        ]);
        
        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object)[
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');
            
        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('balance')
            ->with(
                credentials: $credentials,
                playID: '12345'
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
        $this->expectException(WalletException::class);

        $request = new Request([
            'user_id' => 12345,
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');
            
        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn(['status_code' => 'invalid']);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials, wallet: $stubWallet);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_stubWallet_expectedData()
    {
        $request = new Request([
            'user_id' => 12345,
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $expected = 1000.0;

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');
            
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
