<?php

namespace App\Models;

use App\Http\Enums\MerchantWalletStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    protected $fillable = [
        'name',
        'status',
        'token',
        'cburl'
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class,'merchant_user','merchant_id','user_id');
    }


    public function balances(): HasMany
    {
        return $this->hasMany(MerchantBalance::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(MerchantWallet::class);
    }

    public function mainWallet()
    {
        return $this->wallets()->where('status', MerchantWalletStatusEnum::MAIN)->first();
    }

    public function withDrawWallet()
    {
        return $this->wallets()->where('status',MerchantWalletStatusEnum::WITHDRAW)->first();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Merchanttransaction::class);
    }
}
