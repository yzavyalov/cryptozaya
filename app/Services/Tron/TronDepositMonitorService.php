<?php

namespace App\Services\Tron;

use App\Models\TronDeposit;
use App\Models\Wallet;
use App\Services\Operations\BalanceService;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\TransactionService;

class TronDepositMonitorService
{
    public function __construct(
        protected TronService $tron,
        protected TransactionService $transactionService,
        protected BalanceService $balanceService
    ) {}

    public function scanWallet(Wallet $wallet)
    {
        // 1) Проверяем TRC20 (USDT, USDC, и другие)
        $trc20 = $this->tron->getTRC20Transactions($wallet->number);

        if (!empty($trc20['data'])) {
            $this->processTRC20($wallet, $trc20['data']);
        }

        // 2) Проверяем TRX
        $trx = $this->tron->getTRXTransactions($wallet->number);

        if (!empty($trx['data'])) {
            $this->processTRX($wallet, $trx['data']);
        }
    }

    /**
     * Обработка TRC20 (USDT, USDC, ...)
     */
    private function processTRC20(Wallet $wallet, array $txs)
    {
        foreach ($txs as $tx) {

            // Кошелёк должен быть получателем
            if (($tx['to'] ?? '') !== $wallet->number) {
                continue;
            }

            // Уже обработана?
            if (TronDeposit::where('transaction_id', $tx['transaction_id'])->exists()) {
                continue;
            }

            // Имя токена (USDT, USDC, ...)
            $token = $tx['token_info']['symbol'] ?? 'UNKNOWN';

            // Децималы токена
            $decimals = $tx['token_info']['decimals'] ?? 6;
            $amount = $tx['value'] / (10 ** $decimals);

            TronDeposit::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $tx['transaction_id'],
                'amount' => $amount,
                'token' => $token,
                'confirmed' => true,
            ]);

            // создаём запись транзакции
            $this->transactionService->create(
                $wallet->network,
                $tx['from'],
                $tx['to'],
                $amount,
                CurrencyService::tronToken($token),
            );

            // пополняем баланс
            $this->balanceService->topup(
                $wallet->user_id,
                $amount,
                CurrencyService::tronToken($token),
            );
        }
    }

    /**
     * Обработка TRX
     */
    private function processTRX(Wallet $wallet, array $txs)
    {
        foreach ($txs as $tx) {
            if (($tx['to_address'] ?? '') !== $wallet->number) {
                continue;
            }

            if (TronDeposit::where('transaction_id', $tx['transaction_id'])->exists()) {
                continue;
            }

            $amount = $tx['value'] / 1e6;

            TronDeposit::create([
                'wallet_id' => $wallet->id,
                'transaction_id' => $tx['transaction_id'],
                'amount' => $amount,
                'token' => 'TRX',
                'confirmed' => true,
            ]);

            $this->transactionService->create(
                $wallet->network,
                $tx['from'],
                $tx['to'],
                $amount
            );

            $this->balanceService->topup(
                $wallet->user_id,
                $amount,
                'TRX'
            );
        }
    }
}
