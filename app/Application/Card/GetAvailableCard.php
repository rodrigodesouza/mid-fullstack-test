<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Card\Entity\Card;
use App\Domain\Card\Repositories\CardLimitsRepository;
use App\Domain\Card\Repositories\CardRepository;
use App\Domain\Company\Repositories\CompanyBalanceRepository;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use RuntimeException;

final readonly class GetAvailableCard
{
    public function __construct(
        private CardRepository $cards,
        private CardLimitsRepository $limits,
        private CompanyBalanceRepository $companies,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $cardToken): array
    {
        $card = $this->cards->findByToken($cardToken);

        throw_if(! $card instanceof Card, RuntimeException::class, 'Card not found.');

        $user = UserModel::query()
            ->whereKey($card->userId())
            ->firstOrFail();

        $month = now('America/Sao_Paulo')->format('Y-m');

        $limitRemaining = $this->limits
            ->remainingForMonth($card->id(), $month)
            ->toCents();

        $companyBalance = $this->companies
            ->availableBalanceFor($user->company_id)
            ->toCents();

        return [
            'available_cents' => min(
                $limitRemaining,
                $companyBalance,
            ),
            'limit_remaining_cents' => $limitRemaining,
        ];
    }
}
