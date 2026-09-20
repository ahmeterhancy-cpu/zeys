<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Koleksiyon')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Koleksiyon adı')
                        ->placeholder('Yaz 26 / Basic')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('slug')
                        ->label('Adres eki')
                        ->helperText('Boş bırakılırsa addan türetilir.')
                        ->maxLength(120),

                    Textarea::make('description')
                        ->label('Açıklama')
                        ->placeholder('Hafif kumaşlar, açık tonlar.')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Görünüm')
                ->columns(2)
                ->schema([
                    FileUpload::make('image')
                        ->label('Koleksiyon görseli')
                        ->image()
                        ->directory('koleksiyonlar')
                        ->columnSpanFull(),

                    TextInput::make('position')
                        ->label('Sıra')
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
                        ->label('Vitrinde göster')
                        ->default(true),
                ]),
        ]);
    }
}
