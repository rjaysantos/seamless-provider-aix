<?php

namespace Providers\Aix;

use Exception;
use Carbon\Carbon;
use App\Contracts\V2\IWallet;
use App\Exceptions\Casino\WalletErrorException;
use Illuminate\Http\Request;
use Providers\Aix\AixRepository;
use Providers\Aix\AixCredentials;
use Illuminate\Support\Facades\DB;
use App\Libraries\Wallet\V2\WalletReport;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\ProviderPlayerNotFoundException;
use Providers\Aix\Exceptions\TransactionAlreadySettledException;
use Providers\Aix\Exceptions\ProviderTransactionNotFoundException;
use Providers\Aix\Exceptions\WalletErrorException as ProviderWalletErrorException;

class AixService
{
    private const PROVIDER_API_TIMEZONE = 'GMT+8';

    public function __construct(
        private AixRepository $repository,
        private AixCredentials $credentials,
        private IWallet $wallet,
        private AixApi $api,
        private WalletReport $report
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

    public function credit(Request $request): float
    {
        $playerData = $this->repository->getPlayerByPlayID(playID: $request->user_id);

        if (is_null($playerData) === true)
            throw new ProviderPlayerNotFoundException;

        $credentials = $this->credentials->getCredentialsByCurrency(currency: $playerData->currency);

        if ($request->header('secret-key') !== $credentials->getSecretKey())
            throw new InvalidSecretKeyException;

        $transactionData = $this->repository->getTransactionByTrxID(trxID: $request->txn_id);

        if (is_null($transactionData) === true)
            throw new ProviderTransactionNotFoundException;

        if (is_null($transactionData->updated_at) === false)
            throw new TransactionAlreadySettledException;

        try {
            DB::connection('pgsql_write')->beginTransaction();

            $transactionDate = Carbon::parse($request->credit_time, self::PROVIDER_API_TIMEZONE)
                ->setTimezone('GMT+8')
                ->format('Y-m-d H:i:s');

            $this->repository->settleTransaction(
                trxID: $request->txn_id,
                winAmount: $request->amount,
                settleTime: $transactionDate
            );

            $report = $this->report->makeSlotReport(
                transactionID: $request->txn_id,
                gameCode: $request->game_id,
                betTime: $transactionDate
            );

            $walletResponse = $this->wallet->payout(
                credentials: $credentials,
                playID: $playerData->play_id,
                currency: $playerData->currency,
                transactionID: "payout-{$request->txn_id}",
                amount: $request->amount,
                report: $report
            );

            if ($walletResponse['status_code'] != 2100)
                throw new ProviderWalletErrorException;

            DB::connection('pgsql_write')->commit();
        } catch (Exception $e) {
            DB::connection('pgsql_write')->rollback();
            throw $e;
        }

        return $walletResponse['credit_after'];
    }
}
