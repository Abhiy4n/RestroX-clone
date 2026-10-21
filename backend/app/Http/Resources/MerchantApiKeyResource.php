<?php

namespace App\Http\Resources;

use App\Models\MerchantApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantApiKeyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->key_id,
            'merchant_id' => $this->merchant->code,
            'merchant_name' => $this->merchant->name,
            'name' => $this->name,
            'key_hint' => $this->secret_hint,
            'status' => $this->status(),
            'last_used_at' => $this->last_used_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function status(): string
    {
        /** @var MerchantApiKey $key */
        $key = $this->resource;

        if ($key->revoked_at !== null) {
            return 'revoked';
        }

        if ($key->expires_at?->isPast()) {
            return 'expired';
        }

        return 'active';
    }
}
