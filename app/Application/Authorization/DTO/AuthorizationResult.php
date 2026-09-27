<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTO;

use App\Domain\Authorization\Entity\Authorization;
use App\Domain\Authorization\Enums\AuthorizationDecisionEnum;
use App\Domain\Authorization\Enums\AuthorizationReasonEnum;

final readonly class AuthorizationResult
{
    public function __construct(
        public AuthorizationDecisionEnum $decision,
        public ?AuthorizationReasonEnum $reason,
        public ?Authorization $authorization,
    ) {}
}
