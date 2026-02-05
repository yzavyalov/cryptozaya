<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantBalance extends Model
{
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
