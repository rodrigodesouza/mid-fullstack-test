<?php

declare(strict_types=1);

use App\Application\Authorization\DTO\AuthorizationResult;
use App\Application\Authorization\UseCases\AuthorizeTransaction;
use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Enums\AuthorizationReasonEnum;
use App\Domain\Authorization\Repositories\AuthorizationRepository;
use App\Domain\Card\Enums\CardStatusEnum;
use App\Domain\Card\Repositories\CardLimitsRepository;
use App\Domain\Card\Repositories\CardRepository;
use App\Domain\Company\Repositories\CompanyBalanceRepository;
use App\Domain\Shared\ValueObjects\Money;
use App\Domain\Transaction\Entity\Transaction;
use App\Domain\Transaction\Enums\TransactionTypeEnum;
use App\Domain\Transaction\Repositories\TransactionRepository;
use App\Domain\User\Repositories\UserRepository;
use Tests\Support\Builders\AuthorizeTransactionInputBuilder;
use Tests\Support\Builders\CardBuilder;
use Tests\Support\Builders\UserBuilder;

afterEach(function () {
    Mockery::close();
});

// A1 — Regra: retorna a autorização existente quando o ID externo já foi processado.
it('A1: returns the existing authorization when the external id was already processed', function () {
    $authorization = new Authorization(
        id: '01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
        externalId: 'aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y',
        cardId: 1,
        companyId: 1,
        amount: Money::fromCents(12990),
        currency: 'BRL',
        mcc: '5812',
        decision: AuthorizationDecisionEnum::APPROVED,
        reason: null,
        merchantName: 'Restaurante Bom Prato',
        merchantCity: 'Porto Alegre',
        merchantCountry: 'BR',
        occurredAt: new DateTimeImmutable('2026-09-17T14:03:22Z'),
    );

    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->with('aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y')
        ->andReturn($authorization);

    $authorizationRepository
        ->shouldReceive('save')
        ->never();

    $cardRepository = Mockery::mock(CardRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $input = AuthorizeTransactionInputBuilder::make()
        ->withExternalId('aut_01J8KQ7Z3N9M2P4R6T8V0W1X2Y')
        ->withCardToken('tok_ana')
        ->withAmount(12990)
        ->build();

    $useCase = new AuthorizeTransaction(
        authorizationRepository: $authorizationRepository,
        cardRepository: $cardRepository,
        cardLimitsRepository: $cardLimitsRepository,
        userRepository: $userRepository,
        companyBalanceRepository: $companyBalanceRepository,
        transactionRepository: $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->authorization)
        ->toBe($authorization)
        ->and($result->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($result->reason)
        ->toBeNull();
});

// A2 — Regra: recusa a autorização quando o cartão não existe, sem criar reserva financeira.
it('A2: declines the authorization when the card does not exist', function () {
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    $cardRepository = Mockery::mock(CardRepository::class);

    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->with('tok_inexistente')
        ->andReturnNull();

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken('tok_inexistente')
        ->build();

    $useCase = new AuthorizeTransaction(
        $authorizationRepository,
        $cardRepository,
        $cardLimitsRepository,
        $userRepository,
        $companyBalanceRepository,
        $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->authorization)
        ->toBeNull()
        ->and($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::CARD_NOT_FOUND);
});

// A3 — Regra: recusa a autorização quando o cartão está bloqueado, sem criar reserva financeira.
it('A3: declines the authorization when the card is blocked', function () {
    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    $authorizationRepository
        ->shouldReceive('save')
        ->never();

    $card = CardBuilder::make()
        ->withStatus(CardStatusEnum::BLOCKED)
        ->build();

    $cardRepository = Mockery::mock(CardRepository::class);

    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->with($card->cardToken())
        ->andReturn($card);

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken($card->cardToken())
        ->build();

    $useCase = new AuthorizeTransaction(
        $authorizationRepository,
        $cardRepository,
        $cardLimitsRepository,
        $userRepository,
        $companyBalanceRepository,
        $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->authorization)
        ->toBeNull()
        ->and($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::CARD_BLOCKED);
});

// A4 — Regra: recusa a autorização quando o MCC está bloqueado para o cartão, sem criar reserva financeira.
it('A4: declines the authorization when the MCC is blocked for the card', function () {
    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    $authorizationRepository
        ->shouldReceive('save')
        ->never();

    $card = CardBuilder::make()->build();

    $cardRepository = Mockery::mock(CardRepository::class);

    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->with($card->cardToken())
        ->andReturn($card);

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken($card->cardToken())
        ->withMcc('7995')
        ->build();

    $useCase = new AuthorizeTransaction(
        $authorizationRepository,
        $cardRepository,
        $cardLimitsRepository,
        $userRepository,
        $companyBalanceRepository,
        $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->authorization)
        ->toBeNull()
        ->and($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::MCC_BLOCKED);
});

// A5 — Regra: deve recusar a autorização quando o valor exceder o limite máximo por compra.
it('A5: declines the authorization when the amount exceeds the purchase limit', function () {
    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    $authorizationRepository
        ->shouldReceive('save')
        ->never();

    $card = CardBuilder::make()->build();

    $cardRepository = Mockery::mock(CardRepository::class);

    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->with($card->cardToken())
        ->andReturn($card);

    $cardLimitsRepository
        ->shouldReceive('purchaseLimitFor')
        ->once()
        ->with($card->id())
        ->andReturn(Money::fromCents(80000));

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken($card->cardToken())
        ->withAmount(85000)
        ->build();

    $useCase = new AuthorizeTransaction(
        $authorizationRepository,
        $cardRepository,
        $cardLimitsRepository,
        $userRepository,
        $companyBalanceRepository,
        $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->authorization)
        ->toBeNull()
        ->and($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED);
});

// A6 — Regra: deve recusar a autorização quando o valor exceder o limite mensal restante do cartão.
it('A6: declines the authorization when the amount exceeds the remaining monthly card limit', function () {
    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    $authorizationRepository
        ->shouldReceive('save')
        ->never();

    $card = CardBuilder::make()->build();

    $cardRepository = Mockery::mock(CardRepository::class);

    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->with($card->cardToken())
        ->andReturn($card);

    $cardLimitsRepository
        ->shouldReceive('purchaseLimitFor')
        ->once()
        ->with($card->id())
        ->andReturn(Money::fromCents(100000));

    $cardLimitsRepository
        ->shouldReceive('remainingForMonth')
        ->once()
        ->with($card->id(), '2026-09')
        ->andReturn(Money::fromCents(30000));

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken($card->cardToken())
        ->withAmount(35000)
        ->build();

    $useCase = new AuthorizeTransaction(
        $authorizationRepository,
        $cardRepository,
        $cardLimitsRepository,
        $userRepository,
        $companyBalanceRepository,
        $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->authorization)
        ->toBeNull()
        ->and($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::PURCHASE_LIMIT_EXCEEDED);
});

// A7 — Regra: recusa a autorização quando o valor da compra excede o saldo disponível da empresa.
it('A7: declines the authorization when the amount exceeds the company available balance', function () {
    $card = CardBuilder::make()->build();

    $user = UserBuilder::make()
        ->withId($card->userId())
        ->withCompanyId(1)
        ->build();

    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    $cardRepository = Mockery::mock(CardRepository::class);
    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->andReturn($card);

    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);

    $cardLimitsRepository
        ->shouldReceive('purchaseLimitFor')
        ->once()
        ->with($card->id())
        ->andReturn(Money::fromCents(100000));

    $cardLimitsRepository
        ->shouldReceive('remainingForMonth')
        ->once()
        ->with($card->id(), '2026-09')
        ->andReturn(Money::fromCents(100000));

    $userRepository = Mockery::mock(UserRepository::class);

    $userRepository
        ->shouldReceive('findById')
        ->once()
        ->with($card->userId())
        ->andReturn($user);

    $companyBalanceRepository
        ->shouldReceive('availableBalanceFor')
        ->once()
        ->with($user->companyId())
        ->andReturn(Money::fromCents(50000));

    $useCase = new AuthorizeTransaction(
        authorizationRepository: $authorizationRepository,
        cardRepository: $cardRepository,
        cardLimitsRepository: $cardLimitsRepository,
        userRepository: $userRepository,
        companyBalanceRepository: $companyBalanceRepository,
        transactionRepository: $transactionRepository,
    );

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken($card->cardToken())
        ->withAmount(60000)
        ->build();

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::DECLINED)
        ->and($result->reason)
        ->toBe(AuthorizationReasonEnum::COMPANY_BALANCE_EXCEEDED)
        ->and($result->authorization)
        ->toBeNull();
});

