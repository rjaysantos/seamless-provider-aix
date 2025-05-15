<?php

use Tests\TestCase;
use Providers\Aix\AixApi;
use Illuminate\Http\Request;
use App\Contracts\V2\IWallet;
use Providers\Aix\AixService;
use Providers\Aix\AixRepository;
use Providers\Aix\AixCredentials;
use Wallet\V1\ProvSys\Transfer\Report;
use App\Libraries\Wallet\V2\WalletReport;
use Providers\Aix\Contracts\ICredentials;
use App\Exceptions\Casino\WalletErrorException;
use Providers\Aix\Exceptions\PlayerNotFoundException;
use Providers\Aix\Exceptions\InsufficientFundException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\TransactionAlreadyExistsException;
use Providers\Aix\Exceptions\TransactionAlreadySettledException;
use Providers\Aix\Exceptions\ProviderTransactionNotFoundException;
use Providers\Aix\Exceptions\WalletErrorException as WalletException;

class AixServiceTest extends TestCase
{
    public function makeService(
        $repository = null,
        $credentials = null,
        $wallet = null,
        $api = null,
        $walletReport = null
    ): AixService {

        $repository ??= $this->createMock(AixRepository::class);
        $credentials ??= $this->createMock(AixCredentials::class);
        $wallet ??= $this->createMock(IWallet::class);
        $api ??= $this->createMock(AixApi::class);
        $walletReport ??= $this->createMock(WalletReport::class);

        return new AixService(
            repository: $repository,
            credentials: $credentials,
            wallet: $wallet,
            api: $api,
            walletReport: $walletReport
        );
    }

