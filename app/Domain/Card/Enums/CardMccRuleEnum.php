<?php

declare(strict_types=1);

namespace App\Domain\Card\Enums;

enum CardMccRuleEnum: string
{
    case BLOCKED = 'blocked';
    case ALLOWED = 'allowed';
}
