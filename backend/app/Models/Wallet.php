<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
   protected $fillable = [
    'user_id',
    'balance',
    'currency',
   ];

   protected function casts(): array
   {
     return [
        'balance'=> 'decimal:2',
     ];
   }
   public function user(): BelongsTo
   {
    return $this->belongsTo(User::class);
   }
}
