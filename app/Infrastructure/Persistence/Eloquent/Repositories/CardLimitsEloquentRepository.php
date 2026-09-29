<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Card\Repositories\CardLimitsRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Persistence\Eloquent\Models\CardModel;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;

final class CardLimitsEloquentRepository implements CardLimitsRepository
{
    public function purchaseLimitFor(int $cardId): ?Money
    {
        $limit = CardModel::query()
            ->whereKey($cardId)
            ->value('purchase_limit_cents');

        return $limit === null
            ? null
            : Money::fromCents((int) $limit);
    }

    public function remainingForMonth(
        int $cardId,
        string $month,
    ): Money {
        $cardLimit = CardModel::query()
            ->whereKey($cardId)
            ->value('monthly_limit_cents');

        $used = TransactionModel::query()
            ->where('card_id', $cardId)
            ->where('limit_month', $month.'-01')
            ->sum('amount_cents');

        return Money::fromCents(
            (int) $cardLimit + (int) $used
        );
    }

    public function reserve(
        int $cardId,
        Money $amount,
    ): void {}
}