    public function test_getLaunchUrl_mockRepository_createIgnorePlayer()
    {
        $request = new Request([
            'playId' => 'test-play-id',
            'currency' => 'IDR',
            'username' => 'username'
        ]);

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('createIgnorePlayer')
            ->with('test-play-id', 'username', 'IDR');

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
            'currency' => 'IDR',
            'username' => 'username'
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
            'currency' => 'IDR',
            'username' => 'username'
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
            'currency' => 'IDR',
            'username' => 'username'
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
            'currency' => 'IDR',
            'username' => 'username'
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
            'currency' => 'IDR',
            'username' => 'username'
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

    public function test_getBalance_mockRepository_getPlayerByPlayID()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('getPlayerByPlayID')
            ->with(userID: $request->user_id)
            ->willReturn((object)[
                'play_id' => 'testPlayer',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createStub(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $service = $this->makeService(repository: $mockRepository, wallet: $stubWallet, credentials: $stubCredentials);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_playerNotFound_PlayerNotFoundException()
    {
        $this->expectException(PlayerNotFoundException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_mockCredentials_getCredentialsByCurrency()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object)[
                'play_id' => 'testPlayer',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $mockCredentials = $this->createMock(AixCredentials::class);
        $mockCredentials->expects($this->once())
            ->method('getCredentialsByCurrency')
            ->with(currency: 'IDR')
            ->willReturn($credentials);

        $stubWallet = $this->createStub(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $service = $this->makeService(repository: $stubRepository, credentials: $mockCredentials, wallet: $stubWallet);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_invalidSecretKey_invalidSecretKeyException()
    {
        $this->expectException(InvalidSecretKeyException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'invalidSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayer',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 100.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            wallet: $stubWallet,
            credentials: $stubCredentials
        );
        $service->getBalance(request: $request);
    }

    public function test_getBalance_mockWallet_balance()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object)[
                'play_id' => 'testPlayer',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('balance')
            ->with(
                credentials: $credentials,
                playID: 'testPlayer'
            )
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $service = $this->makeService(repository: $stubRepository, wallet: $mockWallet, credentials: $stubCredentials);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_walletStatusCodeNot2100_WalletException()
    {
        $this->expectException(WalletException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayer',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn(['status_code' => 'invalid']);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials, wallet: $stubWallet);
        $service->getBalance(request: $request);
    }

    public function test_getBalance_stubWallet_expectedData()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'prd_id' => 1
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $expected = 1000.0;

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => 'testPlayer',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createStub(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $service = $this->makeService(repository: $stubRepository, wallet: $stubWallet, credentials: $stubCredentials);
        $result = $service->getBalance(request: $request);

        $this->assertEquals(expected: $expected, actual: $result);
    }

    public function test_bet_mockRepository_getPlayerByPlayID()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('getPlayerByPlayID')
            ->with(playID: $request->user_id)
            ->willReturn((object) [
                'currency' => 'IDR',
                'play_id' => 'testPlayer',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $mockRepository,
            credentials: $stubCredentials,
            wallet: $stubWallet,
            walletReport: $stubWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_playerNotFound_PlayerNotFoundException()
    {
        $this->expectException(PlayerNotFoundException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository);
        $service->bet(request: $request);
    }

    public function test_bet_mockRepository_getTransactionByExtID()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('getTransactionByExtID')
            ->with(trxID: "wager-{$request->txn_id}")
            ->willReturn(null);

        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'currency' => 'IDR',
                'play_id' => 'testPlayer',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $mockRepository,
            credentials: $stubCredentials,
            wallet: $stubWallet,
            walletReport: $stubWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_transactionAlreadyExists_TransactionAlreadyExistsException()
    {
        $this->expectException(TransactionAlreadyExistsException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'currency' => 'IDR',
                'play_id' => 'testPlayer',
                'username' => 'username'
            ]);

        $stubRepository->method('getTransactionByExtID')
            ->willReturn((object) [
                'trx_id' => 'testTxnID'
            ]);

        $service = $this->makeService(repository: $stubRepository);
        $service->bet(request: $request);
    }

    public function test_bet_mockCredentials_getCredentialsByCurrency()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getTransactionByExtID')
            ->willReturn(null);

        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'currency' => 'IDR',
                'play_id' => 'testPlayer',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $mockCredentials = $this->createMock(AixCredentials::class);
        $mockCredentials->expects($this->once())
            ->method('getCredentialsByCurrency')
            ->with(currency: 'IDR')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $mockCredentials,
            wallet: $stubWallet,
            walletReport: $stubWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_invalidSecretKey_InvalidSecretKeyException()
    {
        $this->expectException(InvalidSecretKeyException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'invalidSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 100.00,
                'status_code' => 2100
            ]);

        $service = $this->makeService(
            repository: $stubRepository,
            wallet: $stubWallet,
            credentials: $stubCredentials
        );
        $service->bet(request: $request);
    }

    public function test_bet_mockWallet_balance()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'currency' => 'IDR',
                'play_id' => 'testPlayer',
                'username' => 'username'
            ]);

        $stubRepository->method('getTransactionByExtID')
            ->willReturn(null);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('balance')
            ->with(
                credentials: $credentials,
                playID: 'testPlayer'
            )
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $mockWallet->method('wager')
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            wallet: $mockWallet,
            walletReport: $stubWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_balanceWalletStatusCodeNot2100_WalletException()
    {
        $this->expectException(WalletException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000,
                'status_code' => 'invalid'
            ]);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials, wallet: $stubWallet);
        $service->bet(request: $request);
    }

    public function test_bet_walletBalanceNotEnough_InsufficientFundException()
    {
        $this->expectException(InsufficientFundException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 10,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 'invalid'
            ]);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials, wallet: $stubWallet);
        $service->bet(request: $request);
    }

    public function test_bet_mockRepository_createTransaction()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('createTransaction')
            ->with(
                extID: "wager-{$request->txn_id}",
                playID: '12345',
                username: 'testusername',
                currency: 'IDR',
                gameCode: $request->prd_id,
                betAmount: $request->amount,
                winloseAmount: 0,
                transactionDate: '2025-01-01 00:00:00',
            );

        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'username' => 'testusername',
                'currency' => 'IDR'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 2000,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $mockRepository,
            credentials: $stubCredentials,
            wallet: $stubWallet,
            walletReport: $stubWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_mockWalletReport_makeSlotReport()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 2000,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $mockWalletReport = $this->createMock(WalletReport::class);
        $mockWalletReport->expects($this->once())
            ->method('makeSlotReport')
            ->with(
                extID: $request->txn_id,
                gameCode: $request->prd_id,
                betTime: '2025-01-01 00:00:00'
            )
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            wallet: $stubWallet,
            walletReport: $mockWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_mockWallet_wager()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $report = new Report;

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('wager')
            ->with(
                credentials: $credentials,
                playID: '12345',
                currency: 'IDR',
                extID: "wager-{$request->txn_id}",
                amount: $request->amount,
                report: $report
            )
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $mockWallet->method('balance')
            ->willReturn([
                'credit' => 2000,
                'status_code' => 2100
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn($report);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            wallet: $mockWallet,
            walletReport: $stubWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_walletStatusCodeNot2100_WalletException()
    {
        $this->expectException(WalletException::class);

        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 1000.0,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 'invalid'
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            wallet: $stubWallet,
            walletReport: $stubWalletReport
        );

        $service->bet(request: $request);
    }

    public function test_bet_stubWallet_expected()
    {
        $request = new Request([
            'user_id' => 'testPlayer',
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $expected = 1000.0;

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
                'currency' => 'IDR',
                'username' => 'username'
            ]);

        $credentials = $this->createMock(ICredentials::class);
        $credentials->method('getSecretKey')
            ->willReturn('testSecretKey');

        $stubCredentials = $this->createMock(AixCredentials::class);
        $stubCredentials->method('getCredentialsByCurrency')
            ->willReturn($credentials);

        $stubWallet = $this->createMock(IWallet::class);
        $stubWallet->method('balance')
            ->willReturn([
                'credit' => 2000,
                'status_code' => 2100
            ]);

        $stubWallet->method('wager')
            ->willReturn([
                'status_code' => 2100,
                'credit_after' => 1000.0
            ]);

        $stubWalletReport = $this->createMock(WalletReport::class);
        $stubWalletReport->method('makeSlotReport')
            ->willReturn(new Report);

        $service = $this->makeService(
            repository: $stubRepository,
            credentials: $stubCredentials,
            wallet: $stubWallet,
            walletReport: $stubWalletReport
        );

        $result = $service->bet(request: $request);

        $this->assertSame(expected: $expected, actual: $result);
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

        $mockRepository->method('getTransactionByExtID')
            ->willReturnOnConsecutiveCalls(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
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
            walletReport: $stubReport,
            wallet: $stubWallet
        );

        $service->settle(request: $request);
    }

    public function test_settle_stubRepositoryNullPlayer_ProviderPlayerNotFoundException()
    {
        $this->expectException(PlayerNotFoundException::class);

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

        $stubRepository->method('getTransactionByExtID')
            ->willReturnOnConsecutiveCalls(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
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
            repository: $stubRepository,
            credentials: $mockCredentials,
            walletReport: $stubReport,
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

    public function test_settle_mockRepository_getTransactionByExtID()
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

        $expectedValues = [
            'wager-' . $request->txn_id,
            'payout-' . $request->txn_id,
        ];

        $callCount = 0;

        $mockRepository->expects($this->exactly(2))
            ->method('getTransactionByExtID')
            ->with($this->callback(function ($param) use (&$callCount, $expectedValues) {
                return $param === $expectedValues[$callCount++];
            }))
            ->willReturn(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
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
            walletReport: $stubReport,
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

        $stubRepository->method('getTransactionByExtID')
            ->willReturn(null);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials,);
        $service->settle(request: $request);
    }

    public function test_settle_stubRepositoryTransactionAlreadySettled_TransactionAlreadySettledException()
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

        $stubRepository->method('getTransactionByExtID')
            ->willReturn((object) [
                'trx_id' => 'testTransactionID',
                'updated_at' => '2021-01-01 00:00:00'
            ]);

        $service = $this->makeService(repository: $stubRepository, credentials: $stubCredentials,);
        $service->settle(request: $request);
    }

    public function test_settle_mockRepository_createTransaction()
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

        $mockRepository->method('getTransactionByExtID')
            ->willReturn(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
            );

        $mockRepository->expects($this->once())
            ->method('createTransaction')
            ->with(
                extID: 'payout-testTransactionID',
                playID: 'playid',
                username: 'username',
                currency: 'IDR',
                gameCode: 'gameCode',
                betAmount: 0,
                winloseAmount: 100,
                transactionDate: '2024-01-01 00:00:00',
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
            walletReport: $stubReport,
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

        $stubRepository->method('getTransactionByExtID')
            ->willReturn(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
            );

        $mockReport = $this->createMock(WalletReport::class);
        $mockReport->expects($this->once())
            ->method('makeSlotReport')
            ->with(
                extID: $request->txn_id,
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
            walletReport: $mockReport,
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

        $stubRepository->method('getTransactionByExtID')
            ->willReturn(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
            );

        $stubReport = $this->createMock(WalletReport::class);
        $stubReport->method('makeSlotReport')
            ->willReturn(new Report);

        $mockWallet = $this->createMock(IWallet::class);
        $mockWallet->expects($this->once())
            ->method('payout')
            ->with(
                credentials: $providerCredentials,
                playID: 'playid',
                currency: 'IDR',
                extID: "payout-{$request->txn_id}",
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
            walletReport: $stubReport,
            wallet: $mockWallet
        );

        $service->settle(request: $request);
    }

    public function test_settle_stubWalletInvalidStatus_WalletErrorException()
    {
        $this->expectException(WalletException::class);

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

        $stubRepository->method('getTransactionByExtID')
            ->willReturn(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
            );

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
            walletReport: $stubReport,
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

        $stubRepository->method('getTransactionByExtID')
            ->willReturn(
                (object) [
                    'ext_id' => 'testTransactionID',
                    'updated_at' => null,
                    'bet_amount' => 100,
                    'play_id' => 'playid',
                    'username' => 'username',
                    'currency' => 'IDR',
                    'game_code' => 'gameCode',
                ],
                null
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
            repository: $stubRepository,
            credentials: $stubCredentials,
            walletReport: $stubReport,
            wallet: $stubWallet
        );

        $response = $service->settle(request: $request);

        $this->assertSame(expected: $expectedData, actual: $response);
    }
}
