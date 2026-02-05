<?php

use App\Http\Controllers\BalanceController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\MerchantController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\Operations\SendMoneyController;
use App\Http\Controllers\Operations\TopUpController;
use App\Http\Controllers\Operations\WithdrawDepositsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class,'index'])->name('index');

Route::middleware(['cabinet'])->group(function (){
    Route::get('/cabinet', [PageController::class, 'cabinet'])->name('cabinet');
    Route::get('/logout', LogoutController::class)->name('logout');
    Route::get('/documentation',[PageController::class,'documentation'])->name('documentation');

    //Registration
    //users
    Route::get('/add-new-user',[PageController::class,'newUser'])->name('new-user');
    Route::post('/create-user',[UserController::class,'createUser'])->name('create-user');
    Route::get('/all-users',[UserController::class,'index'])->name('all-users');


    Route::get('/show-keys',[\App\Http\Controllers\ShowKeysController::class,'form'])->name('show-keys');
    Route::post('/show-keys',[\App\Http\Controllers\ShowKeysController::class,'show'])->name('show-form');

    //merchants
    Route::get('/add-new-merchant',[MerchantController::class,'createMerchantForm'])->name('new-merchant');
    Route::post('/create-merchant',[MerchantController::class,'createMerchant'])->name('create-merchant');
    Route::get('/my-merchants',[MerchantController::class,'myMerchants'])->name('my-merchants');
    Route::get('/all-merchants',[MerchantController::class,'allMerchants'])->name('all-merchants');
    Route::get('/all-merchant-transactions',[MerchantController::class,'transcationHistory'])->name('all-merchant-transactions');
    Route::get('/all-merchant-deposits',[MerchantController::class,'allDeposits'])->name('all-deposits');
    Route::post('/withdraw-deposits',[WithdrawDepositsController::class,'withdrawDeposits'])->name('withdraw.deposits');


    //operations
    Route::get('/operations',[OperationController::class,'index'])->name('operations');
    Route::get('/currencies',[OperationController::class,'currencies'])->name('currencies');
    Route::get('my-wallets',[OperationController::class,'myWallets'])->name('my-wallets');
    Route::get('/commission',[OperationController::class,'commission'])->name('commission');
    Route::get('/wallet-balance',[OperationController::class,'walletBalance'])->name('wallet-balance');

    Route::get('cheklogs', function () {
        if (is_writable(storage_path('logs'))) {
            echo "Storage/logs доступен для записи";
        } else {
            echo "Нет доступа к storage/logs!";
        }

        // Пробуем записать тестовый лог
        \Log::info('Проверка логирования через /cheklogs', ['time' => now()]);
    });


    Route::get('/topup-form',[OperationController::class,'topupForm'])->name('topup-form');
    Route::post('/top-up-balance',[TopUpController::class,'topupBalance'])->name('top-up-balance');

    Route::get('/wallet/{id}/money/send',[SendMoneyController::class,'form'])->name('send-form');
    Route::get('/money/send/{currency}',[SendMoneyController::class,'sendMoney'])->name('send-money');

    Route::get('check-transactions',[OperationController::class,'checkTransactions'])->name('check-transactions');


    //balance
    Route::get('/all-users-balance',[BalanceController::class,'allUsersBalance'])->name('all-users-balance');
    Route::get('/all-merchants-balance',[BalanceController::class,'allMerchantsBalance'])->name('all-merchants-balance');
    Route::get('/my-balance',[BalanceController::class,'myBalance'])->name('my-balance');



});


//Route::get('/mail-test',function (){
//    return view('mailtest');
//});

