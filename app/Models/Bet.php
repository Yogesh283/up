<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bet extends Model
{
    protected $fillable = [
        'user_id',
        'draw_id',
        'draw_name',
        'numbers',
        'amount',
        'status',
        'prize',
        'draw_at',
    ];

    protected function casts(): array
    {
        return [
            'numbers' => 'array',
            'amount' => 'decimal:2',
            'prize' => 'decimal:2',
            'draw_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
