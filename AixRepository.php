<?php
namespace Providers\Aix;

use Illuminate\Support\Facades\DB;

class AixRepository
{
    public function getPlayerByUserIDProvider(string $userID): ?object
    {
        return DB::table('aix.players')
            ->where('user_id_provider', $userID)
            ->first();
    }
}