<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Card\GetCardStatement;
use Illuminate\Http\JsonResponse;

final readonly class StatementController
{
    public function __construct(
        private GetCardStatement $statement,
    ) {}

    public function __invoke(string $card_token): JsonResponse
    {
        return response()->json(
            $this->statement->execute($card_token)
        );
    }
}
