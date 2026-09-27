<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Entity;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Transaction
{
    /**
     * Unique identifier of the transaction.
     */
    private string $id;

    /**
     * Company to which the financial movement belongs.
     *
     * Used to derive the company's available balance from the ledger.
     */
    private int $companyId;

    /**
     * Card associated with the financial movement.
     *
     * Null for company-level transactions, such as the initial deposit.
     */
    private ?int $cardId;

    /**
     * Authorization that originated the transaction.
     *
     * Null for transactions that are not related to an authorization.
     */
    private ?string $authorizationId;

    /**
     * Event that originated the transaction.
     *
     * Null for transactions that are not related to an event.
     */
    private ?string $eventId;

    /**
     * Type of financial movement represented by the transaction.
     *
     * Possible types are deposit, reserve, release, capture and cancellation.
     */
    private TransactionTypeEnum $type;

    /**
     * Financial impact of the transaction.
     *
     * Positive values increase the balance/available limit.
     * Negative values decrease the balance/available limit.
     */
    private Money $amount;

    /**
     * Date and time when the financial movement occurred.
     */
    private DateTimeImmutable $occurredAt;

    /**
     * Month to which the transaction's card limit impact belongs.
     *
     * Format: YYYY-MM.
     * Null when the transaction does not affect a card's monthly limit.
     */
    private ?string $limitMonth;

    /**
     * Identifier of the authorization or event that originated the movement.
     *
     * Used to trace the transaction back to its business origin.
     */
    private string $reference;

    public function __construct(
        string $id,
        int $companyId,
        ?int $cardId,
        ?string $authorizationId,
        ?string $eventId,
        TransactionTypeEnum $type,
        Money $amount,
        DateTimeImmutable $occurredAt,
        ?string $limitMonth,
        string $reference,
    ) {
        $this->id = $id;
        $this->companyId = $companyId;
        $this->cardId = $cardId;
        $this->authorizationId = $authorizationId;
        $this->eventId = $eventId;
        $this->type = $type;
        $this->amount = $amount;
        $this->occurredAt = $occurredAt;
        $this->limitMonth = $limitMonth;
        $this->reference = $reference;

        $this->validate();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function cardId(): ?int
    {
        return $this->cardId;
    }

    public function authorizationId(): ?string
    {
        return $this->authorizationId;
    }

    public function eventId(): ?string
    {
        return $this->eventId;
    }

    public function type(): TransactionTypeEnum
    {
        return $this->type;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function limitMonth(): ?string
    {
        return $this->limitMonth;
    }

    public function reference(): string
    {
        return $this->reference;
    }

    private function validate(): void
    {
        if (
            $this->id !== null
            && trim($this->id) === ''
        ) {
            throw new InvalidArgumentException(
                'Transaction id cannot be empty.'
            );
        }
        if (
            $this->authorizationId !== null
            && trim($this->authorizationId) === ''
        ) {
            throw new InvalidArgumentException(
                'Transaction authorization id cannot be empty.'
            );
        }

        if ($this->companyId <= 0) {
            throw new InvalidArgumentException(
                'Transaction company id must be greater than zero.'
            );
        }

        if ($this->cardId !== null && $this->cardId <= 0) {
            throw new InvalidArgumentException(
                'Transaction card id must be greater than zero.'
            );
        }

        if (trim($this->authorizationId) === '' && $this->authorizationId !== null) {
            throw new InvalidArgumentException(
                'Transaction authorization id cannot be empty.'
            );
        }

        if (
            $this->eventId !== null
            && trim($this->eventId) === ''
        ) {
            throw new InvalidArgumentException(
                'Transaction event id cannot be empty.'
            );
        }

        if ($this->amount->isZero()) {
            throw new InvalidArgumentException(
                'Transaction amount must not be zero.'
            );
        }

        if ($this->limitMonth !== null && ! preg_match(
            '/^\d{4}-(0[1-9]|1[0-2])$/',
            $this->limitMonth
        )) {
            throw new InvalidArgumentException(
                'Transaction limit month must be in YYYY-MM format.'
            );
        }

        if (trim($this->reference) === '') {
            throw new InvalidArgumentException(
                'Transaction reference cannot be empty.'
            );
        }
    }
}
