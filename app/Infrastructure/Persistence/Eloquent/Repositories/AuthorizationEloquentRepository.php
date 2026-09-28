<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Enums\AuthorizationReasonEnum;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Infrastructure\Persistence\Eloquent\Models\AuthorizationModel;
use DateTimeImmutable;

final class AuthorizationEloquentRepository implements AuthorizationRepository
{
    public function findByExternalId(string $externalId): ?Authorization
    {
        $model = AuthorizationModel::query()
            ->where('external_id', $externalId)
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function save(Authorization $authorization): void
    {
        AuthorizationModel::query()->create([
            'id' => $authorization->id(),
            'external_id' => $authorization->externalId(),
            'card_id' => $authorization->cardId(),
            'company_id' => $authorization->companyId(),
            'amount_cents' => $authorization->amount()->toCents(),
            'currency' => $authorization->currency(),
            'mcc' => $authorization->mcc(),
            'decision' => $authorization->decision()->value,
            'reason' => $authorization->reason()?->value,
            'merchant_name' => $authorization->merchantName(),
            'merchant_city' => $authorization->merchantCity(),
            'merchant_country' => $authorization->merchantCountry(),
            'occurred_at' => $authorization->occurredAt(),
        ]);
    }

    private function toDomain(AuthorizationModel $model): Authorization
    {
        return new Authorization(
            id: $model->id,
            externalId: $model->external_id,
            cardId: $model->card_id,
            companyId: $model->company_id,
            amount: Money::fromCents($model->amount_cents),
            currency: $model->currency,
            mcc: $model->mcc,
            decision: AuthorizationDecisionEnum::from($model->decision),
            reason: $model->reason !== null
                ? AuthorizationReasonEnum::from($model->reason)
                : null,
            merchantName: $model->merchant_name,
            merchantCity: $model->merchant_city,
            merchantCountry: $model->merchant_country,
            occurredAt: new DateTimeImmutable($model->occurred_at->toISOString()),
        );
    }
}
