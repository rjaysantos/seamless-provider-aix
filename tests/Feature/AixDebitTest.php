<?php

use Tests\TestCase;
use App\Contracts\V2\IWallet;
use Illuminate\Support\Facades\DB;
use App\Libraries\Wallet\V2\TestWallet;
use App\Contracts\V2\IWalletCredentials;
use PHPUnit\Framework\Attributes\DataProvider;

class AixDebitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('TRUNCATE TABLE aix.players RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.playgame RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.reports RESTART IDENTITY;');
        app()->bind(IWallet::class, TestWallet::class);
    }

    public function test_debit_validRequest_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayeru001',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'credit' => 1000.00,
                    'status_code' => 2100
                ];
            }

            public function Wager(IWalletCredentials $credentials, string $playID, string $currency, string $transactionID, float $amount, Wallet\V1\ProvSys\Transfer\Report $report): array
            {
                return [
                    'credit_after' => 900.00,
                    'status_code' => 2100
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        $request = [
            'user_id' => 'testPlayeru001',
            'amount' => 100.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        $response = $this->post('/aix/prov/debit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'balance' => 900.0,
            'status' => 1
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('aix.reports', [
            'ext_id' => 'wager-testTxnID',
            'username' => 'testUsername',
            'play_id' => 'testPlayeru001',
            'web_id' => 1,
            'currency' => 'IDR',
            'game_code' => '1',
            'bet_amount' => 100.00,
            'bet_winlose' => 0,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00'
        ]);
    }

    #[DataProvider('requestParams')]
    public function test_debit_missingRequestParams_expectedData($param)
    {
        $request = [
            'user_id' => 'testPlayeru001',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        unset($request[$param]);

        $response = $this->post('/aix/prov/debit', $request, [
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
            ['amount'],
            ['prd_id'],
            ['txn_id'],
            ['round_id'],
            ['debit_time']
        ];
    }

    public function test_debit_invalidSecretKey_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayeru001',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayeru001',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        $response = $this->post('/aix/prov/debit', $request, [
            'secret-key' => 'invalid-secret-key'
        ]);

        $response->assertJson([
            'error' => 'ACCESS_DENIED',
            'status' => 0
        ]);

        $response->assertStatus(200);
    }

    public function test_debit_playerNotFound_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayeru001',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayeru0011',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        $response = $this->post('/aix/prov/debit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'error' => 'INVALID_USER',
            'status' => 0
        ]);

        $response->assertStatus(200);
    }


    public function test_debit_transactionAlreadyExist_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayeru001',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        DB::table('aix.reports')->insert([
            'ext_id' => 'wager-testTxnID',
            'username' => 'username',
            'play_id' => 'testPlayeru001',
            'web_id' => 1,
            'currency' => 'IDR',
            'game_code' => 1,
            'bet_amount' => 100.0,
            'bet_winlose' => 0,
            'updated_at' => '2025-01-01 00:00:00',
            'created_at' => '2025-01-01 00:00:00'
        ]);

        $request = [
            'user_id' => 'testPlayeru001',
            'amount' => 100.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        $response = $this->post('/aix/prov/debit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'error' => 'DUPLICATE_DEBIT',
            'status' => 0
        ]);

        $response->assertStatus(200);
    }

    public function test_debit_insufficientFunds_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayeru001',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayeru001',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'credit' => 100.00,
                    'status_code' => 2100
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        $response = $this->post('/aix/prov/debit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'INSUFFICIENT_FUNDS'
        ]);

        $response->assertStatus(200);
    }

    public function test_debit_invalidWalletBalanceResponse_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayeru001',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'status_code' => 'invalid'
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        $request = [
            'user_id' => 'testPlayeru001',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        $response = $this->post('/aix/prov/debit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'error' => 'UNKNOWN_ERROR',
            'status' => 0
        ]);

        $response->assertStatus(200);
    }

    public function test_debit_invalidWalletWagerResponse_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayeru001',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $wallet = new class extends TestWallet {
            public function balance(IWalletCredentials $credentials, string $playID): array
            {
                return [
                    'credit' => 1000.00,
                    'status_code' => 2100
                ];
            }

            public function Wager(IWalletCredentials $credentials, string $playID, string $currency, string $transactionID, float $amount, Wallet\V1\ProvSys\Transfer\Report $report): array
            {
                return [
                    'status_code' => 'invalid'
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        $request = [
            'user_id' => 'testPlayeru001',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ];

        $response = $this->post('/aix/prov/debit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'error' => 'UNKNOWN_ERROR',
            'status' => 0
        ]);

        $response->assertStatus(200);
    }
}
