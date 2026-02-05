<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Enums\BlockChainEnum;
use App\Http\Requests\TopUpBalanceRequest;
use App\Services\ChaingatewayService;
use App\Services\EncodeService;
use App\Services\Operations\BalanceService;
use App\Services\Operations\CurrencyService;
use App\Services\Operations\TransactionService;
use App\Services\Tron\TronService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


class TopUpController extends Controller
{
    public function __construct(ChaingatewayService $chaingatewayService,
                                WalletService $walletService,
                                TronService $tronService,
                                BalanceService $balanceService)
    {
        $this->chaingatewayService = $chaingatewayService;

        $this->walletService = $walletService;

        $this->tronService = $tronService;

        $this->balanceService = $balanceService;
    }

    public function topupBalance(TopUpBalanceRequest $request)
    {
        $data = $request->validated();

        $blockchain = BlockChainEnum::from($data['blockchain'])->name;
        $amount = $data['amount'];

        $wallets = $this->walletService->checkUserWallet($blockchain);

        // Если кошельков нет — создаём один
        if (!$wallets) {

            if ($blockchain === 'tron') {
                $newWallet = $this->tronService->createWallet();

                $createdWallet = $this->walletService->createWallet(
                    $blockchain,
                    $newWallet['address']['base58'],
                    $newWallet['address']['hex'],
                    Auth::id(),
                    $newWallet['publicKey'],
                    $newWallet['privateKey']
                );

                // Обернём обратно в коллекцию
                $wallets = collect([$createdWallet]);
            }
        }

        //Если нет, то создаем баланс
        if (!$this->balanceService->userBalanceCurrency(Auth::id(), CurrencyService::tronToken('USDT')))
            $this->balanceService->create(Auth::id(),CurrencyService::tronToken('USDT'));


        // Генерация QR для каждого кошелька
        $walletsWithQr = $wallets->map(function ($wallet) {
            return [
                'wallet' => $wallet,
                'qr' => QrCode::size(100)->generate($wallet->number),
            ];
        });

        return view('cabinet.operations.topup-result', [
            'wallets' => $walletsWithQr,
            'amount' => $amount,
        ]);
    }

}
