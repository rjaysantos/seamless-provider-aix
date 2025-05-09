<?php
namespace Providers\Aix;

use Illuminate\Http\Request;
use App\Contracts\V2\IWallet;
use Providers\Aix\AixCredentials;
use Providers\Aix\Exceptions\WalletErrorException;
use Providers\Aix\Exceptions\PlayerNotFoundException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;

class AixService
{
    public function __construct(
        protected AixRepository $repository, 
        protected AixCredentials $credentials, 
        protected IWallet $wallet
    ) {}

    public function getBalance(Request $request): float
    {
        $playerDetails = $this->repository->getPlayerByUserIDProvider(userID: $request->user_id);

        if (is_null($playerDetails) === true)
            throw new PlayerNotFoundException;

        $credentials = $this->credentials->getCredentialsByCurrency(currency: $playerDetails->currency);
        
        if ($request->header('secret-key') !== $credentials->getSecretKey())
            throw new InvalidSecretKeyException;

        $walletResponse = $this->wallet->balance(credentials: $credentials, playID: $playerDetails->play_id);

        if ($walletResponse['status_code'] != 2100)
            throw new WalletErrorException;

        return $walletResponse['credit'];
    }
}