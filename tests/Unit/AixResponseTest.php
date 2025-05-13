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

    public function test_casinoSuccess_givenData_expected()
    {
        $expected = new JsonResponse(
            [
                'success' => true,
                'code' => 200,
                'data' => 'test-url',
                'error' => null
            ]
        );

        $response = $this->makeResponse();
        $result = $response->casinoSuccess('test-url');

        $this->assertEquals($expected, $result);
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
