<?php

namespace App\Filament\Resources\ReturnRequests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReturnRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->required(),
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required(),
                TextInput::make('type')
                    ->required()
                    ->default('return'),
                TextInput::make('status')
                    ->required()
                    ->default('opened'),
                TextInput::make('reason')
                    ->required(),
                Textarea::make('customer_note')
                    ->columnSpanFull(),
                Textarea::make('admin_note')
                    ->columnSpanFull(),
                TextInput::make('return_carrier'),
                TextInput::make('return_tracking_number'),
                TextInput::make('exchange_carrier'),
                TextInput::make('exchange_tracking_number'),
                TextInput::make('refund_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('shipped_back_at'),
                DateTimePicker::make('received_at'),
                DateTimePicker::make('resolved_at'),
            ]);
    }
}
