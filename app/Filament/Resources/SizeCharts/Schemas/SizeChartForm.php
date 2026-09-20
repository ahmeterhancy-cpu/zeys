<?php

namespace App\Filament\Resources\SizeCharts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Beden tablosu formu.
 *
 * `columns` ve `rows` veritabanında JSON dizisi.
 *
 * Üretecin bıraktığı hâlde ikisi de HAM JSON basan düz Textarea idi:
 * yönetici oraya normal metin yazsa veri bozulur, ürün sayfasındaki
 * tablo açılırken patlardı.
 *
 * Önce iç içe tekrarlayıcı (Repeater) denendi; Filament'in durum
 * dönüşümüyle çakışıp satırları boşaltıyordu — test yakaladı. Daha
 * gösterişli ama kırılgan bir çözümü zorlamak yerine bozulamayacak
 * olanı seçtim: satır başına bir beden, hücreler "|" ile ayrılmış.
 * Dönüşüm tek satır (explode/implode), sessizce bozulacak bir yeri yok.
 */
class SizeChartForm
{
    /** "Beden|Göğüs|Bel" -> ['Beden','Göğüs','Bel'] */
    public static function satiriBol(string $satir): array
    {
        return array_values(array_filter(
            array_map('trim', explode('|', $satir)),
            fn ($h) => $h !== ''
        ));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Section::make('Tablo')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Tablo adı')
                        ->placeholder('Üst Giyim / Alt Giyim / Ayakkabı')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('slug')
                        ->label('Adres eki')
                        ->helperText('Boş bırakılırsa addan türetilir.')
                        ->maxLength(120),

                    Textarea::make('note')
                        ->label('Not')
                        ->placeholder('Ölçüler cm cinsindendir ve giysi ölçüsüdür.')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Tablo içeriği')
                ->description('Hücreleri dik çizgi ( | ) ile ayırın. İlk sütun genelde "Beden" olur.')
                ->schema([
                    Textarea::make('columns')
                        ->label('Sütun başlıkları')
                        ->placeholder('Beden | Göğüs | Bel | Boy')
                        ->rows(2)
                        ->required()
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state) => is_array($state) ? implode(' | ', $state) : (string) $state)
                        ->dehydrateStateUsing(fn ($state) => static::satiriBol((string) $state)),

                    Textarea::make('rows')
                        ->label('Satırlar')
                        ->placeholder("S | 88 | 72 | 60\nM | 92 | 76 | 62\nL | 96 | 80 | 64")
                        ->helperText('Her satır bir beden. Hücre sayısı sütun başlığı sayısıyla aynı olmalı.')
                        ->rows(10)
                        ->required()
                        ->columnSpanFull()
                        ->formatStateUsing(function ($state) {
                            if (! is_array($state)) {
                                return (string) $state;
                            }

                            return implode("\n", array_map(
                                fn ($satir) => implode(' | ', (array) $satir),
                                $state
                            ));
                        })
                        ->dehydrateStateUsing(function ($state) {
                            $satirlar = preg_split('/\R/', (string) $state);

                            return array_values(array_filter(array_map(
                                fn ($satir) => static::satiriBol($satir),
                                $satirlar
                            ), fn ($satir) => $satir !== []));
                        }),
                ]),
        ]);
    }
}
