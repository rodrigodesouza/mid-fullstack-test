<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class AuthorizationModel extends Model
{
    public $incrementing = false;

    protected $table = 'authorizations';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'external_id',
        'card_id',
        'company_id',
        'amount_cents',
        'currency',
        'mcc',
        'decision',
        'reason',
        'merchant_name',
        'merchant_city',
        'merchant_country',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
