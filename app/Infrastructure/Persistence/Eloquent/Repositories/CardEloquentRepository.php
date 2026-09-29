<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Card\Entity\Card;
use App\Domain\Card\Enums\CardStatusEnum;
use App\Domain\Card\Repositories\CardRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Persistence\Eloquent\Models\CardModel;
use DateTimeImmutable;

final class CardEloquentRepository implements CardRepository
{
    public function findByToken(string $token): ?Card
    {
        $card = CardModel::query()
            ->with('mccRules')
            ->where('card_token', $token)
            ->first();

        if ($card === null) {
            return null;
        }

        return new Card(
            id: $card->id,
            userId: $card->user_id,
            cardToken: $card->card_token,
            monthlyLimitCents: Money::fromCents($card->monthly_limit_cents),
            status: CardStatusEnum::from($card->status),
            createdAt: new DateTimeImmutable($card->created_at->toISOString()),
            updatedAt: new DateTimeImmutable($card->updated_at->toISOString()),
            purchaseLimitCents: $card->purchase_limit_cents !== null
                ? Money::fromCents($card->purchase_limit_cents)
                : null,
            mccRules: $card->mccRules
                ->map(static fn ($rule): array => [
                    'mcc' => $rule->mcc,
                    'rule' => $rule->rule,
                    'tolerance_percent' => $rule->tolerance_percent,
                ])
                ->all(),
        );
    }
}
