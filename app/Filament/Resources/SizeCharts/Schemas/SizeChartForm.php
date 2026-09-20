<?php

namespace App\Filament\Resources\SizeCharts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SizeChartForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('note')
                    ->columnSpanFull(),
                Textarea::make('columns')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('rows')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
