<?php

use Tests\TestCase;
use Providers\Aix\AixApi;
use Illuminate\Http\Request;
use App\Contracts\V2\IWallet;
use App\Exceptions\Casino\WalletErrorException;
use Providers\Aix\AixService;
use Providers\Aix\AixRepository;
use Providers\Aix\AixCredentials;
use Providers\Aix\Credentials\Staging;
use Providers\Aix\Contracts\ICredentials;

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
        $mockWallet->expects($this->once())
            ->method('balance')
            ->with($credentials, 'test-play-id')
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
}
