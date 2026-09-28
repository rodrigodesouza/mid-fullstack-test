<?php

declare(strict_types=1);

namespace App\Application\Cancellation\UseCases;

use App\Application\Cancellation\DTO\CancelTransactionInput;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Event\Entity\Event;
use App\Domain\Event\Repositories\EventRepository;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;
use Illuminate\Support\Str;

final class CancelTransaction
{
    public function __construct(
        private readonly AuthorizationRepository $authorizationRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly EventRepository $eventRepository,
    ) {}

    public function execute(CancelTransactionInput $input): bool
    {
        // X3 — Evento já processado não pode produzir novo efeito financeiro.
        $existingEvent = $this->eventRepository
            ->findByExternalId($input->externalId);

        if ($existingEvent !== null && $existingEvent->status() !== 'pending') {
            return true;
        }

        $authorization = $this->authorizationRepository
            ->findByExternalId($input->authorizationId);

        if ($authorization === null) {
            return false;
        }

        $event = new Event(
            id: $existingEvent?->id() ?? (string) Str::uuid(),
            externalId: $input->externalId,
            authorizationReference: $input->authorizationId,
            authorizationId: $authorization->id(),
            type: 'cancellation',
            amountCents: null,
            currency: $authorization->currency(),
            sequence: null,
            final: true,
            occurredAt: $input->occurredAt,
            status: 'pending',
        );

        if ($existingEvent === null) {
            $this->eventRepository->save($event);
        }

        $captured = $this->transactionRepository
            ->capturedAmountForAuthorization($authorization->id());

        $remaining = $authorization->amount()->subtract($captured);

        if (! $remaining->isZero()) {
            $release = new Transaction(
                id: (string) Str::uuid(),
                companyId: $authorization->companyId(),
                cardId: $authorization->cardId(),
                authorizationId: $authorization->id(),
                eventId: $event->id(),
                type: TransactionTypeEnum::RELEASE,
                amount: $remaining,
                occurredAt: $input->occurredAt,
                limitMonth: $input->occurredAt->format('Y-m'),
                reference: $input->externalId,
            );

            $this->transactionRepository->save($release);
        }

        $processedEvent = new Event(
            id: $event->id(),
            externalId: $event->externalId(),
            authorizationReference: $event->authorizationReference(),
            authorizationId: $authorization->id(),
            type: $event->type(),
            amountCents: $event->amountCents(),
            currency: $event->currency(),
            sequence: $event->sequence(),
            final: $event->isFinal(),
            occurredAt: $event->occurredAt(),
            status: 'processed',
        );

        $this->eventRepository->update($processedEvent);

        return true;
    }
}
