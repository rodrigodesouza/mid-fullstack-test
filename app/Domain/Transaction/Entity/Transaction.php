<?php

declare(strict_types=1);

namespace App\Domain\Transaction\Entity;

use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class Transaction
{
    public function __construct(
        /**
         * Unique identifier of the transaction.
         */
        private string $id,
        /**
         * Company to which the financial movement belongs.
         *
         * Used to derive the company's available balance from the ledger.
         */
        private int $companyId,
        /**
         * Card associated with the financial movement.
         *
         * Null for company-level transactions, such as the initial deposit.
         */
        private ?int $cardId,
        /**
         * Authorization that originated the transaction.
         *
         * Null for transactions that are not related to an authorization.
         */
        private ?string $authorizationId,
        /**
         * Event that originated the transaction.
         *
         * Null for transactions that are not related to an event.
         */
        private ?string $eventId,
        /**
         * Type of financial movement represented by the transaction.
         *
         * Possible types are deposit, reserve, release, capture and cancellation.
         */
        private TransactionTypeEnum $type,
        /**
         * Financial impact of the transaction.
         *
         * Positive values increase the balance/available limit.
         * Negative values decrease the balance/available limit.
         */
        private Money $amount,
        /**
         * Date and time when the financial movement occurred.
         */
        private DateTimeImmutable $occurredAt,
        /**
         * Month to which the transaction's card limit impact belongs.
         *
         * Format: YYYY-MM.
         * Null when the transaction does not affect a card's monthly limit.
         */
        private ?string $limitMonth,
        /**
         * Identifier of the authorization or event that originated the movement.
         *
         * Used to trace the transaction back to its business origin.
         */
        private string $reference,
    ) {
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
        throw_if($this->id !== null
        && mb_trim($this->id) === '', InvalidArgumentException::class, 'Transaction id cannot be empty.');

        throw_if($this->authorizationId !== null
        && mb_trim($this->authorizationId) === '', InvalidArgumentException::class, 'Transaction authorization id cannot be empty.');
        throw_if($this->companyId <= 0, InvalidArgumentException::class, 'Transaction company id must be greater than zero.');
        throw_if($this->cardId !== null && $this->cardId <= 0, InvalidArgumentException::class, 'Transaction card id must be greater than zero.');
        throw_if(mb_trim($this->authorizationId) === '' && $this->authorizationId !== null, InvalidArgumentException::class, 'Transaction authorization id cannot be empty.');
        throw_if($this->eventId !== null
        && mb_trim($this->eventId) === '', InvalidArgumentException::class, 'Transaction event id cannot be empty.');
        throw_if($this->amount->isZero(), InvalidArgumentException::class, 'Transaction amount must not be zero.');
        throw_if($this->limitMonth !== null && ! preg_match(
            '/^\d{4}-(0[1-9]|1[0-2])$/',
            $this->limitMonth
        ), InvalidArgumentException::class, 'Transaction limit month must be in YYYY-MM format.');
        throw_if(mb_trim($this->reference) === '', InvalidArgumentException::class, 'Transaction reference cannot be empty.');
    }
}
