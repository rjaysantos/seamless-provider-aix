<?php

use Tests\TestCase;
use App\Contracts\V2\IWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Libraries\Wallet\V2\TestWallet;
use App\Contracts\V2\IWalletCredentials;
use PHPUnit\Framework\Attributes\DataProvider;

class AixPlayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('TRUNCATE TABLE aix.players RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.playgame RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.reports RESTART IDENTITY;');
    }

    public function test_play_validRequest_expectedData()
    {
        $request = [
            'playId' => 'testPlayID',
            'memberId' => 123,
            'username' => 'testUsername',
            'host' => 'testHost.com',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => '1',
            'memberIp' => '127.0.0.1',
            'language' => 'en'
        ];

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'credit' => 1000.0,
                    'status_code' => 2100
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        Http::fake([
            '/auth' => Http::response([
                'launch_url' => 'testLaunchUrl.com',
                'status' => 1
            ])
        ]);

        $response = $this->post('aix/in/play', $request, [
            'Authorization' => 'Bearer ' . env('FEATURE_TEST_TOKEN')
        ]);

        $response->assertJson([
            'success' => true,
            'code' => 200,
            'data' => 'testLaunchUrl.com',
            'error' => null
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('aix.players', [
            'play_id' => 'testPlayID',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        Http::assertSent(function ($request) {
            return $request->url() == 'https://stg-games-api.ais-le.com/api/v1/auth' &&
                $request->hasHeader('ag-code', 'ais') &&
                $request->hasHeader('ag-token', 'ag-token') &&
                $request['user']['id'] == 'testPlayID' &&
                $request['user']['name'] == 'testUsername' &&
                $request['user']['balance'] == 1000.0 &&
                $request['user']['language'] == 'en' &&
                $request['user']['domain_url'] == 'testHost.com' &&
                $request['user']['currency'] == 'IDR' &&
                $request['prd']['id'] == 1 &&
                $request['prd']['is_mobile'] == false;
        });
    }

    public function test_play_invalidBearerToken_expectedData()
    {
        $request = [
            'playId' => 'testPlayID',
            'memberId' => 123,
            'username' => 'testUsername',
            'host' => 'testHost.com',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => '1',
            'memberIp' => '127.0.0.1',
            'language' => 'en'
        ];

        $response = $this->post('aix/in/play', $request, [
            'Authorization' => 'Bearer invalid-token'
        ]);

        $response->assertJson([
            'success' => false,
            'code' => 9301,
            'error' => 'Authorization failed.',
            'data' => NULL
        ]);

        $response->assertStatus(401);
    }

    #[DataProvider('playParams')]
    public function test_play_invalidRequest_expectedData($parameter)
    {
        $request = [
            'playId' => 'testPlayID',
            'memberId' => 123,
            'username' => 'testUsername',
            'host' => 'testHost.com',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => '1',
            'memberIp' => '127.0.0.1',
            'language' => 'en'
        ];

        unset($request[$parameter]);

        $response = $this->post('aix/in/play', $request, [
            'Authorization' => 'Bearer ' . env('FEATURE_TEST_TOKEN')
        ]);

        $response->assertJson([
            'success' => false,
            'code' => 422,
            'error' => "invalid request format",
            'data' => NULL,
        ]);

        $response->assertStatus(200);
    }

    public static function playParams()
    {
        return [
            ['playId'],
            ['memberId'],
            ['username'],
            ['host'],
            ['currency'],
            ['device'],
            ['gameId'],
            ['memberIp'],
            ['language']
        ];
    }

    public function test_play_walletError_expectedResponse()
    {
        $request = [
            'playId' => 'testPlayID',
            'memberId' => 123,
            'username' => 'testUsername',
            'host' => 'testHost.com',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => '1',
            'memberIp' => '127.0.0.1',
            'language' => 'en'
        ];

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'status_code' => 1234
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        Http::fake([
            '/auth' => Http::response([
                'launch_url' => 'testLaunchUrl.com',
                'status' => 1
            ])
        ]);

        $response = $this->post('aix/in/play', $request, [
            'Authorization' => 'Bearer ' . env('FEATURE_TEST_TOKEN')
        ]);

        $response->assertJson([
            'success' => false,
            'code' => 422,
            'data' => null,
            'error' => 'Wallet Error'
        ]);

        $response->assertStatus(200);
    }

    public function test_play_thirdPartyInvalidResponseFormat_expectedResponse()
    {
        $request = [
            'playId' => 'testPlayID',
            'memberId' => 123,
            'username' => 'testUsername',
            'host' => 'testHost.com',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => '1',
            'memberIp' => '127.0.0.1',
            'language' => 'en'
        ];

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'credit' => 1000.0,
                    'status_code' => 2100
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        Http::fake([
            '/auth' => Http::response([
                'test' => 'test',
            ])
        ]);

        $response = $this->post('aix/in/play', $request, [
            'Authorization' => 'Bearer ' . env('FEATURE_TEST_TOKEN')
        ]);

        $response->assertJson([
            'success' => false,
            'code' => 422,
            'data' => null,
            'error' => 'Third Party Api error'
        ]);

        $response->assertStatus(200);
    }

    public function test_play_thirdPartyStatusFail_expectedResponse()
    {
        $request = [
            'playId' => 'testPlayID',
            'memberId' => 123,
            'username' => 'testUsername',
            'host' => 'testHost.com',
            'currency' => 'IDR',
            'device' => 1,
            'gameId' => '1',
            'memberIp' => '127.0.0.1',
            'language' => 'en'
        ];

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'credit' => 1000.0,
                    'status_code' => 2100
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        Http::fake([
            '/auth' => Http::response([
                'launch_url' => 'testLaunchUrl.com',
                'status' => 0
            ])
        ]);

        $response = $this->post('aix/in/play', $request, [
            'Authorization' => 'Bearer ' . env('FEATURE_TEST_TOKEN')
        ]);

        $response->assertJson([
            'success' => false,
            'code' => 422,
            'data' => null,
            'error' => 'Third Party Api error'
        ]);

        $response->assertStatus(200);
    }
}
