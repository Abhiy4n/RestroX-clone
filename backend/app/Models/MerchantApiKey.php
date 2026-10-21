<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\MerchantApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MerchantApiKey extends Model
{
    /** @use HasFactory<MerchantApiKeyFactory> */
    use HasFactory;

    protected $fillable = [
        'key_id',
        'name',
        'secret_hash',
        'secret_hint',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    /**
     * Issue a key and return its plaintext secret exactly once to the caller.
     *
     * @return array{key: self, secret: string}
     */
    public static function issueFor(Merchant $merchant, string $name, ?CarbonInterface $expiresAt = null): array
    {
        $secret = 'rsk_test_'.Str::random(48);
        $key = $merchant->apiKeys()->create([
            'key_id' => 'key_'.Str::lower((string) Str::ulid()),
            'name' => $name,
            'secret_hash' => hash('sha256', $secret),
            'secret_hint' => Str::substr($secret, 0, 13).'…'.Str::substr($secret, -4),
            'expires_at' => $expiresAt,
        ]);

        return ['key' => $key, 'secret' => $secret];
    }

    public function getRouteKeyName(): string
    {
        return 'key_id';
    }

    /**
     * Get the merchant that owns this credential.
     *
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture())
            && $this->merchant->status === Merchant::STATUS_ACTIVE;
    }

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
