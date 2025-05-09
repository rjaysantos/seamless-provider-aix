<?php

use Illuminate\Http\JsonResponse;
use Providers\Aix\AixResponse;
use Tests\TestCase;

class AixResponseTest extends TestCase
{
    public function makeResponse()
    {
        return new AixResponse;
    }

    public function test_balance_stubResponse_expected()
    {
        $balance = 1000.0;

        $expected = new JsonResponse([
            'status' => 1,
            'balance' => $balance
        ]);

        $response = $this->makeResponse();
        $result = $response->balance(balance: $balance);

        $this->assertEquals(expected: $expected, actual: $result);
    }
}