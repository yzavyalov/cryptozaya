<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merchanttransaction extends Model
{
    protected $table = 'merchant_transactions';

    protected $fillable = [
        'merchant_id',
        'type_transactions',
        'status',
        'network',
        'wallet_from',
        'wallet_to',
        'merchant_system_user_id',
        'merchant_system_transaction_id',
        'sum',
        'currency_id',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
