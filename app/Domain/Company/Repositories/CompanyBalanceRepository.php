<?php

declare(strict_types=1);

namespace App\Domain\Company\Repositories;

use App\Domain\Shared\ValueObjects\Money;

interface CompanyBalanceRepository
{
    public function availableBalanceFor(int $companyId): Money;

    public function reserve(
        int $companyId,
        Money $amount,
    ): void;
}
