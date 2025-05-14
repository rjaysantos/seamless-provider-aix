<?php
namespace Providers\Aix;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\AbstractCasinoController;
use Providers\Aix\Exceptions\InvalidProviderRequestException;

class AixController extends AbstractCasinoController
{
    public function __construct(protected AixService $service, protected AixResponse $response){}

    private function validateProviderRequest(Request $request, array $rules)
    {
        $validate = Validator::make($request->all(), $rules);

        if ($validate->fails())
            throw new InvalidProviderRequestException;
    }

    public function balance(Request $request)
    {
        $this->validateProviderRequest(request: $request, rules: [
            'user_id' => 'required|string',
            'prd_id' => 'required|integer'
        ]);

        $balance = $this->service->getBalance(request: $request);

        return $this->response->successResponse(balance: $balance);
    }

    public function credit(Request $request)
    {
        $this->validateProviderRequest(request: $request, rules: [
            'user_id' => 'required|string',
            'amount' => 'required|numeric',
            'prd_id' => 'required|integer',
            'txn_id' => 'required|string',
            'credit_time' => 'required|string'
        ]);

        $balance = $this->service->settle(request: $request);

        return $this->response->successResponse(balance: $balance);
    }

    public function debit(Request $request)
    {
        $this->validateProviderRequest(request: $request, rules: [
            'user_id' => 'required|string',
            'amount' => 'required|numeric',
            'prd_id' => 'required|integer',
            'txn_id' => 'required|string',
            'round_id' => 'required|string',
            'debit_time' => 'required|string'
        ]);

        $balance = $this->service->bet(request: $request);

        return $this->response->successResponse(balance: $balance);
    }
}