<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Decides which user fields are sent to the frontend.
 */
class UserResource extends JsonResource
{
    /**
     * The user fields the frontend receives.
     *
     * Only the fields listed here are sent. Anything else on the user, like the password
     * hash or is_active, stays private, even if new columns are added later.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_country_iso2' => $this->phone_country_iso2,
            'phone_e164' => $this->phone_e164,
            'email_verified_at' => $this->email_verified_at,
        ];
    }
}
