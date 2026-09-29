<?php

declare(strict_types=1);

use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Enums\AuthorizationReasonEnum;
use App\Domain\Shared\ValueObjects\Money;
use Carbon\CarbonImmutable;

describe('Authorization', function (): void {
    // cria uma autorização aprovada
    it('creates an approved authorization', function (): void {
        $authorization = new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(12990),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Restaurante Bom Prato',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::parse('2026-09-17T14:03:22Z'),
        );

        expect($authorization->id())->toBe('aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y')
            ->and($authorization->externalId())
            ->toBe('aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y')
            ->and($authorization->cardId())->toBe(10)
            ->and($authorization->companyId())->toBe(20)
            ->and($authorization->amount()->toCents())->toBe(12990)
            ->and($authorization->currency())->toBe('BRL')
            ->and($authorization->mcc())->toBe('5812')
            ->and($authorization->decision())
            ->toBe(AuthorizationDecisionEnum::APPROVED)
            ->and($authorization->reason())->toBeNull()
            ->and($authorization->merchantName())
            ->toBe('Restaurante Bom Prato')
            ->and($authorization->merchantCity())
            ->toBe('Porto Alegre')
            ->and($authorization->merchantCountry())
            ->toBe('BR')
            ->and($authorization->occurredAt())
            ->toEqual(CarbonImmutable::parse('2026-09-17T14:03:22Z'))
            ->and($authorization->isApproved())->toBeTrue()
            ->and($authorization->isDeclined())->toBeFalse();
    });

    // Cria uma autorização recusada com uma justificativa.
    it('creates a declined authorization with a reason', function (): void {
        $authorization = new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(85000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED,
            merchantName: 'Restaurante Bom Prato',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::parse('2026-09-17T14:03:22Z'),
        );

        expect($authorization->decision())
            ->toBe(AuthorizationDecisionEnum::DECLINED)
            ->and($authorization->reason())
            ->toBe(AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED)
            ->and($authorization->isApproved())->toBeFalse()
            ->and($authorization->isDeclined())->toBeTrue();
    });

    it('requires a id not null', function (): void {
        expect(fn () => new Authorization(
            id: '',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('requires an external id', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: '',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('requires a positive card id', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 0,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('requires a positive company id', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 0,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('requires a positive amount', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(0),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('requires a currency', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: '',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('requires an mcc', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    it('requires merchant information', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: null,
            merchantName: '',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    // Não permite a apresentação de uma justificativa para uma autorização aprovada.
    it('does not allow a reason for an approved authorization', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::APPROVED,
            reason: AuthorizationReasonEnum::MCC_BLOCKED,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });

    // É necessário apresentar uma justificativa para a recusa de uma autorização.
    it('requires a reason for a declined authorization', function (): void {
        expect(fn () => new Authorization(
            id: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
            externalId: 'aut_01',
            cardId: 10,
            companyId: 20,
            amount: Money::fromCents(1000),
            currency: 'BRL',
            mcc: '5812',
            decision: AuthorizationDecisionEnum::DECLINED,
            reason: null,
            merchantName: 'Merchant',
            merchantCity: 'Porto Alegre',
            merchantCountry: 'BR',
            occurredAt: CarbonImmutable::now(),
        ))->toThrow(InvalidArgumentException::class);
    });
});
