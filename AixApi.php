<?php

namespace Providers\Aix;

use App\Exceptions\Casino\ThirdPartyApiErrorException;
use App\Libraries\LaravelHttpClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Providers\Aix\Contracts\ICredentials;

class AixApi
{
    private const CASINO_MOBILE = 0;

    public function __construct(private LaravelHttpClient $http) {}

    public function auth(ICredentials $credentials, Request $request, float $balance): string
    {
        $headers = [
            'ag-code' => $credentials->getAgCode(),
            'ag-token' => $credentials->getAgToken(),
        ];

        $request = [
            'user' => [
                'id' => $request->playId,
                'name' => $request->username,
                'balance' => $balance,
                'domain_url' => $request->host,
                'language' => 'en',
                'currency' => $request->currency,
            ],
            'prd' => [
                'id' => $request->gameId,
                'is_mobile' => $request->device == self::CASINO_MOBILE ? true : false,
            ]
        ];

        $response = $this->http->post(
            url: "{$credentials->getApiUrl()}/auth",
            request: $request,
            headers: $headers
        );

        $validator = Validator::make(
            (array)$response,
            [
                'status' => 'required|integer',
                'launch_url' => 'required|string',
            ]
        );

        if ($validator->fails())
            throw new ThirdPartyApiErrorException;

        if ($response->status == 0)
            throw new ThirdPartyApiErrorException;

        return $response->launch_url;
    }
}
