<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Capture\DTO\CaptureTransactionInput;
use App\Application\Capture\UseCases\CaptureTransaction;
use App\Domain\Event\Repositories\EventRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Events\AuthorizationApproved;

final readonly class ProcessPendingCapture
{
    public function __construct(
        private EventRepository $eventRepository,
        private CaptureTransaction $captureTransaction,
    ) {}

    public function handle(AuthorizationApproved $event): void
    {
        $authorization = $event->authorization;

        $pendingEvents = $this->eventRepository
            ->findPendingByAuthorizationReference(
                $authorization->externalId()
            );

        foreach ($pendingEvents as $pendingEvent) {
            $this->captureTransaction->execute(
                new CaptureTransactionInput(
                    externalId: $pendingEvent->externalId(),
                    authorizationId: $authorization->externalId(),
                    amount: Money::fromCents($pendingEvent->amountCents()),
                    currency: $pendingEvent->currency(),
                    occurredAt: $pendingEvent->occurredAt(),
                    final: $pendingEvent->isFinal(),
                    sequence: $pendingEvent->sequence(),
                ),
            );
        }
    }
}
