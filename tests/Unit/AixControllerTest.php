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

    #[DataProvider('creditParams')]
    public function test_credit_missingRequest_InvalidProviderRequestException($param)
    {
        $this->expectException(InvalidProviderRequestException::class);

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        unset($request[$param]);

        $controller = $this->makeController();
        $controller->credit(request: $request);
    }

    #[DataProvider('creditParams')]
    public function test_credit_invalidRequestType_InvalidProviderRequestException($param, $value)
    {
        $this->expectException(InvalidProviderRequestException::class);

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request[$param] = $value;

        $controller = $this->makeController();
        $controller->credit(request: $request);
    }

    public static function creditParams()
    {
        return [
            ['user_id', 'test'],
            ['amount', 'test'],
            ['game_id', 'test'],
            ['txn_id', 123],
            ['credit_time', 123]
        ];
    }

    public function test_credit_mockService_settle()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $mockService = $this->createMock(AixService::class);
        $mockService->expects($this->once())
            ->method('credit')
            ->with(request: $request);

        $controller = $this->makeController(service: $mockService);
        $controller->credit(request: $request);
    }

    public function test_credit_mockResponse_successResponse()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $stubService = $this->createMock(AixService::class);
        $stubService->method('credit')
            ->willReturn(1000.00);

        $mockResponse = $this->createMock(AixResponse::class);
        $mockResponse->expects($this->once())
            ->method('successResponse')
            ->with(balance: 1000.00);

        $controller = $this->makeController(service: $stubService, response: $mockResponse);
        $controller->credit(request: $request);
    }

    public function test_credit_stubResponse_expectedData()
    {
        $expectedData = new JsonResponse;

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $mockResponse = $this->createMock(AixResponse::class);
        $mockResponse->method('successResponse')
            ->willReturn(new JsonResponse);

        $controller = $this->makeController(response: $mockResponse);
        $response = $controller->credit(request: $request);

        $this->assertEquals(expected: $expectedData, actual: $response);
    }
}