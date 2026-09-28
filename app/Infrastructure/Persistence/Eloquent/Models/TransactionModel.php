<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class TransactionModel extends Model
{
    public $incrementing = false;

    protected $table = 'transactions';

    protected $fillable = [
        'id',
        'company_id',
        'card_id',
        'authorization_id',
        'event_id',
        'type',
        'amount_cents',
        'occurred_at',
        'limit_month',
        'reference',
    ];

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
            'limit_month' => 'date',
        ];
    }
}
