<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Event\Entity\Event;
use App\Domain\Event\Repositories\EventRepository;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;

final class EventEloquentRepository implements EventRepository
{
    public function findByExternalId(string $externalId): ?Event
    {
        $model = EventModel::query()
            ->where('external_id', $externalId)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(Event $event): void
    {
        EventModel::query()->create([
            'id' => $event->id(),
            'external_id' => $event->externalId(),
            'authorization_id' => $event->authorizationId(),
            'authorization_reference' => $event->authorizationReference(),
            'type' => $event->type(),
            'amount_cents' => $event->amountCents(),
            'currency' => $event->currency(),
            'sequence' => $event->sequence(),
            'final' => $event->isFinal(),
            'occurred_at' => $event->occurredAt(),
            'status' => $event->status(),
        ]);
    }

    public function update(Event $event): void
    {
        EventModel::query()
            ->whereKey($event->id())
            ->update([
                'authorization_id' => $event->authorizationId(),
                'authorization_reference' => $event->authorizationReference(),
                'status' => $event->status(),
            ]);
    }

    public function hasFinalCapture(string $authorizationId): bool
    {
        return EventModel::query()
            ->where('authorization_id', $authorizationId)
            ->where('type', 'capture')
            ->where('final', true)
            ->exists();
    }

    public function findPendingByAuthorizationReference(
        string $authorizationReference
    ): array {
        return EventModel::query()
            ->where('authorization_reference', $authorizationReference)
            ->where('status', 'pending')
            ->orderBy('sequence')
            ->oldest('occurred_at')
            ->get()
            ->map(fn (EventModel $model) => $this->toDomain($model))
            ->all();
    }

    private function toDomain(EventModel $model): Event
    {
        return new Event(
            id: $model->id,
            externalId: $model->external_id,
            authorizationReference: $model->authorization_reference,
            authorizationId: $model->authorization_id,
            type: $model->type,
            amountCents: $model->amount_cents,
            currency: $model->currency,
            sequence: $model->sequence,
            final: $model->final,
            occurredAt: new DateTimeImmutable(
                $model->occurred_at->toISOString()
            ),
            status: $model->status,
        );
    }
}
