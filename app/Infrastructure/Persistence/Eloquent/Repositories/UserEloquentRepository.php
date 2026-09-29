<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entity\User;
use App\Domain\User\Enums\RoleEnum;
use App\Domain\User\Repositories\UserRepository;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

final class UserEloquentRepository implements UserRepository
{
    public function findById(int $id): ?User
    {
        $user = UserModel::query()->find($id);

        if ($user === null) {
            return null;
        }

        return new User(
            id: $user->id,
            companyId: $user->company_id,
            name: $user->name,
            email: new Email($user->email),
            role: RoleEnum::from($user->role),
            password: $user->password,
        );
    }
}
