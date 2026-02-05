<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = [
        'id',
        'user_id',
        'number',
        'hex',
        'network',
        'publicKey',
        'privateKey',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function walletBalance()
    {
        return $this->hasMany(WalletBalance::class,'wallet_id','id');
    }
}
