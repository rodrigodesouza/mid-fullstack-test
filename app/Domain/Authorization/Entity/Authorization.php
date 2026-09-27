<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entity;

use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Enums\AuthorizationReasonEnum;
use App\Domain\Authorization\Exceptions\InvalidAuthorizationException;
use App\Domain\Shared\ValueObjects\Money;
use DateTimeImmutable;

final readonly class Authorization
{
    /**
     * Identificador interno da autorização.
     */
    private string $id;

    /**
     * Identificador da autorização na rede.
     *
     * Também é utilizado como chave de idempotência.
     */
    private string $externalId;

    /**
     * Identificador do cartão.
     */
    private int $cardId;

    /**
     * Identificador da empresa proprietária do cartão.
     */
    private int $companyId;

    /**
     * Valor solicitado na autorização.
     */
    private Money $amount;

    /**
     * Moeda da transação.
     */
    private string $currency;

    /**
     * Merchant Category Code.
     */
    private string $mcc;

    /**
     * Resultado da autorização.
     */
    private AuthorizationDecisionEnum $decision;

    /**
     * Motivo da recusa.
     *
     * Nulo quando a autorização foi aprovada.
     */
    private ?AuthorizationReasonEnum $reason;

    /**
     * Nome do estabelecimento.
     */
    private string $merchantName;

    /**
     * Cidade do estabelecimento.
     */
    private string $merchantCity;

    /**
     * País do estabelecimento.
     */
    private string $merchantCountry;

    /**
     * Data e hora em que a autorização ocorreu na rede.
     */
    private DateTimeImmutable $occurredAt;

    public function __construct(
        string $id,
        string $externalId,
        int $cardId,
        int $companyId,
        Money $amount,
        string $currency,
        string $mcc,
        AuthorizationDecisionEnum $decision,
        ?AuthorizationReasonEnum $reason,
        string $merchantName,
        string $merchantCity,
        string $merchantCountry,
        DateTimeImmutable $occurredAt,
    ) {
        if (trim($id) === '') {
            throw new InvalidAuthorizationException('Authorization id cannot be empty.');
        }

        if (trim($externalId) === '') {
            throw new InvalidAuthorizationException('Authorization external id cannot be empty.');
        }

        if ($cardId <= 0) {
            throw new InvalidAuthorizationException('Authorization card id must be greater than zero.');
        }

        if ($companyId <= 0) {
            throw new InvalidAuthorizationException('Authorization company id must be greater than zero.');
        }

        if ($amount->isLessThan(Money::fromCents(1))) {
            throw new InvalidAuthorizationException('Authorization amount must be greater than zero.');
        }

        if (trim($currency) === '') {
            throw new InvalidAuthorizationException('Authorization currency cannot be empty.');
        }

        if (trim($mcc) === '') {
            throw new InvalidAuthorizationException('Authorization MCC cannot be empty.');
        }

        if (trim($merchantName) === '') {
            throw new InvalidAuthorizationException('Authorization merchant name cannot be empty.');
        }

        if (trim($merchantCity) === '') {
            throw new InvalidAuthorizationException('Authorization merchant city cannot be empty.');
        }

        if (trim($merchantCountry) === '') {
            throw new InvalidAuthorizationException('Authorization merchant country cannot be empty.');
        }

        if (
            $decision === AuthorizationDecisionEnum::APPROVED
            && $reason !== null
        ) {
            throw new InvalidAuthorizationException(
                'Approved authorization cannot have a reason.'
            );
        }

        if (
            $decision === AuthorizationDecisionEnum::DECLINED
            && $reason === null
        ) {
            throw new InvalidAuthorizationException(
                'Declined authorization must have a reason.'
            );
        }

        $this->id = $id;
        $this->externalId = $externalId;
        $this->cardId = $cardId;
        $this->companyId = $companyId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->mcc = $mcc;
        $this->decision = $decision;
        $this->reason = $reason;
        $this->merchantName = $merchantName;
        $this->merchantCity = $merchantCity;
        $this->merchantCountry = $merchantCountry;
        $this->occurredAt = $occurredAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function externalId(): string
    {
        return $this->externalId;
    }

    public function cardId(): int
    {
        return $this->cardId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function amount(): Money
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function mcc(): string
    {
        return $this->mcc;
    }

    public function decision(): AuthorizationDecisionEnum
    {
        return $this->decision;
    }

    public function reason(): ?AuthorizationReasonEnum
    {
        return $this->reason;
    }

    public function merchantName(): string
    {
        return $this->merchantName;
    }

    public function merchantCity(): string
    {
        return $this->merchantCity;
    }

    public function merchantCountry(): string
    {
        return $this->merchantCountry;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function isApproved(): bool
    {
        return $this->decision === AuthorizationDecisionEnum::APPROVED;
    }

    public function isDeclined(): bool
    {
        return $this->decision === AuthorizationDecisionEnum::DECLINED;
    }
}
