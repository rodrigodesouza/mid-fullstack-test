<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

final class CardMccRuleModel extends Model
{
    protected $table = 'card_mcc_rules';

    protected $fillable = [
        'card_id',
        'mcc',
        'rule',
        'tolerance_percent',
    ];

    protected function casts(): array
    {
        return [
            'card_id' => 'integer',
            'tolerance_percent' => 'integer',
        ];
    }
}
