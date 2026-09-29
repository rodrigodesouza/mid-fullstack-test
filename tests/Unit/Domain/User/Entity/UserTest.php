<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entity\User;
use App\Domain\User\Enums\RoleEnum;
use App\Domain\User\Exceptions\InvalidUserAttributeException;

// Testa se usuário pode ser instanciada corretamente
test('it can instantiate a user with initial balance', function (): void {
    $user = new User(
        id: 1,
        companyId: 1,
        name: 'John Doe',
        email: new Email('john.doe@example.com'),
        role: RoleEnum::CARD_HOLDER,
        password: 'securepassword',
    );

    expect($user->id())->toBe(1)
        ->and($user->name())->toBe('John Doe')
        ->and($user->companyId())->toBe(1)
        ->and($user->role())->toBe(RoleEnum::CARD_HOLDER)
        ->and($user->email())->toBe('john.doe@example.com');
});

// Testa se lança exceção ao tentar criar um usuário com ID vazio
test('it throws exception when user id is empty', function (): void {
    expect(fn () => new User(
        id: 0,
        companyId: 1,
        name: 'John Doe',
        email: new Email('john.doe@example.com'),
        role: RoleEnum::CARD_HOLDER,
        password: 'securepassword',
    ))
        ->toThrow(InvalidUserAttributeException::class, 'User ID must be a positive integer.');
});

// Testa se lança exceção ao tentar criar um usuário com nome vazio
test('it throws exception when user name is empty', function (): void {
    expect(fn () => new User(
        id: 1,
        companyId: 1,
        name: '',
        email: new Email('john.doe@example.com'),
        role: RoleEnum::CARD_HOLDER,
        password: 'securepassword',
    ))
        ->toThrow(InvalidUserAttributeException::class, 'User name cannot be empty.');
});

// Testa se lança exceção ao tentar criar um usuário com nome contendo apenas espaços em branco
test('it throws exception when user name is blank', function (): void {
    expect(fn () => new User(
        id: 1,
        companyId: 1,
        name: '   ',
        email: new Email('john.doe@example.com'),
        role: RoleEnum::CARD_HOLDER,
        password: 'securepassword',
    ))
        ->toThrow(InvalidUserAttributeException::class, 'User name cannot be empty.');
});

// Testa se lança exceção ao tentar criar um usuário com e-mail inválido
test('it throws exception when user email is invalid', function (): void {
    expect(fn () => new User(
        id: 1,
        companyId: 1,
        name: 'John Doe',
        email: new Email('invalid-email'),
        role: RoleEnum::CARD_HOLDER,
        password: 'securepassword',
    ))
        ->toThrow(InvalidArgumentException::class, 'Invalid email address.');
});
