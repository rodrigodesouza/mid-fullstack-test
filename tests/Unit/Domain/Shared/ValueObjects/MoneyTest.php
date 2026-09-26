<?php

declare(strict_types=1);

use Domain\Shared\ValueObjects\Money;

// Testa se o objeto Money pode ser instanciado via centavos e recupera o valor corretamente
test('it can be instantiated from cents and return cents', function () {
    $money = Money::fromCents(15050);

    expect($money->toCents())->toBe(15050);
});

// Testa a adição de dois valores monetários garantindo que o objeto original não é alterado (imutabilidade)
test('it can add two money values immutably', function () {
    $amount1 = Money::fromCents(1000);
    $amount2 = Money::fromCents(500);

    $sum = $amount1->add($amount2);

    expect($sum->toCents())->toBe(1500)
        ->and($amount1->toCents())->toBe(1000); // Garante que $amount1 continua inalterado
});

// Testa a subtração de dois valores monetários mantendo a imutabilidade
test('it can subtract two money values immutably', function () {
    $amount1 = Money::fromCents(1000);
    $amount2 = Money::fromCents(300);

    $difference = $amount1->subtract($amount2);

    expect($difference->toCents())->toBe(700)
        ->and($amount1->toCents())->toBe(1000); // Garante que $amount1 continua inalterado
});

// Testa se dois objetos Money com o mesmo valor são considerados iguais e diferentes quando os valores mudam
test('it can check equality between money values', function () {
    $money1 = Money::fromCents(1000);
    $money2 = Money::fromCents(1000);
    $money3 = Money::fromCents(2000);

    expect($money1->equals($money2))->toBeTrue()
        ->and($money1->equals($money3))->toBeFalse();
});

// Testa as validações de comparação de maior ou igual entre valores monetários
test('it can evaluate if money is greater than or equal to another', function () {
    $ten = Money::fromCents(1000);
    $twenty = Money::fromCents(2000);
    $anotherTen = Money::fromCents(1000);

    expect($twenty->isGreaterThanOrEqual($ten))->toBeTrue()
        ->and($ten->isGreaterThanOrEqual($anotherTen))->toBeTrue()
        ->and($ten->isGreaterThanOrEqual($twenty))->toBeFalse();
});

// Testa se o método identifica corretamente quando o valor monetário é exatamente zero
test('it can check if money is zero', function () {
    $zero = Money::fromCents(0);
    $notZero = Money::fromCents(100);

    expect($zero->isZero())->toBeTrue()
        ->and($notZero->isZero())->toBeFalse();
});
