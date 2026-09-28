<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;
use App\Infrastructure\Persistence\Eloquent\Models\AuthorizationModel;
use App\Infrastructure\Persistence\Eloquent\Models\CardModel;
use App\Infrastructure\Persistence\Eloquent\Models\CompanyModel;
use App\Infrastructure\Persistence\Eloquent\Models\TransactionModel;
use Illuminate\Support\Facades\DB;

final class TransactionEloquentRepository implements TransactionRepository
{
    public function save(Transaction $transaction): void
    {
        TransactionModel::query()->create(
            $this->toPersistence($transaction)
        );
    }

    public function reserve(
        Authorization $authorization,
        Transaction $transaction,
    ): bool {
        return DB::transaction(function () use ($authorization, $transaction): bool {
            /*
             * A10 — Serializa reservas concorrentes através dos registros
             * da empresa e do cartão antes de verificar os limites.
             */
            CompanyModel::query()
                ->whereKey($transaction->companyId())
                ->lockForUpdate()
                ->firstOrFail();

            CardModel::query()
                ->whereKey($transaction->cardId())
                ->lockForUpdate()
                ->firstOrFail();

            $companyBalance = (int) TransactionModel::query()
                ->where('company_id', $transaction->companyId())
                ->sum('amount_cents');

            $cardLimit = (int) CardModel::query()
                ->whereKey($transaction->cardId())
                ->value('monthly_limit_cents');

            $usedInMonth = (int) TransactionModel::query()
                ->where('card_id', $transaction->cardId())
                ->where('limit_month', $transaction->limitMonth().'-01')
                ->sum('amount_cents');

            $cardRemaining = $cardLimit + $usedInMonth;
            $amount = $transaction->amount()->toCents();

            if ($companyBalance + $amount < 0) {
                return false;
            }

            if ($cardRemaining + $amount < 0) {
                return false;
            }

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

            TransactionModel::query()->create(
                $this->toPersistence($transaction)
            );

            return true;
        });
    }

    public function capturedAmountForAuthorization(string $authorizationId): Money
    {
        $amount = TransactionModel::query()
            ->where('authorization_id', $authorizationId)
            ->where('type', TransactionTypeEnum::CAPTURE->value)
            ->sum('amount_cents');

        return Money::fromCents(abs((int) $amount));
    }

    public function hasFinalCapture(string $authorizationId): bool
    {
        return false;
    }

    /**
     * Converts the immutable domain transaction into its persistence representation.
     */
    private function toPersistence(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id(),
            'company_id' => $transaction->companyId(),
            'card_id' => $transaction->cardId(),
            'authorization_id' => $transaction->authorizationId(),
            'event_id' => $transaction->eventId(),
            'type' => $transaction->type()->value,
            'amount_cents' => $transaction->amount()->toCents(),
            'occurred_at' => $transaction->occurredAt(),
            'limit_month' => $transaction->limitMonth() !== null
                ? $transaction->limitMonth().'-01'
                : null,
            'reference' => $transaction->reference(),
        ];
    }
}
