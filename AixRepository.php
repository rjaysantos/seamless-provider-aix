<?php

namespace Providers\Aix;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AixRepository
{
    public function createIgnorePlayer(string $playID, string $username, string $currency): void
    {
        DB::connection('pgsql_write')
            ->table('aix.players')
            ->insertOrIgnore([
                'play_id' => $playID,
                'username' => $username,
                'currency' => $currency
            ]);
    }

    public function getPlayerByPlayID(string $playID): ?object
    {
        return DB::table('aix.players')
            ->where('play_id', $playID)
            ->first();
    }

    public function getTransactionByExtID(string $extID): ?object
    {
        return DB::table('aix.reports')
            ->where('ext_id', $extID)
            ->first();
    }

    private function getWebID(string $playID)
    {
        if (preg_match_all('/u(\d+)/', $playID, $matches)) {
            $lastNumber = end($matches[1]);
            return $lastNumber;
        }
    }

    public function createTransaction(
        string $extID,
        string $playID,
        string $username,
        string $currency,
        string $gameCode,
        float $betAmount,
        float $betWinlose,
        string $transactionDate,
    ): void {
        DB::connection('pgsql_write')
            ->table('aix.reports')
            ->insert([
                'ext_id' => $extID,
                'username' => $username,
                'play_id' => $playID,
                'web_id' => $this->getWebID($playID),
                'currency' => $currency,
                'game_code' => $gameCode,
                'bet_amount' => $betAmount,
                'bet_valid' => $betAmount,
                'bet_winlose' => $betWinlose,
                'updated_at' => $transactionDate,
                'created_at' => $transactionDate
            ]);
    }

    public function settleTransaction(string $extID, float $winloseAmount, string $settleTime): void
    {
        DB::connection('pgsql_write')
            ->table('aix.reports')
            ->where('ext_id', $extID)
            ->update([
                'bet_winlose' => $winloseAmount,
                'updated_at' => $settleTime
            ]);
    }
}
