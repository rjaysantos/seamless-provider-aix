<?php
namespace Providers\Aix;

use Illuminate\Http\JsonResponse;

class AixResponse
{
    public function balance($balance): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'balance' => $balance,
        ]);
    }
}