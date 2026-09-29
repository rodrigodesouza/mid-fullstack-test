<?php

declare(strict_types=1);

namespace App\Application\Card;

use App\Domain\Card\Entity\Card;
use App\Domain\Card\Repositories\CardRepository;
use App\Domain\Transaction\Repositories\TransactionRepository;
use RuntimeException;

final readonly class GetCardStatement
{
    public function __construct(
        private CardRepository $cards,
        private TransactionRepository $transactions,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $cardToken): array
    {
        $card = $this->cards->findByToken($cardToken);

        throw_if(! $card instanceof Card, RuntimeException::class, 'Card not found.');

        $month = now('America/Sao_Paulo')->format('Y-m');

        $limitRemaining = $card
            ->monthlyLimitCents()
            ->toCents();

        $statement = [];

        foreach ($this->transactions->statementForCard(
            $card->id(),
            $month,
        ) as $transaction) {
            $limitRemaining += $transaction['amount_cents'];

            $statement[] = [
                'occurred_at' => $transaction['occurred_at'],
                'type' => $transaction['type'],
                'amount_cents' => $transaction['amount_cents'],
                'reference' => $transaction['reference'],
                'limit_remaining_after_cents' => $limitRemaining,
            ];
        }

        return [
            'transactions' => $statement,
        ];
    }
}
