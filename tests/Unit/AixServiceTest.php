<?php

use Tests\TestCase;
use Providers\Aix\AixApi;
use Illuminate\Http\Request;
use App\Contracts\V2\IWallet;
use Providers\Aix\AixService;
use Providers\Aix\AixRepository;
use Providers\Aix\AixCredentials;
use Providers\Aix\Credentials\Staging;
use Wallet\V1\ProvSys\Transfer\Report;
use App\Libraries\Wallet\V2\WalletReport;
use Providers\Aix\Contracts\ICredentials;
use App\Exceptions\Casino\WalletErrorException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\ProviderPlayerNotFoundException;
use Providers\Aix\Exceptions\TransactionAlreadySettledException;
use Providers\Aix\Exceptions\ProviderTransactionNotFoundException;
use Providers\Aix\Exceptions\WalletErrorException as ProviderWalletErrorException;

class AixServiceTest extends TestCase
{
    public function makeService($repository = null, $credentials = null, $wallet = null, $api = null, $report = null)
    {
        $repository ??= $this->createMock(AixRepository::class);
        $credentials ??= $this->createMock(AixCredentials::class);
        $wallet ??= $this->createMock(IWallet::class);
        $api ??= $this->createMock(AixApi::class);
        $report ??= $this->createStub(WalletReport::class);

        return new AixService(
            repository: $repository,
            credentials: $credentials,
            wallet: $wallet,
            api: $api,
            report: $report,
        );
    }

    public function test_getLaunchUrl_mockRepository_createIgnorePlayer()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('createIgnorePlayer')
            ->with('test-play-id', 'IDR');

        $stubWallet = $this->createMock(originalClassName: IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $service = $this->makeService(repository: $mockRepository, wallet: $stubWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockCredentials_getCredentialsByCurrency()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $mockCredentials = $this->createMock(AixCredentials::class);
        $mockCredentials->expects($this->once())
            ->method('getCredentialsByCurrency')
            ->with('IDR');

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $service = $this->makeService(credentials: $mockCredentials, wallet: $stubWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockWallet_getBalance()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $credentials = $this->createMock(ICredentials::class);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('balance')
            ->with($credentials, 'test-play-id')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $service = $this->makeService(credentials: $stubCredentials, wallet: $mockWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockWalletStatusCodeNot2100_WalletErrorException()
    {
        $this->expectException(WalletErrorException::class);

        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $credentials = $this->createMock(ICredentials::class);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->method('balance')
            ->willReturn([
                'status_code' => 1234
            ]);

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $service = $this->makeService(credentials: $stubCredentials, wallet: $mockWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_mockApi_auth()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $credentials = $this->createMock(ICredentials::class);

        $mockApi = $this->createMock(AixApi::class);
        $mockApi->expects($this->once())
            ->method('auth')
            ->with($credentials, $request, 100);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $service = $this->makeService(api: $mockApi, credentials: $stubCredentials, wallet: $stubWallet);
        $service->getLaunchUrl($request);
    }

    public function test_getLaunchUrl_stubApi_expected()
    {
        $expected = 'test-url';

        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR'
        ]);

        $stubApi = $this->createMock(AixApi::class);
        $stubApi->method('auth')
            ->willReturn('test-url');

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'status_code' => 2100,
                'credit' => 100
            ]);

        $service = $this->makeService(api: $stubApi, wallet: $stubWallet);
        $result = $service->getLaunchUrl($request);

        $this->assertSame($expected, $result);
    }

    public function test_settle_mockRepository_getPlayerByPlayID()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
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

        $service->settle(request: $request);
    }

    public function test_settle_stubRepositoryNullPlayer_ProviderPlayerNotFoundException()
    {
        $this->expectException(ProviderPlayerNotFoundException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);
        
        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository);
        $service->settle(request: $request);
    }

    public function test_settle_mockCredentails_getCredentialsByCurrency()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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

        $service->settle(request: $request);
    }

    public function test_settle_stubRepositoryInvalidSecretKey_InvalidSecretKeyException()
    {
        $this->expectException(InvalidSecretKeyException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'invalidSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
                'currency' => 'IDR'
            ]);

        $providerCredentials = $this->createMock(ICredentials::class);
        $providerCredentials->method('getSecretKey')
            ->willReturn('secretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($providerCredentials);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials,);
        $service->settle(request: $request);
    }

    public function test_settle_mockRepository_getTransactionByTrxID()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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

        $service->settle(request: $request);
    }

    public function test_settle_stubRepositoryNullTransaction_TransactionNotFoundException()
    {
        $this->expectException(ProviderTransactionNotFoundException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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
        $service->settle(request: $request);
    }

    public function test_settle_stubRepositoryTransactionAlreadyExist_TransactionAlreadySettledException()
    {
        $this->expectException(TransactionAlreadySettledException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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
        $service->settle(request: $request);
    }

    public function test_settle_mockRepository_settleTransaction()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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

        $service->settle(request: $request);
    }

    public function test_settle_mockReport_makeSlotReport()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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
                gameCode: $request->prd_id,
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

        $service->settle(request: $request);
    }

    public function test_settle_mockWallet_payout()
    {
        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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
                playID: 'testPlayID',
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

        $service->settle(request: $request);
    }

    public function test_settle_stubWalletInvalidStatus_WalletErrorException()
    {
        $this->expectException(ProviderWalletErrorException::class);

        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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

        $service->settle(request: $request);
    }

    public function test_settle_stubWallet_expectedData()
    {
        $expectedData = 1200.00;

        $request = new Request([
            'user_id' => 'testPlayID',
            'amount' => 200,
            'prd_id' => 1,
            'txn_id' => 'testTransactionID',
            'credit_time' => '2024-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'secretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayID',
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

        $response = $service->settle(request: $request);

        $this->assertSame(expected: $expectedData, actual: $response);
    }
}