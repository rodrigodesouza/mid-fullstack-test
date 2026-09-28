<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Services;

final class CaptureTolerance
{
    private const TOLERANCE_PERCENTAGES = [
        '7011' => 20,
        '5812' => 20,
        '5541' => 20,
    ];

    public function percentageFor(string $mcc): int
    {
        return self::TOLERANCE_PERCENTAGES[$mcc] ?? 0;
    }
}
