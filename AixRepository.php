<?php
namespace Providers\Aix;

use Illuminate\Support\Facades\DB;

class AixRepository
{
    public function createIgnorePlayer(string $playID, string $currency): void
    {
        DB::connection('pgsql_write')
            ->table('aix.players')
            ->insertOrIgnore([
                'play_id' => $playID,
                'username' => $playID,
                'currency' => $currency
            ]);
    }

    public function getPlayerByPlayID(string $playID): ?object
    {
        return DB::table('aix.players')
            ->where('play_id', $playID)
            ->first();
    }

    public function getTransactionByTrxID(string $transactionID): ?object
    {
        return DB::table('aix.reports')
            ->where('trx_id', $transactionID)
            ->first();
    }

    public function createTransaction(string $transactionID, float $betAmount, string $transactionDate): void
    {
        DB::connection('pgsql_write')
            ->table('aix.reports')
            ->insert([
                'trx_id' => $transactionID,
                'bet_amount' => $betAmount,
                'win_amount' => 0,
                'updated_at' => null,
                'created_at' => $transactionDate
            ]);
    }

    public function settleTransaction(string $trxID, float $winAmount, string $settleTime): void
    {
        DB::connection('pgsql_write')
            ->table('aix.reports')
            ->where('trx_id', $trxID)
            ->update([
                'win_amount' => $winAmount,
                'updated_at' => $settleTime
            ]);
    }
}