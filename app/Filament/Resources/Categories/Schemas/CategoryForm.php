<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Kategori')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Kategori adı')
                        ->placeholder('Elbise / Üst Giyim / Alt Giyim')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('slug')
                        ->label('Adres eki')
                        ->helperText('Boş bırakılırsa addan türetilir.')
                        ->maxLength(120),

                    Select::make('parent_id')
                        ->label('Üst kategori')
                        ->relationship('parent', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Boş bırakılırsa ana kategori olur.'),

                    Select::make('size_chart_id')
                        ->label('Beden tablosu')
                        ->relationship('sizeChart', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Boş bırakılırsa üst kategorininki kullanılır.'),

                    Textarea::make('description')
                        ->label('Açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Görünüm')
                ->columns(2)
                ->schema([
                    FileUpload::make('image')
                        ->label('Kategori görseli')
                        ->image()
                        ->directory('kategoriler')
                        ->columnSpanFull(),

                    TextInput::make('position')
                        ->label('Sıra')
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
                        ->label('Vitrinde göster')
                        ->default(true),

                    Toggle::make('is_featured')
                        ->label('Öne çıkar'),
                ]),

            Section::make('SEO')
                ->schema([
                    TextInput::make('meta_title')
                        ->label('Meta başlık')
                        ->maxLength(190),

                    Textarea::make('meta_description')
                        ->label('Meta açıklama')
                        ->rows(2),
                ]),
        ]);
    }
}
