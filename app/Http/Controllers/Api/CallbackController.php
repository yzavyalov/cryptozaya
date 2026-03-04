<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchanttransaction;
use App\Services\Operations\MerchantWallet\MerchantWebhookService;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function __construct(MerchantWebhookService $merchantWebhookService)
    {
        $this->merchantWebhookService = $merchantWebhookService;
    }


    public function index()
    {
        return view('cabinet.operations.send-callback');
    }


    public function sendCallback($id)
    {
        $merchantTransaction = Merchanttransaction::query()->findOrFail($id);

        $merchant = $merchantTransaction->merchant;

        $response = $this->merchantWebhookService->sendWebhook($merchant, $merchantTransaction->toArray());

        return redirect()->back()->with('callback_response', $response);
    }

}
