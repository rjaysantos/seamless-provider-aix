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
}
