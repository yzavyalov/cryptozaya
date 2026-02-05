<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function merchants(): BelongsToMany
    {
        return $this->belongsToMany(Merchant::class,'merchant_user','user_id','merchant_id');
    }


    public function balances()
    {
        return $this->hasMany(UserBalance::class);
    }

    public function merchantBalances()
    {
        return MerchantBalance::query()
            ->join('merchants', 'merchants.id', '=', 'merchant_balances.merchant_id')
            ->join('merchant_user', 'merchant_user.merchant_id', '=', 'merchants.id')
            ->where('merchant_user.user_id', $this->id)
            ->select('merchant_balances.*');
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }
}
