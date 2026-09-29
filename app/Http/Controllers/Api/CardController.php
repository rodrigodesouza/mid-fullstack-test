<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Card\GetAvailableCard;
use Illuminate\Http\JsonResponse;

final readonly class CardController
{
    public function __construct(
        private GetAvailableCard $getAvailableCard,
    ) {}

    public function __invoke(string $card_token): JsonResponse
    {
        return response()->json(
            $this->getAvailableCard->execute($card_token)
        );
    }
}
