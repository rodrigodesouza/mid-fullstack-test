<?php

declare(strict_types=1);

namespace App\Application\Capture\UseCases;

use App\Application\Capture\DTO\CaptureTransactionInput;
use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Authorization\Services\CaptureTolerance;
use App\Domain\Event\Entity\Event;
use App\Domain\Event\Repositories\EventRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;
use DateTimeZone;
use Illuminate\Support\Str;

final readonly class CaptureTransaction
{
    public function __construct(
        private AuthorizationRepository $authorizationRepository,
        private TransactionRepository $transactionRepository,
        private CaptureTolerance $captureTolerance,
        private EventRepository $eventRepository,
    ) {}

    public function execute(CaptureTransactionInput $input): bool
    {
        $existingEvent = $this->eventRepository->findByExternalId(
            $input->externalId,
        );

        /*
         * Evento já processado = operação idempotente.
         */
        if (
            $existingEvent instanceof Event
            && $existingEvent->status() !== 'pending'
        ) {
            return true;
        }

        $authorization = $this->authorizationRepository
            ->findByExternalId($input->authorizationId);

        /*
         * Capture pode chegar antes da authorization.
         *
         * Nesse cenário o evento é persistido como pending e nenhum
         * movimento financeiro é criado.
         */
        if (! $authorization instanceof Authorization) {
            $event = new Event(
                id: $existingEvent?->id() ?? (string) Str::uuid(),
                externalId: $input->externalId,
                authorizationReference: $input->authorizationId,
                authorizationId: null,
                type: 'capture',
                amountCents: $input->amount->toCents(),
                currency: $input->currency,
                sequence: $input->sequence,
                final: $input->final,
                occurredAt: $input->occurredAt,
                status: 'pending',
            );

            if ($existingEvent instanceof Event) {
                $this->eventRepository->update($event);
            } else {
                $this->eventRepository->save($event);
            }

            return true;
        }

        if ($authorization->decision() !== AuthorizationDecisionEnum::APPROVED) {
            return false;
        }

        if ($this->eventRepository->hasFinalCapture($authorization->id())) {
            return false;
        }

        if ($input->currency !== $authorization->currency()) {
            return false;
        }

        $tolerancePercentage = $this->captureTolerance->percentageFor(
            $authorization->mcc(),
        );

        $maximumCaptureAmount = $authorization->amount()->add(
            Money::fromCents(
                intdiv(
                    $authorization->amount()->toCents()
                    * $tolerancePercentage,
                    100,
                ),
            ),
        );

        $capturedAmount = $this->transactionRepository
            ->capturedAmountForAuthorization($authorization->id());

        $totalCapturedAmount = $capturedAmount->add($input->amount);

        if ($totalCapturedAmount->isGreaterThan($maximumCaptureAmount)) {
            return false;
        }

        if ($input->amount->isGreaterThan($maximumCaptureAmount)) {
            return false;
        }

        /*
         * IMPORTANTE:
         *
         * O evento precisa existir no banco ANTES das transactions,
         * pois transactions.event_id possui FK para events.id.
         */
        $eventId = $existingEvent?->id() ?? (string) Str::uuid();

        $event = new Event(
            id: $eventId,
            externalId: $input->externalId,
            authorizationReference: $input->authorizationId,
            authorizationId: $authorization->id(),
            type: 'capture',
            amountCents: $input->amount->toCents(),
            currency: $input->currency,
            sequence: $input->sequence,
            final: $input->final,
            occurredAt: $input->occurredAt,
            status: 'pending',
        );

        if ($existingEvent instanceof Event) {
            $this->eventRepository->update($event);
        } else {
            $this->eventRepository->save($event);
        }

        $saoPauloTimezone = new DateTimeZone('America/Sao_Paulo');

        $authorizationMonth = $authorization
            ->occurredAt()
            ->setTimezone($saoPauloTimezone)
            ->format('Y-m');

        $captureMonth = $input->occurredAt
            ->setTimezone($saoPauloTimezone)
            ->format('Y-m');

        /*
         * Valor da reserva que ainda permanece bloqueado.
         */
        $remainingReservedCents = max(
            0,
            $authorization->amount()->toCents()
            - $capturedAmount->toCents(),
        );

        /*
         * Primeiro liberamos a parte da captura coberta pela reserva.
         */
        $releaseCents = min(
            $input->amount->toCents(),
            $remainingReservedCents,
        );

        if ($releaseCents > 0) {
            $release = new Transaction(
                id: (string) Str::uuid(),
                companyId: $authorization->companyId(),
                cardId: $authorization->cardId(),
                authorizationId: $authorization->id(),
                eventId: $eventId,
                type: TransactionTypeEnum::RELEASE,
                amount: Money::fromCents($releaseCents),
                occurredAt: $input->occurredAt,
                limitMonth: $authorizationMonth,
                reference: $input->externalId,
            );

            $this->transactionRepository->save($release);
        }

        /*
         * Se a captura ocorrer no mesmo mês da autorização, todo o
         * valor pertence ao mês da autorização.
         *
         * Se ocorrer em outro mês, somente o valor ainda coberto pela
         * reserva pertence ao mês da autorização.
         */
        $captureInAuthorizationMonth = $captureMonth === $authorizationMonth
            ? $input->amount->toCents()
            : min(
                $input->amount->toCents(),
                $remainingReservedCents,
            );

        if ($captureInAuthorizationMonth > 0) {
            $capture = new Transaction(
                id: (string) Str::uuid(),
                companyId: $authorization->companyId(),
                cardId: $authorization->cardId(),
                authorizationId: $authorization->id(),
                eventId: $eventId,
                type: TransactionTypeEnum::CAPTURE,
                amount: Money::fromCents(
                    $captureInAuthorizationMonth,
                )->negate(),
                occurredAt: $input->occurredAt,
                limitMonth: $authorizationMonth,
                reference: $input->externalId,
            );

            $this->transactionRepository->save($capture);
        }

        /*
         * Excedente em relação ao valor ainda reservado.
         *
         * Quando a captura acontece em outro mês, esse excedente
         * consome o limite do mês da captura.
         */
        $excessCents = $input->amount->toCents()
            - $captureInAuthorizationMonth;

        if (
            $excessCents > 0
            && $captureMonth !== $authorizationMonth
        ) {
            $captureExcess = new Transaction(
                id: (string) Str::uuid(),
                companyId: $authorization->companyId(),
                cardId: $authorization->cardId(),
                authorizationId: $authorization->id(),
                eventId: $eventId,
                type: TransactionTypeEnum::CAPTURE,
                amount: Money::fromCents($excessCents)->negate(),
                occurredAt: $input->occurredAt,
                limitMonth: $captureMonth,
                reference: $input->externalId,
            );

            $this->transactionRepository->save($captureExcess);
        }

        /*
         * Somente depois de todas as movimentações financeiras terem
         * sido persistidas o evento passa para processed.
         */
        $processedEvent = new Event(
            id: $eventId,
            externalId: $input->externalId,
            authorizationReference: $input->authorizationId,
            authorizationId: $authorization->id(),
            type: 'capture',
            amountCents: $input->amount->toCents(),
            currency: $input->currency,
            sequence: $input->sequence,
            final: $input->final,
            occurredAt: $input->occurredAt,
            status: 'processed',
        );

        $this->eventRepository->update($processedEvent);

        return true;
    }
}
