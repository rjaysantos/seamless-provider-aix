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
use Providers\Aix\Exceptions\WalletErrorException as ProviderWalletException;
use App\Exceptions\Casino\WalletErrorException;
use Providers\Aix\Exceptions\InsufficientFundException;
use Providers\Aix\Exceptions\PlayerNotFoundException;
use Providers\Aix\Exceptions\InvalidSecretKeyException;
use Providers\Aix\Exceptions\TransactionAlreadyExistsException;

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

    public function test_bet_mockRepository_getPlayerByPlayID()
    {
        $request = new Request([
            'user_id' => 12345,
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
                'play_id' => 'testPlayID'
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
            'user_id' => 12345,
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

    public function test_bet_mockRepository_getTransactionByTrxID()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $mockRepository = $this->createMock(AixRepository::class);
        $mockRepository->expects($this->once())
            ->method('getTransactionByTrxID')
            ->with(trxID: $request->txn_id)
            ->willReturn(null);

        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'currency' => 'IDR',
                'play_id' => 'testPlayID'
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
            'user_id' => 12345,
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
                'play_id' => 'testPlayID'
            ]);

        $stubRepository->method('getTransactionByTrxID')
            ->willReturn((object) [
                'trx_id' => 'testTxnID'
            ]);

        $service = $this->makeService(repository: $stubRepository);
        $service->bet(request: $request);
    }

    public function test_bet_mockCredentials_getCredentialsByCurrency()
    {
        $request = new Request([
            'user_id' => 12345,
            'amount' => 1000.0,
            'prd_id' => 1,
            'txn_id' => 'testTxnID',
            'round_id' => 'testRoundID',
            'debit_time' => '2025-01-01 00:00:00'
        ]);

        $request->headers->set('secret-key', 'testSecretKey');

        $stubRepository = $this->createMock(AixRepository::class);
        $stubRepository->method('getTransactionByTrxID')
            ->willReturn(null);

        $stubRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'currency' => 'IDR',
                'play_id' => 'testPlayID'
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
            'user_id' => 12345,
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
        $service->bet(request: $request);
    }

    public function test_bet_mockWallet_balance()
    {
        $request = new Request([
            'user_id' => 12345,
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
                'play_id' => 'testPlayID'
            ]);

        $stubRepository->method('getTransactionByTrxID')
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
                playID: 'testPlayID'
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

    public function test_bet_balanceWalletStatusCodeNot2100_ProviderWalletException()
    {
        $this->expectException(ProviderWalletException::class);

        $request = new Request([
            'user_id' => 12345,
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
            'user_id' => 12345,
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

    public function test_bet_mockRepository_createTransction()
    {
        $request = new Request([
            'user_id' => 12345,
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
                transactionID: $request->txn_id, 
                betAmount: $request->amount, 
                transactionDate: '2025-01-01 00:00:00'
            );

        $mockRepository->method('getPlayerByPlayID')
            ->willReturn((object) [
                'play_id' => '12345',
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
            'user_id' => 12345,
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

        $mockWalletReport = $this->createMock(WalletReport::class);
        $mockWalletReport->expects($this->once())
            ->method('makeSlotReport')
            ->with(
                transactionID: $request->txn_id,
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
            'user_id' => 12345,
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
            ->method('wager')
            ->with(
                credentials: $credentials,
                playID: '12345',
                currency: 'IDR',
                transactionID: $request->txn_id,
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
        $this->expectException(ProviderWalletException::class);

        $request = new Request([
            'user_id' => 12345,
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
            'user_id' => 12345,
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
            repository: $stubRepository, 
            credentials: $stubCredentials, 
            wallet: $stubWallet,
            walletReport: $stubWalletReport
        );
        
        $result = $service->bet(request: $request);

        $this->assertSame(expected: $expected, actual: $result);
    }
}
