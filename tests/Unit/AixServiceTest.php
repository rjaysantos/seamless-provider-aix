<?php

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Contracts\V2\IWallet;
use Providers\Aix\AixService;
use Providers\Aix\AixRepository;
use Providers\Aix\AixCredentials;
use Wallet\V1\ProvSys\Transfer\Report;
use App\Libraries\Wallet\V2\WalletReport;
use Providers\Aix\Contracts\ICredentials;
use Providers\Aix\Exceptions\WalletErrorException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\ProviderPlayerNotFoundException;
use Providers\Aix\Exceptions\TransactionAlreadySettledException;
use Providers\Aix\Exceptions\ProviderTransactionNotFoundException;

class AixServiceTest extends TestCase
{
    private function makeService(
        $repository = null,
        $credentials = null,
        $wallet = null,
        $report = null
    ): AixService {
        $repository ??= $this->createStub(AixRepository::class);
        $credentials ??= $this->createStub(AixCredentials::class);
        $wallet ??= $this->createStub(IWallet::class);
        $report ??= $this->createStub(WalletReport::class);

        return new AixService(
            repository: $repository,
            credentials: $credentials,
            wallet: $wallet,
            report: $report,
        );
    }

    public function test_credit_mockRepository_getPlayerByPlayID()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('getPlayerByPlayID')
            ->with(playID: $request->user_id)
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $mockRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('payout')
            ->willReturn([
                'credit_after' => 1200.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $mockRepository,
            credentials: $stubCredentials,
            report: $stubReport,
            wallet: $stubWallet
        );

        $service->credit(request: $request);
    }

    public function test_credit_stubRepositoryNullPlayer_ProviderPlayerNotFoundException()
    {
        $this->expectException(ProviderPlayerNotFoundException::class);

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);
        
        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository);
        $service->credit(request: $request);
    }

    public function test_credit_mockCredentails_getCredentialsByCurrency()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $mockCredentials = $this->createMock(AixCredentials::class);
        $mockCredentials->expects($this->once())
            ->method('getCredentialsByCurrency')
            ->with(currency: 'IDR')
            ->willReturn($providerCredentials);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('payout')
            ->willReturn([
                'credit_after' => 1200.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $mockCredentials,
            report: $stubReport,
            wallet: $stubWallet
        );

        $service->credit(request: $request);
    }

    public function test_credit_stubRepositoryInvalidSecretKey_InvalidSecretKeyException()
    {
        $this->expectException(InvalidSecretKeyException::class);

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'invalidSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials,);
        $service->credit(request: $request);
    }

    public function test_credit_mockRepository_getTransactionByTrxID()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $mockRepository->expects($this->once())
            ->method('getTransactionByTrxID')
            ->with(trxID: $request->txn_id)
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('payout')
            ->willReturn([
                'credit_after' => 1200.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $mockRepository,
            credentials: $stubCredentials,
            report: $stubReport,
            wallet: $stubWallet
        );

        $service->credit(request: $request);
    }

    public function test_credit_stubRepositoryNullTransaction_TransactionNotFoundException()
    {
        $this->expectException(ProviderTransactionNotFoundException::class);

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials,);
        $service->credit(request: $request);
    }

    public function test_credit_stubRepositoryTransactionAlreadyExist_TransactionAlreadySettledException()
    {
        $this->expectException(TransactionAlreadySettledException::class);

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => '2021-01-01 00:00:00'
            ]);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials,);
        $service->credit(request: $request);
    }

    public function test_credit_mockRepository_settleTransaction()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $mockRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $mockRepository->expects($this->once())
            ->method('settleTransaction')
            ->with(
                trxID: $request->txn_id,
                winAmount: 200,
                settleTime: '2024-01-01 00:00:00'
            );

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('payout')
            ->willReturn([
                'credit_after' => 1200.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $mockRepository,
            credentials: $stubCredentials,
            report: $stubReport,
            wallet: $stubWallet
        );

        $service->credit(request: $request);
    }

    public function test_credit_mockReport_makeSlotReport()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $mockReport = $this->createMock(WalletReport::class);
        $mockReport->expects($this->once())
            ->method('makeSlotReport')
            ->with(
                transactionID: $request->txn_id,
                gameCode: $request->game_id,
                betTime: '2024-01-01 00:00:00'
            )
            ->willReturn(new Report);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('payout')
            ->willReturn([
                'credit_after' => 1200.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            report: $mockReport,
            wallet: $stubWallet
        );

        $service->credit(request: $request);
    }

    public function test_credit_mockWallet_payout()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('payout')
            ->with(
                credentials: $providerCredentials,
                playID: '12345',
                currency: 'IDR',
                transactionID: "payout-{$request->txn_id}",
                amount: 200.00,
                report: new Report
            )
            ->willReturn([
                'credit_after' => 1200.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            report: $stubReport,
            wallet: $mockWallet
        );

        $service->credit(request: $request);
    }

    public function test_credit_stubWalletInvalidStatus_WalletErrorException()
    {
        $this->expectException(WalletErrorException::class);

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('payout')
            ->willReturn([
                'status_code' => 999
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            report: $stubReport,
            wallet: $stubWallet
        );

        $service->credit(request: $request);
    }

    public function test_credit_stubWallet_expectedData()
    {
        $expectedData = 1200.00;

        $request = new Request([
            'user_id' => 12345,
            'amount' => 200,
            'game_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR'
            ]);

            $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => null
            ]);

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('payout')
            ->willReturn([
                'credit_after' => 1200.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            report: $stubReport,
            wallet: $stubWallet
        );

        $response = $service->credit(request: $request);

        $this->assertSame(expected: $expectedData, actual: $response);
    }
}