<?php

use Tests\TestCase;
use Illuminate\Http\Request;
use Providers\Aix\AixService;
use Providers\Aix\AixResponse;
use Providers\Aix\AixController;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Providers\Aix\Exceptions\InvalidProviderRequestException;

class AixControllerTest extends TestCase
{
    private function makeController($service = null, $response = null): AixController
    {
        $service ??= $this->createStub(AixService::class);
        $response ??= $this->createStub(AixResponse::class);

        return new AixController(service: $service, response: $response);
    }

    #[DataProvider('debitRequestParams')]
    public function test_debit_missingRequestParameters_InvalidProviderRequestException($params)
    {
        $this->expectException(InvalidProviderRequestException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        unset($request[$params]);
        
        $controller = $this->makeController();
        $controller->debit(request: $request);
    }

    #[DataProvider('debitRequestParams')]
    public function test_debit_invalidRequestParameters_InvalidProviderRequestException($params, $value)
    {
        $this->expectException(InvalidProviderRequestException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        unset($request[$params]);
        
        $controller = $this->makeController();
        $controller->debit(request: $request);
    }

    public static function debitRequestParams()
    {
        return [
            ['user_id', 123],
            ['amount', 'test'],
            ['prd_id', 'test'],
            ['txn_id', 12345],
            ['round_id', 12345],
            ['debit_time', 12345]
        ];
    }

    public function test_debit_mockService_bet()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $mockService = $this->createMock(AixService::class);
        $mockService->expects($this->once())
            ->method('bet')
            ->with(request: $request);

        $controller = $this->makeController(service: $mockService);
        $controller->debit(request: $request);
    }

    public function test_debit_mockResponse_balance()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]); 

        $mockResponse = $this->createMock(AixResponse::class);
        $mockResponse->expects($this->once())
            ->method('balance')
            ->with(balance: 0);

        $controller = $this->makeController(response: $mockResponse);
        $controller->debit(request: $request);
    }

    public function test_debit_stubResponse_expectedData()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]); 

        $expected = new JsonResponse;
        
        $stubService = $this->createMock(AixService::class);
        $stubService->method('bet')
                ->willReturn(0.0);

        $stubResponse = $this->createMock(AixResponse::class);
        $stubResponse->method('balance')
                ->willReturn($expected);

        $controller = $this->makeController(service: $stubService, response: $stubResponse);
        $result = $controller->debit(request: $request);

        $this->assertEquals(expected: $expected, actual: $result);
    }
}