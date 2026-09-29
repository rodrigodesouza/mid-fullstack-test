<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Company\Repositories\CompanyBalanceRepository;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

final class CompanyBalance extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $user = Auth::user();

        $balance = resolve(CompanyBalanceRepository::class)
            ->availableBalanceFor($user->company_id)
            ->toCents();

        return [
            Stat::make(
                'Saldo disponível',
                'R$ '.number_format(
                    $balance / 100,
                    2,
                    ',',
                    '.',
                ),
            ),
        ];
    }
}
