<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Transactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo'),

                TextColumn::make('amount_cents')
                    ->label('Valor')
                    ->numeric(),

                TextColumn::make('limit_month')
                    ->label('Mês limite'),

                TextColumn::make('reference')
                    ->label('Referência'),

                TextColumn::make('occurred_at')
                    ->label('Ocorrido em')
                    ->dateTime(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime(),
            ])
            ->filters([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
