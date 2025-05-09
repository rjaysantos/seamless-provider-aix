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
}