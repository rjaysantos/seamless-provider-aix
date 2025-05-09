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
            'user_id' => 'required|integer',
            'prd_id' => 'required|integer'
        ]);

        $balance = $this->service->getBalance(request: $request);

        return $this->response->balance(balance: $balance);
    }
}