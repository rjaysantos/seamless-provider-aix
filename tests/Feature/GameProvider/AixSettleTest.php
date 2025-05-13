<?php

use Tests\TestCase;
use App\Contracts\V2\IWallet;
use Illuminate\Support\Facades\DB;
use App\Libraries\Wallet\V2\TestWallet;
use App\Contracts\V2\IWalletCredentials;
use PHPUnit\Framework\Attributes\DataProvider;

class AixSettleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('TRUNCATE TABLE aix.players RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.playgame RESTART IDENTITY;');
        DB::statement('TRUNCATE TABLE aix.reports RESTART IDENTITY;');
        app()->bind(IWallet::class, TestWallet::class);
    }

    public function test_credit_validRequest_expectedData()
    {
        $wallet = new class extends TestWallet {
            public function Payout(IWalletCredentials $credentials, string $playID, string $currency, string $transactionID, float $amount, Wallet\V1\ProvSys\Transfer\Report $report): array
            {
                return [
                    'credit_after' => 1200.00,
                    'status_code' => 2100
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        DB::table('aix.players')->insert([
            'play_id' => 'testPlayID',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        DB::table('aix.reports')->insert([
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 0.00,
            'updated_at' => null,
            'created_at' => '2024-01-01 00:00:00'
        ]);

        $request = [
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ];

        $response = $this->post('aix/prov/credit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 1,
            'balance' => 1200.00
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('aix.reports', [
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 0.00,
            'updated_at' => null,
            'created_at' => '2024-01-01 00:00:00'
        ]);

        $this->assertDatabaseHas('aix.reports', [
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 200.00,
            'updated_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00'
        ]);
    }

    #[DataProvider('creditParams')]
    public function test_credit_incompleteRequest_expectedData($param)
    {
        $request = [
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ];

        unset($request[$param]);

        $response = $this->post('aix/prov/credit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'ACCESS_DENIED'
        ]);

        $response->assertStatus(200);
    }

    public static function creditParams(): array
    {
        return [
            ['user_id'],
            ['amount'],
            ['prd_id'],
            ['txn_id'],
            ['credit_time']
        ];
    }

    public function test_credit_playerNotFound_expectedData()
    {
        $request = [
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ];

        $response = $this->post('aix/prov/credit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'INVALID_USER'
        ]);

        $response->assertStatus(200);
    }

    public function test_credit_invalidSecretKey_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayID',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        $request = [
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ];

        $response = $this->post('aix/prov/credit', $request, [
            'secret-key' => 'invalid-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'ACCESS_DENIED'
        ]);

        $response->assertStatus(200);
    }

    public function test_credit_txnIDNotFound_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayID',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        DB::table('aix.reports')->insert([
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 0.00,
            'updated_at' => null,
            'created_at' => '2024-01-01 00:00:00'
        ]);

        $request = [
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'invalidTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ];

        $response = $this->post('aix/prov/credit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'INVALID_DEBIT'
        ]);

        $response->assertStatus(200);
    }

    public function test_credit_transactionAlreadySettled_expectedData()
    {
        DB::table('aix.players')->insert([
            'play_id' => 'testPlayID',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        DB::table('aix.reports')->insert([
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 200.00,
            'updated_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00'
        ]);

        $request = [
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ];

        $response = $this->post('aix/prov/credit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'DUPLICATE_CREDIT'
        ]);

        $response->assertStatus(200);
    }

    public function test_credit_walletError_expectedData()
    {
        $wallet = new class extends TestWallet {
            public function Payout(IWalletCredentials $credentials, string $playID, string $currency, string $transactionID, float $amount, Wallet\V1\ProvSys\Transfer\Report $report): array
            {
                return [
                    'status_code' => 'invalid'
                ];
            }
        };

        app()->bind(IWallet::class, $wallet::class);

        DB::table('aix.players')->insert([
            'play_id' => 'testPlayID',
            'username' => 'testUsername',
            'currency' => 'IDR'
        ]);

        DB::table('aix.reports')->insert([
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 200.00,
            'updated_at' => null,
            'created_at' => '2024-01-01 00:00:00'
        ]);

        $request = [
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ];

        $response = $this->post('aix/prov/credit', $request, [
            'secret-key' => 'ais-secret-key'
        ]);

        $response->assertJson([
            'status' => 0,
            'error' => 'UNKNOWN_ERROR'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('aix.reports', [
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 200.00,
            'updated_at' => null,
            'created_at' => '2024-01-01 00:00:00'
        ]);

        $this->assertDatabaseMissing('aix.reports', [
            'trx_id' => 'testTransactionID',
            'bet_amount' => 100.00,
            'win_amount' => 200.00,
            'updated_at' => '2024-01-01 00:00:00',
            'created_at' => '2024-01-01 00:00:00'
        ]);
    }
}