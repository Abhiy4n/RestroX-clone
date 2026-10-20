<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Merchant extends Model
{
    protected $fillable = [
        'name',
        'code',
        'website_url',
    ];

    protected $attributes = [
        'status' => 'active',
        'mode' => 'sandbox',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
