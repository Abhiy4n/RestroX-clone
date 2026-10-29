<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->public_id,
            'merchant_id' => $this->merchant->code,
            'merchant_name' => $this->merchant->name,
            'merchant_reference' => $this->merchant_reference,
            'status' => $this->status,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'description' => $this->description,
            'return_url' => $this->return_url,
            'cancel_url' => $this->cancel_url,
            'expires_at' => $this->expires_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
