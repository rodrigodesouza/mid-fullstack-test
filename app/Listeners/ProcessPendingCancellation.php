<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Application\Cancellation\DTO\CancelTransactionInput;
use App\Application\Cancellation\UseCases\CancelTransaction;
use App\Domain\Event\Repositories\EventRepository;
use App\Events\AuthorizationApproved;

final readonly class ProcessPendingCancellation
{
    public function __construct(
        private EventRepository $eventRepository,
        private CancelTransaction $cancelTransaction,
    ) {}

    public function handle(AuthorizationApproved $event): void
    {
        $authorization = $event->authorization;

        $pendingEvents = $this->eventRepository
            ->findPendingByAuthorizationReference(
                $authorization->externalId()
            );

        foreach ($pendingEvents as $pendingEvent) {
            if ($pendingEvent->type() !== 'cancellation') {
                continue;
            }

            $this->cancelTransaction->execute(
                new CancelTransactionInput(
                    externalId: $pendingEvent->externalId(),
                    authorizationId: $authorization->externalId(),
                    occurredAt: $pendingEvent->occurredAt(),
                ),
            );
        }
    }
}
