<?php

namespace Providers\Aix;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Providers\Aix\AixRepository;
use App\Contracts\V2\IWallet;
use Illuminate\Support\Facades\DB;
use App\Libraries\Wallet\V2\WalletReport;
use App\Exceptions\Casino\WalletErrorException;
use Providers\Aix\Exceptions\PlayerNotFoundException;
use Providers\Aix\Exceptions\InsufficientFundException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\TransactionAlreadyExistsException;
use Providers\Aix\Exceptions\ProviderTransactionNotFoundException;
use Providers\Aix\Exceptions\WalletErrorException as ProviderWalletException;


class AixService
{
    private const PROVIDER_API_TIMEZONE = 'GMT+8';

    public function __construct(
        private AixRepository $repository,
        private AixCredentials $credentials,
        private IWallet $wallet,
        private AixApi $api,
        private WalletReport $walletReport
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

        $walletResponse = $this->wallet->balance(credentials: $credentials, playID: $request->user_id);

        if ($walletResponse['status_code'] != 2100)
            throw new ProviderWalletException;

        return $walletResponse['credit'];
    }

    public function bet(Request $request): float
    {
        $playerDetails = $this->repository->getPlayerByPlayID(playID: $request->user_id);

        if (is_null($playerDetails) === true)
            throw new PlayerNotFoundException;

        $transactionData = $this->repository->getTransactionByTrxID(transactionID: $request->txn_id);

        if (is_null($transactionData) === false)
            throw new TransactionAlreadyExistsException;

        $credentials = $this->credentials->getCredentialsByCurrency(currency: $playerDetails->currency);
        
        if ($request->header('secret-key') !== $credentials->getSecretKey())
            throw new InvalidSecretKeyException;

        $balanceResponse = $this->wallet->balance(credentials: $credentials, playID: $request->user_id);

        if ($balanceResponse['status_code'] !== 2100)
            throw new ProviderWalletException;

        if ($balanceResponse['credit'] < $request->amount)
            throw new InsufficientFundException;

        try {
            DB::connection('pgsql_write')->beginTransaction();

            $transactionDate = Carbon::parse($request->debit_time, self::PROVIDER_API_TIMEZONE)
                ->setTimezone(8)
                ->format('Y-m-d H:i:s');

            $this->repository->createTransaction(
                transactionID: $request->txn_id,
                betAmount: $request->amount,
                transactionDate: $transactionDate
            );

            $report = $this->walletReport->makeSlotReport(
                transactionID: $request->txn_id,
                gameCode: $request->prd_id,
                betTime: $transactionDate
            );

            $walletResponse = $this->wallet->wager(
                credentials: $credentials,
                playID: $playerDetails->play_id,
                currency: $playerDetails->currency,
                transactionID: $request->txn_id,
                amount: $request->amount,
                report: $report
            );

            if ($walletResponse['status_code'] != 2100)
                throw new ProviderWalletException;

            DB::connection('pgsql_write')->commit();
        } catch (Exception $e) {
            DB::connection('pgsql_write')->rollback();
            throw $e;
        }

        return $walletResponse['credit_after'];
    }

    public function settle(Request $request): float
    {
        $playerData = $this->repository->getPlayerByPlayID(playID: $request->user_id);

        if (is_null($playerData) === true)
            throw new PlayerNotFoundException;

        $credentials = $this->credentials->getCredentialsByCurrency(currency: $playerData->currency);

        if ($request->header('secret-key') !== $credentials->getSecretKey())
            throw new InvalidSecretKeyException;

        $transactionData = $this->repository->getTransactionByTrxID(transactionID: $request->txn_id);

        if (is_null($transactionData) === true)
            throw new ProviderTransactionNotFoundException;

        if (is_null($transactionData->updated_at) === false)
            throw new TransactionAlreadyExistsException;

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

            $report = $this->walletReport->makeSlotReport(
                transactionID: $request->txn_id,
                gameCode: $request->prd_id,
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
                throw new ProviderWalletException;

            DB::connection('pgsql_write')->commit();
        } catch (Exception $e) {
            DB::connection('pgsql_write')->rollback();
            throw $e;
        }

        return $walletResponse['credit_after'];
    }
}
