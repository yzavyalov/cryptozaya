<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantWallet extends Model
{
    protected $fillable = [
        'merchant_user_id',
        'merchant_transaction_id',
        'merchant_id',
        'number',
        'hex',
        'network',
        'public_key',
        'private_key',
        ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
