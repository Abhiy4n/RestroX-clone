<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ledgerAccount extends Model
{
  protected $fillable = [
        'code',
        'wallet_id',
        'name',
        'type',
        'normal_side',
        'currency',
  ];
  public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
