<?php

declare(strict_types=1);

namespace App\Domain\Card\Repositories;

use App\Domain\Shared\ValueObjects\Money;

interface CardLimitsRepository
{
    public function purchaseLimitFor(int $cardId): Money;

    public function remainingForMonth(
        int $cardId,
        string $month,
    ): Money;

    public function reserve(
        int $cardId,
        Money $amount,
    ): void;
}
