<?php

declare(strict_types=1);

use App\Domain\Company\Entity\Company;
use App\Domain\Company\Exceptions\InvalidCompanyAttributeException;

// Testa se a empresa pode ser instanciada corretamente
test('it can instantiate a company with initial balance', function (): void {
    $company = new Company(
        id: 1,
        name: 'Acme Corp',
    );

    expect($company->id())->toBe(1)
        ->and($company->name())->toBe('Acme Corp');
});

// Testa se lança exceção ao tentar criar uma empresa com ID vazio
test('it throws exception when company id is empty', function (): void {
    expect(fn () => new Company(id: 0, name: 'Acme Corp'))
        ->toThrow(InvalidCompanyAttributeException::class, 'Company ID must be a positive integer.');
});

// Testa se lança exceção ao tentar criar uma empresa com nome vazio
test('it throws exception when company name is empty', function (): void {
    expect(fn () => new Company(id: 1, name: ''))
        ->toThrow(InvalidCompanyAttributeException::class, 'Company name cannot be empty.');
});

// Testa se lança exceção ao tentar criar uma empresa com nome contendo apenas espaços em branco
test('it throws exception when company name is blank', function (): void {
    expect(fn () => new Company(id: 1, name: '   '))
        ->toThrow(InvalidCompanyAttributeException::class, 'Company name cannot be empty.');
});
