<?php

namespace App\Filament\Resources\SizeCharts\Pages;

use App\Filament\Resources\SizeCharts\SizeChartResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSizeCharts extends ListRecords
{
    protected static string $resource = SizeChartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
