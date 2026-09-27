<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entity\User;
use App\Domain\User\Enums\RoleEnum;
use App\Domain\User\Exceptions\InvalidUserAttributeException;

// Testa se usuário pode ser instanciada corretamente
test('it can instantiate a user with initial balance', function () {
    $user = new User(
        id: 'user_john',
        companyId: 1,
        name: 'John Doe',
        email: new Email('john.doe@example.com'),
        password: 'securepassword',
        role: RoleEnum::CARD_HOLE,
    );

    expect($user->id())->toBe('user_john')
        ->and($user->name())->toBe('John Doe')
        ->and($user->companyId())->toBe(1)
        ->and($user->role())->toBe(RoleEnum::CARD_HOLE)
        ->and($user->email())->toBe('john.doe@example.com');
});

// Testa se lança exceção ao tentar criar um usuário com ID vazio
test('it throws exception when user id is empty', function () {
    expect(fn () => new User(
        id: '',
        name: 'John Doe',
        companyId: 1,
        email: new Email('john.doe@example.com'),
        password: 'securepassword',
        role: RoleEnum::CARD_HOLE,
    ))
        ->toThrow(InvalidUserAttributeException::class, 'User ID cannot be empty.');
});

// Testa se lança exceção ao tentar criar um usuário com ID contendo apenas espaços em branco
test('it throws exception when user id is blank', function () {
    expect(fn () => new User(
        id: '   ',
        name: 'John Doe',
        companyId: 1,
        email: new Email('john.doe@example.com'),
        password: 'securepassword',
        role: RoleEnum::CARD_HOLE,
    ))
        ->toThrow(InvalidUserAttributeException::class, 'User ID cannot be empty.');
});

// Testa se lança exceção ao tentar criar um usuário com nome vazio
test('it throws exception when user name is empty', function () {
    expect(fn () => new User(
        id: 'user_john',
        name: '',
        companyId: 1,
        email: new Email('john.doe@example.com'),
        password: 'securepassword',
        role: RoleEnum::CARD_HOLE,
    ))
        ->toThrow(InvalidUserAttributeException::class, 'User name cannot be empty.');
});

// Testa se lança exceção ao tentar criar um usuário com nome contendo apenas espaços em branco
test('it throws exception when user name is blank', function () {
    expect(fn () => new User(
        id: 'user_john',
        name: '   ',
        companyId: 1,
        email: new Email('john.doe@example.com'),
        password: 'securepassword',
        role: RoleEnum::CARD_HOLE,
    ))
        ->toThrow(InvalidUserAttributeException::class, 'User name cannot be empty.');
});

// Testa se lança exceção ao tentar criar um usuário com e-mail inválido
test('it throws exception when user email is invalid', function () {
    expect(fn () => new User(
        id: 'user_john',
        name: 'John Doe',
        companyId: 1,
        email: new Email('invalid-email'),
        password: 'securepassword',
        role: RoleEnum::CARD_HOLE,
    ))
        ->toThrow(InvalidArgumentException::class, 'Invalid email address.');
});
