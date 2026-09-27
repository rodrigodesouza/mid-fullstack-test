<?php

declare(strict_types=1);

namespace App\Domain\Card\Enums;

enum CardStatusEnum: string
{
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';
}
