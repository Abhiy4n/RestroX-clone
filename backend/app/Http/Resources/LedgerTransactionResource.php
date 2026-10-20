<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'type' => $this->type,
            'status' => $this->status,
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'direction' => $this->when(
                $this->resource->getAttribute('wallet_direction') !== null,
                fn (): string => (string) $this->resource->getAttribute('wallet_direction'),
            ),
            'posted_at' => $this->posted_at,
        ];
    }
}
