<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Application\Card\GetAvailableCard;
use App\Application\Card\GetCardStatement;
use App\Infrastructure\Persistence\Eloquent\Models\CardModel;
use Livewire\Component;

final class MyCard extends Component
{
    /** @var array<string, mixed> */
    public array $available = [];

    /** @var array<string, mixed> */
    public array $statement = [];

    public function mount(): void
    {
        $this->loadCard();
    }

    public function loadCard(): void
    {
        $availableCard = resolve(GetAvailableCard::class);
        $statement = resolve(GetCardStatement::class);

        $card = CardModel::query()
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $this->available = $availableCard->execute(
            $card->card_token,
        );

        $this->statement = $statement->execute(
            $card->card_token,
        );
    }

    public function render()
    {
        return view('livewire.my-card')
            ->layout('components.layouts.employee');
    }
}
