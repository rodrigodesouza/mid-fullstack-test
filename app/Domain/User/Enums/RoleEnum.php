<?php

declare(strict_types=1);

namespace App\Domain\User\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case CARD_HOLDER = 'card_holder';
}
