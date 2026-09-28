<?php

declare(strict_types=1);

namespace App\Application\Capture\UseCases;

use App\Application\Capture\DTO\CaptureTransactionInput;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Authorization\Services\CaptureTolerance;
use App\Domain\Event\Entity\Event;
use App\Domain\Event\Repositories\EventRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;

final class CaptureTransaction
{
    public function __construct(
        private readonly AuthorizationRepository $authorizationRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly CaptureTolerance $captureTolerance,
        private readonly EventRepository $eventRepository,
    ) {}

    public function execute(CaptureTransactionInput $input): bool
    {
        $existingEvent = $this->eventRepository->findByExternalId($input->externalId);
        if ($existingEvent !== null && $existingEvent->status() !== 'pending') {
            return true;
        }

        $authorization = $this->authorizationRepository
            ->findByExternalId($input->authorizationId);

        if ($authorization === null) {
            $event = new Event(
                id: (string) \Illuminate\Support\Str::uuid(),
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

            $this->eventRepository->save($event);

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
            $authorization->mcc()
        );

        $maximumCaptureAmount = $authorization->amount()->add(
            Money::fromCents(
                intdiv(
                    $authorization->amount()->toCents() * $tolerancePercentage,
                    100
                )
            )
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

        $capture = new Transaction(
            id: (string) \Illuminate\Support\Str::uuid(),
            companyId: $authorization->companyId(),
            cardId: $authorization->cardId(),
            authorizationId: $authorization->id(),
            eventId: null,
            type: TransactionTypeEnum::CAPTURE,
            amount: $input->amount->negate(),
            occurredAt: $input->occurredAt,
            limitMonth: $input->occurredAt->format('Y-m'),
            reference: $input->externalId,
        );

        $this->transactionRepository->save($capture);

        $event = new Event(
            // id: (string) \Illuminate\Support\Str::uuid(),
            id: $existingEvent?->id() ?? (string) \Illuminate\Support\Str::uuid(),
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

        // $this->eventRepository->save($event);
        if ($existingEvent !== null) {
            $this->eventRepository->update($event);
        } else {
            $this->eventRepository->save($event);
        }

        return true;
    }
}
