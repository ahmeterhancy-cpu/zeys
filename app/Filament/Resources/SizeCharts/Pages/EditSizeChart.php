<?php

namespace App\Filament\Resources\SizeCharts\Pages;

use App\Filament\Resources\SizeCharts\SizeChartResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSizeChart extends EditRecord
{
    protected static string $resource = SizeChartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
