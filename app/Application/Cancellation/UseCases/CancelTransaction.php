<?php

declare(strict_types=1);

namespace App\Application\Cancellation\UseCases;

use App\Application\Cancellation\DTO\CancelTransactionInput;
use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Event\Entity\Event;
use App\Domain\Event\Repositories\EventRepository;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;
use DateTimeZone;
use Illuminate\Support\Str;

final readonly class CancelTransaction
{
    public function __construct(
        private AuthorizationRepository $authorizationRepository,
        private TransactionRepository $transactionRepository,
        private EventRepository $eventRepository,
    ) {}

    public function execute(CancelTransactionInput $input): bool
    {
        $existingEvent = $this->eventRepository
            ->findByExternalId($input->externalId);

        // X3 — evento já processado não gera efeito financeiro novamente.
        if ($existingEvent instanceof Event && $existingEvent->status() === 'processed') {
            return true;
        }

        $authorization = $this->authorizationRepository
            ->findByExternalId($input->authorizationId);

        // Decisão: cancellation de authorization desconhecida é aceito e persistido.
        if (! $authorization instanceof Authorization) {
            $event = new Event(
                id: $existingEvent?->id() ?? (string) Str::uuid(),
                externalId: $input->externalId,
                authorizationReference: $input->authorizationId,
                authorizationId: null,
                type: 'cancellation',
                amountCents: null,
                currency: null,
                sequence: null,
                final: true,
                occurredAt: $input->occurredAt,
                status: 'processed',
            );

            if ($existingEvent instanceof Event) {
                $this->eventRepository->update($event);
            } else {
                $this->eventRepository->save($event);
            }

            return true;
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
            status: 'processed',
        );

        if ($existingEvent instanceof Event) {
            $this->eventRepository->update($event);
        } else {
            $this->eventRepository->save($event);
        }

        // Libera somente o valor ainda reservado.
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
                limitMonth: $authorization
                    ->occurredAt()
                    ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
                    ->format('Y-m'),
                reference: $input->externalId,
            );

            $this->transactionRepository->save($release);
        }

        return true;
    }
}
