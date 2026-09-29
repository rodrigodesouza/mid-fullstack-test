<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Events\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

final class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('external_id')
                    ->label('ID externo'),

                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge(),

                TextEntry::make('status')
                    ->label('Status')
                    ->badge(),

                TextEntry::make('authorization_id')
                    ->label('Authorization')
                    ->placeholder('-'),

                TextEntry::make('amount_cents')
                    ->label('Valor')
                    ->formatStateUsing(
                        fn ($state): string => $state === null
                            ? '-'
                            : 'R$ '.number_format(
                                $state / 100,
                                2,
                                ',',
                                '.',
                            ),
                    ),

                TextEntry::make('currency')
                    ->label('Moeda')
                    ->placeholder('-'),

                TextEntry::make('final')
                    ->label('Final')
                    ->formatStateUsing(
                        fn (bool $state): string => $state ? 'Yes' : 'No'
                    ),

                TextEntry::make('occurred_at')
                    ->label('Ocorrido em')
                    ->dateTime('d/m/Y H:i:s'),
            ]);
    }
}
