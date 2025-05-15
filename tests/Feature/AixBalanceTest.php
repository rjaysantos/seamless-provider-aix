<?php

use Tests\TestCase;
use App\Contracts\V2\IWallet;
use Illuminate\Support\Facades\DB;
use App\Libraries\Wallet\V2\TestWallet;
use App\Contracts\V2\IWalletCredentials;
use PHPUnit\Framework\Attributes\DataProvider;

class AixBalanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('TRUNCATE TABLE aix.players RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.playgame RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.reports RESTART IDENTITY;');
        app()->bind(IWallet::class, TestWallet::class);
    }
    
    public function test_balance_validRequest_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayer',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayer',
            'prd_id' => 1
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

        $response = $this->post('/aix/prov/balance', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 1,
            'balance' => 1000.00
        ]);

        $response->assertStatus(200);
    }
    
    #[DataProvider('requestParams')]
    public function test_balance_incompleteRequestParameters_expectedData($params)
    {
        $request = [
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ];

        unset($request[$params]);

        $response = $this->post('/aix/prov/balance', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0
        ]);

        $response->assertStatus(200);
    }

    public static function requestParams()
    {
        return [
            ['user_id'],
            ['prd_id']
        ];
    }

    #[DataProvider('invalidRequestParams')]
    public function test_balance_invalidRequestParameters_expectedData($params, $value)
    {
        $request = [
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ];

        $request[$params] = $value;

        $response = $this->post('/aix/prov/balance', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0
        ]);

        $response->assertStatus(200);
    }

    public static function invalidRequestParams()
    {
        return [
            ['user_id', 'test'],
            ['user_id', 12345.0],
            ['prd_id', 1.0],
            ['prd_id', 'test']
        ];
    }

    public function test_balance_playerNotFoundException_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayer',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayer1',
            'prd_id' => 1
        ];

        $response = $this->post('/aix/prov/balance', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'INVALID_USER'
        ]);

        $response->assertStatus(200);
    }

    public function test_balance_invalidSecretKeyException_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayer',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ];

        $response = $this->post('/aix/prov/balance', $request, [
            'secret-key' => 'invalid_secret_key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'ACCESS_DENIED'
        ]);

        $response->assertStatus(200);
    }

    public function test_balance_walletErrorException_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayer',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ];

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'status_code' => 'invalid'
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        $response = $this->post('/aix/prov/balance', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'UNKNOWN_ERROR'
        ]);

        $response->assertStatus(200);
    }
}
