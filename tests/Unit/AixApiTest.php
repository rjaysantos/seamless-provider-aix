<?php

use App\Exceptions\Casino\ThirdPartyApiErrorException;
use App\Libraries\LaravelHttpClient;
use Illuminate\Http\Request;
use Providers\Aix\AixApi;
use Providers\Aix\Contracts\ICredentials;
use Tests\TestCase;

class AixApiTest extends TestCase
{
    private function makeApi($http = null)
    {
        $http ??= $this->createMock(LaravelHttpClient::class);

        return new AixApi($http);
    }

    private function makeCredentials(): ICredentials
    {
        return new class implements ICredentials {
            public function getGrpcHost(): string
            {
                return '';
            }
            public function getGrpcPort(): string
            {
                return '';
            }
            public function getGrpcToken(): string
            {
                return '';
            }
            public function getGrpcSignature(): string
            {
                return '';
            }
            public function getProviderCode(): string
            {
                return '';
            }

            public function getApiUrl(): string
            {
                return 'api-url';
            }

            public function getAgCode(): string
            {
                return 'ag-code';
            }

            public function getAgToken(): string
            {
                return 'ag-token';
            }

            public function getSecretKey(): string
            {
                return '';
            }
        };
    }

    public function test_auth_mockHttp_post()
    {
        $credentials = $this->makeCredentials();

        $request = new Request([
            'playId' => 'test-play-id',
            'host' => 'www.host.com',
            'language' => 'en',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => 2,
            'username' => 'username'
        ]);

        $balance = 100;

        $mockHttp = $this->createMock(LaravelHttpClient::class);
        $mockHttp->expects($this->once())
            ->method('post')
            ->with(
                'api-url/auth',
                [
                    'user' => [
                        'id' => 'test-play-id',
                        'name' => 'username',
                        'balance' => 100,
                        'domain_url' => 'www.host.com',
                        'language' => 'en',
                        'currency' => 'IDR',
                    ],
                    'prd' => [
                        'id' => 2,
                        'is_mobile' => false,
                    ]
                ],
                [
                    'ag-code' => 'ag-code',
                    'ag-token' => 'ag-token',
                ]
            )
            ->willReturn((object)[
                'status' => 1,
                'launch_url' => 'test-url'
            ]);

        $api = $this->makeApi($mockHttp);
        $api->auth($credentials, $request, $balance);
    }

    public function test_auth_stubHttpErrorResponseFormat_ThirdPartyApiErrorException()
    {
        $this->expectException(ThirdPartyApiErrorException::class);

        $credentials = $this->makeCredentials();

        $request = new Request([
            'playId' => 'test-play-id',
            'host' => 'www.host.com',
            'language' => 'en',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => 2,
            'username' => 'username'
        ]);

        $balance = 100;

        $stubHttp = $this->createMock(LaravelHttpClient::class);
        $stubHttp->method('post')
            ->willReturn((object)[
                'test' => 0,
                'launch_url' => 'test'
            ]);

        $api = $this->makeApi(http: $stubHttp);
        $api->auth($credentials, $request, $balance);
    }

    public function test_auth_stubHttpErrorResponse_ThirdPartyApiErrorException()
    {
        $this->expectException(ThirdPartyApiErrorException::class);

        $credentials = $this->makeCredentials();

        $request = new Request([
            'playId' => 'test-play-id',
            'host' => 'www.host.com',
            'language' => 'en',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => 2,
            'username' => 'username'
        ]);

        $balance = 100;

        $stubHttp = $this->createMock(LaravelHttpClient::class);
        $stubHttp->method('post')
            ->willReturn((object)[
                'status' => 0,
                'launch_url' => 'test'
            ]);

        $api = $this->makeApi(http: $stubHttp);
        $api->auth($credentials, $request, $balance);
    }

    public function test_auth_stubHttpValidResponse_expected()
    {
        $expected = 'test-launch-url';

        $credentials = $this->makeCredentials();

        $request = new Request([
            'playId' => 'test-play-id',
            'host' => 'www.host.com',
            'language' => 'en',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => 2,
            'username' => 'username'
        ]);

        $balance = 100;

        $stubHttp = $this->createMock(LaravelHttpClient::class);
        $stubHttp->method('post')
            ->willReturn((object)[
                'status' => 1,
                'launch_url' => $expected
            ]);

        $api = $this->makeApi(http: $stubHttp);
        $result = $api->auth($credentials, $request, $balance);

        $this->assertSame($expected, $result);
    }
}
