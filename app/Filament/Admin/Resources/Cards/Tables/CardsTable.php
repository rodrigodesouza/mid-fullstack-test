<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('card_token')
                    ->label('Token')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Portador'),

                TextColumn::make('status')
                    ->label('Status'),

                TextColumn::make('monthly_limit_cents')
                    ->label('Limite mensal')
                    ->formatStateUsing(
                        fn ($state): string => 'R$ '.number_format(
                            $state / 100,
                            2,
                            ',',
                            '.',
                        ),
                    ),
            ])
            ->filters([])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