// A8 — Regra: aprova a autorização quando todas as regras anteriores são satisfeitas e cria a autorização.
it('A8: approves the authorization when all authorization rules pass', function () {
    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $cardRepository = Mockery::mock(CardRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $card = CardBuilder::make()
        ->build();

    $user = UserBuilder::make()
        ->withId($card->userId())
        ->withCompanyId(1)
        ->build();

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    // $authorizationRepository
    //     ->shouldReceive('reserve')
    //     ->once()
    //     ->with(Mockery::type(Authorization::class));

    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->with($card->cardToken())
        ->andReturn($card);

    $cardLimitsRepository
        ->shouldReceive('purchaseLimitFor')
        ->once()
        ->with($card->id())
        ->andReturn(Money::fromCents(100000));

    $cardLimitsRepository
        ->shouldReceive('remainingForMonth')
        ->once()
        ->with($card->id(), '2026-09')
        ->andReturn(Money::fromCents(100000));

    $userRepository
        ->shouldReceive('findById')
        ->once()
        ->with($card->userId())
        ->andReturn($user);

    $companyBalanceRepository
        ->shouldReceive('availableBalanceFor')
        ->once()
        ->with($user->companyId())
        ->andReturn(Money::fromCents(100000));

    // $companyBalanceRepository
    //     ->shouldReceive('reserve')
    //     ->once()
    //     ->with($user->companyId(), Mockery::type(Money::class));
    $transactionRepository
        ->shouldReceive('reserve')
        ->once()
        ->andReturn(true);
    // $transactionRepository
    //     ->shouldReceive('save')
    //     ->once()
    //     ->with(Mockery::on(
    //         fn (Transaction $transaction) =>
    //             $transaction->companyId() === $user->companyId()
    //             && $transaction->cardId() === $card->id()
    //             && $transaction->authorizationId() !== null
    //             && $transaction->type() === TransactionTypeEnum::RESERVE
    //             && $transaction->amount()->toCents() === -60000
    //             && $transaction->limitMonth() === '2026-09'
    //     ));

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken($card->cardToken())
        ->withAmount(60000)
        ->build();

    $useCase = new AuthorizeTransaction(
        authorizationRepository: $authorizationRepository,
        cardRepository: $cardRepository,
        cardLimitsRepository: $cardLimitsRepository,
        userRepository: $userRepository,
        companyBalanceRepository: $companyBalanceRepository,
        transactionRepository: $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($result->reason)
        ->toBeNull()
        ->and($result->authorization)
        ->toBeInstanceOf(Authorization::class)
        ->and($result->authorization->externalId())
        ->toBe($input->externalId)
        ->and($result->authorization->cardId())
        ->toBe($card->id())
        ->and($result->authorization->companyId())
        ->toBe($user->companyId())
        ->and($result->authorization->amount())
        ->toEqual($input->amount);
});

// A9 — Regra: ao aprovar, deve reservar o valor no cartão e no saldo disponível da empresa.
it('A9: reserves the authorization amount on the card and company balance when approved', function () {
    $card = CardBuilder::make()
        ->build();

    $user = UserBuilder::make()
        ->withId($card->userId())
        ->withCompanyId(1)
        ->build();

    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $cardRepository = Mockery::mock(CardRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->once()
        ->andReturnNull();

    $cardRepository
        ->shouldReceive('findByToken')
        ->once()
        ->with($card->cardToken())
        ->andReturn($card);

    $cardLimitsRepository
        ->shouldReceive('purchaseLimitFor')
        ->once()
        ->with($card->id())
        ->andReturn(Money::fromCents(100000));

    $cardLimitsRepository
        ->shouldReceive('remainingForMonth')
        ->once()
        ->with($card->id(), '2026-09')
        ->andReturn(Money::fromCents(100000));

    $userRepository
        ->shouldReceive('findById')
        ->once()
        ->with($card->userId())
        ->andReturn($user);

    $companyBalanceRepository
        ->shouldReceive('availableBalanceFor')
        ->once()
        ->with($user->companyId())
        ->andReturn(Money::fromCents(100000));

    $input = AuthorizeTransactionInputBuilder::make()
        ->withCardToken($card->cardToken())
        ->withAmount(60000)
        ->build();
    $transactionRepository
        ->shouldReceive('reserve')
        ->once()
        ->andReturn(true);
    // $transactionRepository
    //     ->shouldReceive('save')
    //     ->once()
    //     ->with(Mockery::on(
    //         fn (Transaction $transaction) =>
    //             $transaction->companyId() === $user->companyId()
    //             && $transaction->cardId() === $card->id()
    //             && $transaction->authorizationId() !== null
    //             && $transaction->type() === TransactionTypeEnum::RESERVE
    //             && $transaction->amount()->toCents() === -60000
    //             && $transaction->limitMonth() === '2026-09'
    //             && $transaction->reference() !== ''
    //     ));

    $useCase = new AuthorizeTransaction(
        authorizationRepository: $authorizationRepository,
        cardRepository: $cardRepository,
        cardLimitsRepository: $cardLimitsRepository,
        userRepository: $userRepository,
        companyBalanceRepository: $companyBalanceRepository,
        transactionRepository: $transactionRepository,
    );

    $result = $useCase->execute($input);

    expect($result->decision)
        ->toBe(AuthorizationDecisionEnum::APPROVED)
        ->and($result->authorization)
        ->toBeInstanceOf(Authorization::class);
});

// A10: autorizações concorrentes não podem ultrapassar o saldo disponível.
it('A10: does not approve authorizations beyond the available balance under concurrency', function () {
    // A10: autorizações concorrentes não podem ultrapassar o saldo disponível.

    $authorizationRepository = Mockery::mock(AuthorizationRepository::class);
    $cardRepository = Mockery::mock(CardRepository::class);
    $cardLimitsRepository = Mockery::mock(CardLimitsRepository::class);
    $userRepository = Mockery::mock(UserRepository::class);
    $companyBalanceRepository = Mockery::mock(CompanyBalanceRepository::class);
    $transactionRepository = Mockery::mock(TransactionRepository::class);

    $card = CardBuilder::make()
        ->withId(1)
        ->withUserId(1)
        ->withMonthlyLimit(50000)
        ->build();

    $user = UserBuilder::make()
        ->withId(1)
        ->withCompanyId(1)
        ->build();

    $authorizationRepository
        ->shouldReceive('findByExternalId')
        ->times(20)
        ->andReturn(null);

    $cardRepository
        ->shouldReceive('findByToken')
        ->times(20)
        ->andReturn($card);

    $cardLimitsRepository
        ->shouldReceive('purchaseLimitFor')
        ->times(20)
        ->andReturn(Money::fromCents(50000));

    $cardLimitsRepository
        ->shouldReceive('remainingForMonth')
        ->times(20)
        ->andReturn(Money::fromCents(50000));

    $userRepository
        ->shouldReceive('findById')
        ->times(20)
        ->andReturn($user);

    $companyBalanceRepository
        ->shouldReceive('availableBalanceFor')
        ->times(20)
        ->andReturn(Money::fromCents(50000));

    $approvedReservations = 0;

    $transactionRepository
        ->shouldReceive('reserve')
        ->times(20)
        ->andReturnUsing(function () use (&$approvedReservations) {
            if ($approvedReservations >= 5) {
                return false;
            }

            $approvedReservations++;

            return true;
        });

    // $authorizationRepository
    //     ->shouldReceive('save')
    //     ->times(5);

    $useCase = new AuthorizeTransaction(
        authorizationRepository: $authorizationRepository,
        cardRepository: $cardRepository,
        cardLimitsRepository: $cardLimitsRepository,
        userRepository: $userRepository,
        companyBalanceRepository: $companyBalanceRepository,
        transactionRepository: $transactionRepository,
    );

    $results = [];

    for ($i = 1; $i <= 20; $i++) {
        $results[] = $useCase->execute(
            AuthorizeTransactionInputBuilder::make()
                ->withExternalId("external_$i")
                ->withCardToken($card->cardToken())
                ->withAmount(10000)
                ->build()
        );
    }

    expect(collect($results)->filter(
        fn (AuthorizationResult $result) => $result->decision === AuthorizationDecisionEnum::APPROVED
    ))->toHaveCount(5);

    expect(collect($results)->filter(
        fn (AuthorizationResult $result) => $result->decision === AuthorizationDecisionEnum::DECLINED
    ))->toHaveCount(15);
});
