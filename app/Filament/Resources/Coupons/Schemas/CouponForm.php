<?php

namespace App\Filament\Resources\Coupons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Kupon')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Kod')
                        ->placeholder('ZEYS20')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(60)
                        ->helperText('Müşteri bunu sepette yazacak. Büyük/küçük harf ayrımı yok.'),

                    Select::make('type')
                        ->label('İndirim türü')
                        ->options([
                            'percent' => 'Yüzde (%)',
                            'amount' => 'Tutar (TL)',
                        ])
                        ->default('percent')
                        ->required()
                        ->live(),

                    TextInput::make('value')
                        ->label(fn (Get $get) => $get('type') === 'amount' ? 'İndirim tutarı (TL)' : 'İndirim oranı (%)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->helperText('İndirim hiçbir zaman ara toplamı aşamaz; fazlası kırpılır.'),

                    TextInput::make('min_total')
                        ->label('Alt sepet tutarı (TL)')
                        ->numeric()
                        ->minValue(0)
                        ->helperText('Boş bırakılırsa sınır yok.'),
                ]),

            Section::make('Geçerlilik')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->label('Başlangıç')
                        ->seconds(false)
                        ->displayFormat('d.m.Y H:i'),

                    DateTimePicker::make('ends_at')
                        ->label('Bitiş')
                        ->seconds(false)
                        ->displayFormat('d.m.Y H:i')
                        ->after('starts_at'),

                    TextInput::make('usage_limit')
                        ->label('Kullanım sınırı')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Boş bırakılırsa sınırsız.'),

                    TextInput::make('used_count')
                        ->label('Kullanıldı')
                        ->numeric()
                        ->disabled()
                        ->helperText('Sipariş tamamlandıkça otomatik artar; elle girilmez.'),

                    Toggle::make('is_active')
                        ->label('Etkin')
                        ->default(true),
                ]),
        ]);
    }
}
