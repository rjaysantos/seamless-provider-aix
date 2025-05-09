<?php

namespace Providers\Aix;

use App\Contracts\V2\IWallet;
use App\Exceptions\Casino\WalletErrorException;
use Illuminate\Http\Request;

class AixService
{
    public function __construct(
        private AixRepository $repository,
        private AixCredentials $credentials,
        private IWallet $wallet,
        private AixApi $api
    ) {}

    public function getLaunchUrl(Request $request): string
    {
        $this->repository->createIgnorePlayer(playID: $request->playId, currency: $request->currency);

        $credentials = $this->credentials->getCredentialsByCurrency($request->currency);

        $walletResponse = $this->wallet->Balance(credentials: $credentials, playID: $request->playId);

        if ($walletResponse['status_code'] != 2100)
            throw new WalletErrorException;

        return $this->api->auth(credentials: $credentials, request: $request, balance: $walletResponse['credit']);
    }
}
