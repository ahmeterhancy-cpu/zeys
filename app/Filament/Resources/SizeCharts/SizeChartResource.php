<?php

namespace App\Filament\Resources\SizeCharts;

use App\Filament\Concerns\YalnizYonetici;
use App\Filament\Resources\SizeCharts\Pages\CreateSizeChart;
use App\Filament\Resources\SizeCharts\Pages\EditSizeChart;
use App\Filament\Resources\SizeCharts\Pages\ListSizeCharts;
use App\Filament\Resources\SizeCharts\Schemas\SizeChartForm;
use App\Filament\Resources\SizeCharts\Tables\SizeChartsTable;
use App\Models\SizeChart;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SizeChartResource extends Resource
{
    use YalnizYonetici;

    protected static ?string $model = SizeChart::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Beden Tabloları';

    protected static ?string $modelLabel = 'beden tablosu';

    protected static ?string $pluralModelLabel = 'beden tabloları';

    protected static ?int $navigationSort = 4;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    public static function form(Schema $schema): Schema
    {
        return SizeChartForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SizeChartsTable::configure($table);
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
            'index' => ListSizeCharts::route('/'),
            'create' => CreateSizeChart::route('/create'),
            'edit' => EditSizeChart::route('/{record}/edit'),
        ];
    }
}
