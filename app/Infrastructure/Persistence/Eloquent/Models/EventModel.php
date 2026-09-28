<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class EventModel extends Model
{
    public $incrementing = false;

    protected $table = 'events';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'external_id',
        'authorization_id',
        'authorization_reference',
        'type',
        'amount_cents',
        'currency',
        'sequence',
        'final',
        'occurred_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'sequence' => 'integer',
            'final' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
