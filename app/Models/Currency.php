<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'name'
    ];

    public function userBalance()
    {
        return $this->hasOne(UserBalance::class);
    }

    public function walletBalance()
    {
        return $this->hasMany(WalletBalance::class,'currency_id','id');
    }
}
