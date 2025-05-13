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

    #[DataProvider('balanceParams')]
    public function test_balance__incompleteRequestParameters_InvalidProviderRequestException($param)
    {
        $this->expectException(InvalidProviderRequestException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'prd_id' => 1
        ]);

        unset($request[$param]);

        $controller = $this->makeController();
        $controller->balance(request: $request);
    }

    #[DataProvider('balanceParams')]
    public function test_balance__invalidRequestParameters_InvalidProviderRequestException($param, $value)
    {
        $this->expectException(InvalidProviderRequestException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'prd_id' => 1
        ]);

        $request[$param] = $value;

        $controller = $this->makeController();
        $controller->balance(request: $request);
    }

    public static function balanceParams()
    {
        return [
            ['user_id', 123],
            ['prd_id', 'test']
        ];
    }

    public function test_balance_mockService_getBalance()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'prd_id' => 1
        ]);

        $mockService = $this->createMock(AixService::class);
        $mockService->expects($this->once())
            ->method('getBalance')
            ->with(request: $request);

        $controller = $this->makeController(service: $mockService);
        $controller->balance(request: $request);
    }

    public function test_balance_mockResponse_balance()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'prd_id' => 1
        ]);

        $stubService = $this->createMock(AixService::class);
        $stubService->method('getBalance')
            ->willReturn(1000.0);

        $mockResponse = $this->createMock(AixResponse::class);
        $mockResponse->expects($this->once())
            ->method('balance')
            ->with(balance: 1000.0);

        $controller = $this->makeController(service: $stubService, response: $mockResponse);
        $controller->balance(request: $request);
    }

    public function test_balance_stubResponse_expectedData()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'prd_id' => 1
        ]);

        $expected = new JsonResponse;

        $stubService = $this->createMock(AixService::class);
        $stubService->method('getBalance')
            ->willReturn(1000.0);

        $stubResponse = $this->createMock(AixResponse::class);
        $stubResponse->method('balance')
            ->willReturn($expected);

        $controller = $this->makeController(service: $stubService, response: $stubResponse);
        $response = $controller->balance(request: $request);

        $this->assertEquals(expected: $expected, actual: $response);
    }

    #[DataProvider('creditParams')]
    public function test_credit_missingRequest_InvalidProviderRequestException($param)
    {
        $this->expectException(InvalidProviderRequestException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
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
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
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
            ['user_id', 123],
            ['amount', 'test'],
            ['prd_id', 'test'],
            ['txn_id', 123],
            ['credit_time', 123]
        ];
    }

    public function test_credit_mockService_settle()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $mockService = $this->createMock(AixService::class);
        $mockService->expects($this->once())
            ->method('settle')
            ->with(request: $request);

        $controller = $this->makeController(service: $mockService);
        $controller->credit(request: $request);
    }

    public function test_credit_mockResponse_successResponse()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $stubService = $this->createMock(AixService::class);
        $stubService->method('settle')
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
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
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