<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CardModel extends Model
{
    protected $table = 'cards';

    protected $fillable = [
        'user_id',
        'card_token',
        'monthly_limit_cents',
        'purchase_limit_cents',
        'status',
    ];

    protected $casts = [
        'monthly_limit_cents' => 'integer',
        'purchase_limit_cents' => 'integer',
    ];

    public function mccRules(): HasMany
    {
        return $this->hasMany(CardMccRuleModel::class, 'card_id');
    }
}
