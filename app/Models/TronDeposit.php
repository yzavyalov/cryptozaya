<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TronDeposit extends Model
{
    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'amount',
        'token',
        'confirmed',
    ];
}
