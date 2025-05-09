<?php

namespace Providers\Aix;

use App\Contracts\V2\IWallet;
use App\Exceptions\Casino\WalletErrorException;
use Illuminate\Http\Request;
use Providers\Aix\AixCredentials;
use Providers\Aix\Exceptions\PlayerNotFoundException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\WalletErrorException as WalletException;


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

    public function getBalance(Request $request): float
    {
        $playerDetails = $this->repository->getPlayerByPlayID(playID: $request->user_id);

        if (is_null($playerDetails) === true)
            throw new PlayerNotFoundException;

        $credentials = $this->credentials->getCredentialsByCurrency(currency: $playerDetails->currency);
        
        if ($request->header('secret-key') !== $credentials->getSecretKey())
            throw new InvalidSecretKeyException;

        $walletResponse = $this->wallet->balance(credentials: $credentials, playID: $playerDetails->play_id);

        if ($walletResponse['status_code'] != 2100)
            throw new WalletException;

        return $walletResponse['credit'];
    }
}
