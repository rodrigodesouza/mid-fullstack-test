<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Authorizations;

use App\Filament\Admin\Resources\Authorizations\Pages\ListAuthorizations;
use App\Filament\Admin\Resources\Authorizations\Pages\ViewAuthorization;
use App\Filament\Admin\Resources\Authorizations\Schemas\AuthorizationForm;
use App\Filament\Admin\Resources\Authorizations\Schemas\AuthorizationInfolist;
use App\Filament\Admin\Resources\Authorizations\Tables\AuthorizationsTable;
use App\Infrastructure\Persistence\Eloquent\Models\AuthorizationModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class AuthorizationResource extends Resource
{
    protected static ?string $model = AuthorizationModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'external_id';

    public static function form(Schema $schema): Schema
    {
        return AuthorizationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AuthorizationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuthorizationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthorizations::route('/'),
            'view' => ViewAuthorization::route('/{record}'),
        ];
    }
}
