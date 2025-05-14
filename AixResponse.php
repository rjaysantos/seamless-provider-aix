<?php

namespace Providers\Aix;

use Illuminate\Http\JsonResponse;

class AixResponse
{
    public function casinoSuccess(string $url): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'data' => $url,
            'error' => null
        ]);
    }

    public function successResponse($balance): JsonResponse
    {
        return response()->json([
            'status' => 1,
            'balance' => $balance,
        ]);
    }
}
