<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Company\Repositories\CompanyBalanceRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;

final class CompanyEloquentRepository implements CompanyBalanceRepository
{
    public function availableBalanceFor(int $companyId): Money
    {
        $balance = TransactionModel::query()
            ->where('company_id', $companyId)
            ->sum('amount_cents');

        return Money::fromCents((int) $balance);
    }

    public function reserve(
        int $companyId,
        Money $amount,
    ): void {}
}
