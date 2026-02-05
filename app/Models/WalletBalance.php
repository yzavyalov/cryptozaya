<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletBalance extends Model
{
    protected $fillable = [
        'wallet_id',
        'currency_id',
        'balance',
    ];


    public function wallet()
    {
        return $this->belongsTo(Wallet::class,'wallet_id','id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class,'currency_id','id');
    }
}
