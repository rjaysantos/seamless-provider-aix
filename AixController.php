<?php
namespace Providers\Aix;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\AbstractCasinoController;
use Providers\Aix\Exceptions\InvalidProviderRequestException;

class AixController extends AbstractCasinoController
{
    public function __construct(protected AixService $service, protected AixResponse $response){}

    public function credit(Request $request)
    {
        $validate = Validator::make(data: $request->all(), rules: [
            'user_id' => 'required|string',
            'amount' => 'required|numeric',
            'prd_id' => 'required|integer',
            'txn_id' => 'required|string',
            'credit_time' => 'required|string'
        ]);

        if ($validate->fails())
            throw new InvalidProviderRequestException;

        $balance = $this->service->settle(request: $request);

        return $this->response->successResponse(balance: $balance);
    }
}