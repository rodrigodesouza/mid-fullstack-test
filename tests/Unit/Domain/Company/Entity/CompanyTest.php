<?php

declare(strict_types=1);

use App\Domain\Company\Entity\Company;
use App\Domain\Company\Exceptions\InvalidCompanyAttributeException;

// Testa se a empresa pode ser instanciada corretamente
test('it can instantiate a company with initial balance', function () {
    $company = new Company(
        id: 'comp_acme',
        name: 'Acme Corp',
    );

    expect($company->id())->toBe('comp_acme')
        ->and($company->name())->toBe('Acme Corp');
});

// Testa se lança exceção ao tentar criar uma empresa com ID vazio
test('it throws exception when company id is empty', function () {
    expect(fn () => new Company(id: '', name: 'Acme Corp'))
        ->toThrow(InvalidCompanyAttributeException::class, 'Company ID cannot be empty.');
});

// Testa se lança exceção ao tentar criar uma empresa com ID contendo apenas espaços em branco
test('it throws exception when company id is blank', function () {
    expect(fn () => new Company(id: '   ', name: 'Acme Corp'))
        ->toThrow(InvalidCompanyAttributeException::class, 'Company ID cannot be empty.');
});

// Testa se lança exceção ao tentar criar uma empresa com nome vazio
test('it throws exception when company name is empty', function () {
    expect(fn () => new Company(id: 'comp_acme', name: ''))
        ->toThrow(InvalidCompanyAttributeException::class, 'Company name cannot be empty.');
});

// Testa se lança exceção ao tentar criar uma empresa com nome contendo apenas espaços em branco
test('it throws exception when company name is blank', function () {
    expect(fn () => new Company(id: 'comp_acme', name: '   '))
        ->toThrow(InvalidCompanyAttributeException::class, 'Company name cannot be empty.');
});
