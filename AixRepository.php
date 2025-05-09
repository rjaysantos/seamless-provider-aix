<?php
namespace Providers\Aix;

use Illuminate\Support\Facades\DB;

class AixRepository
{
    public function getPlayerByPlayID(string $playID): ?object
    {
        return DB::table('aix.players')
            ->where('play_id', $playID)
            ->first();
    }

    public function getTransactionByTrxID(string $trxID): ?object
    {
        return DB::table('aix.reports')
            ->where('trx_id', $trxID)
            ->first();
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