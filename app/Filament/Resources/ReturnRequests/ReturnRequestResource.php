<?php

namespace App\Filament\Resources\ReturnRequests;

use App\Filament\Resources\ReturnRequests\Pages\CreateReturnRequest;
use App\Filament\Resources\ReturnRequests\Pages\EditReturnRequest;
use App\Filament\Resources\ReturnRequests\Pages\ListReturnRequests;
use App\Filament\Resources\ReturnRequests\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\ReturnRequests\Schemas\ReturnRequestForm;
use App\Filament\Resources\ReturnRequests\Tables\ReturnRequestsTable;
use App\Models\ReturnRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReturnRequestResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static ?string $navigationLabel = 'İade / Değişim';

    protected static ?string $modelLabel = 'talep';

    protected static ?string $pluralModelLabel = 'talepler';

    protected static ?int $navigationSort = 2;

    protected static string|\UnitEnum|null $navigationGroup = 'Satış';

    public static function form(Schema $schema): Schema
    {
        return ReturnRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReturnRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    /** Bekleyen talep sayisi menude rozet olarak gorunur. */
    public static function getNavigationBadge(): ?string
    {
        $acik = static::getModel()::whereNotIn('status', ['completed', 'rejected', 'cancelled'])->count();

        return $acik > 0 ? (string) $acik : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Sonuclanmamis iade/degisim talepleri';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturnRequests::route('/'),
            'create' => CreateReturnRequest::route('/create'),
            'edit' => EditReturnRequest::route('/{record}/edit'),
        ];
    }
}
