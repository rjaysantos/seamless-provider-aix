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

    public function getPlayerByUserIDProvider(string $userID): ?object
    {
        return DB::table('aix.players')
            ->where('user_id_provider', $userID)
            ->first();
    }
}