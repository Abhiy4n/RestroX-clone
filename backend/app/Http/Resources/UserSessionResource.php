<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Decides which details of one login session (one device) are sent to the frontend.
 *
 * Wraps a row from the `sessions` table.
 *
 * @property string $id
 * @property ?string $ip_address
 * @property ?string $user_agent
 * @property int $last_activity
 */
class UserSessionResource extends JsonResource
{
    /**
     * The session details the frontend receives.
     *
     * The real session ID is the login cookie itself. Anyone who had it would be logged in
     * as this user, so we only send a hash of it, which can't be turned back into the ID.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => hash('sha256', $this->id),
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'last_active_at' => Carbon::createFromTimestamp($this->last_activity)->toIso8601String(),
            'is_current_device' => $request->hasSession() && hash_equals($this->id, $request->session()->getId()),
        ];
    }
}
