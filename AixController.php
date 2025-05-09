<?php
namespace Providers\Aix;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\AbstractCasinoController;
use Providers\Aix\Exceptions\InvalidProviderRequestException;

class AixController extends AbstractCasinoController
{
    public function __construct(protected AixService $service, protected AixResponse $response){}

    private function validateProviderRequest(Request $request, array $rules): void
    {
        $validate = Validator::make(data: $request->all(), rules: $rules);

        if ($validate->fails())
            throw new InvalidProviderRequestException;
    }

    public function credit(Request $request)
    {
        $this->validateProviderRequest(request: $request, rules: [
            'user_id' => 'required|integer',
            'amount' => 'required|numeric',
            'game_id' => 'required|integer',
            'txn_id' => 'required|string',
            'credit_time' => 'required|string'
        ]);

        $balance = $this->service->credit(request: $request);

        return $this->response->successResponse(balance: $balance);
    }
}