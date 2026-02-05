<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'id',
        'network',
        'wallet_from',
        'wallet_to',
        'sum',
        'currency_id',
    ];
}
