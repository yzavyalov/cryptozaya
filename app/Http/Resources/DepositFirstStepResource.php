<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepositFirstStepResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'address' => $this['address'],
            'qr' => (string)$this['qr'],
            'user_id' => $this['user_id'],
            'transaction_id' => $this['transaction_id'],
            'token' => $this['token'],
            'amount' => $this['amount'],
        ];
    }
}
