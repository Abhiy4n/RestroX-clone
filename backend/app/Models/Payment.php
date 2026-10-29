<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'public_id',
        'merchant_reference',
        'amount_minor',
        'currency',
        'description',
        'status',
        'checkout_token_hash',
        'idempotency_key_hash',
        'return_url',
        'cancel_url',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->public_id ??= 'pay_'.Str::lower((string) Str::ulid());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Merchant, $this> */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LedgerTransaction, $this> */
    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class);
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
