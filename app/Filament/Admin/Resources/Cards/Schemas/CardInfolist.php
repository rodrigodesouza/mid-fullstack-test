<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class CardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('card_token')
                    ->label('Token'),

                TextEntry::make('user.name')
                    ->label('Portador'),

                TextEntry::make('status')
                    ->label('Status'),

                TextEntry::make('monthly_limit_cents')
                    ->label('Limite mensal')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            $state / 100,
                            2,
                            ',',
                            '.',
                        ),
                    ),
            ]);
    }
}
