<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;

class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const INTEGRATION_WEBSITE = 'website';

    public const INTEGRATION_MOBILE_APP = 'mobile_app';

    public const INTEGRATION_SERVER_API = 'server_api';

    protected $fillable = [
        'name',
        'website_url',
        'integration_type',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
        'mode' => 'sandbox',
        'integration_type' => 'server_api',
    ];

    protected static function booted(): void
    {
        static::creating(function (Merchant $merchant): void {
            $merchant->code ??= 'mer_'.Str::lower((string) Str::ulid());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the integration credentials issued for this merchant.
     *
     * @return HasMany<MerchantApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(MerchantApiKey::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function settlementWallet(): HasOneThrough
    {
        return $this->hasOneThrough(
            Wallet::class,
            User::class,
            'id',
            'user_id',
            'user_id',
            'id',
        );
    }
}
