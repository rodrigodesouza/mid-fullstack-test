<?php

declare(strict_types=1);

namespace App\Domain\Event\Entity;

use DateTimeImmutable;

final readonly class Event
{
    public function __construct(
        private string $id,
        private string $externalId,
        private string $authorizationReference,
        private ?string $authorizationId,
        private string $type,
        private ?int $amountCents,
        private ?string $currency,
        private ?int $sequence,
        private ?bool $final,
        private DateTimeImmutable $occurredAt,
        private string $status,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function authorizationReference(): string
    {
        return $this->authorizationReference;
    }

    public function authorizationId(): ?string
    {
        return $this->authorizationId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function amountCents(): ?int
    {
        return $this->amountCents;
    }

    public function currency(): ?string
    {
        return $this->currency;
    }

    public function sequence(): ?int
    {
        return $this->sequence;
    }

    public function isFinal(): bool
    {
        return $this->final === true;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function status(): string
    {
        return $this->status;
    }
}
