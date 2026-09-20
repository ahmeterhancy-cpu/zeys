<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Ürün formu.
 *
 * DİKKAT: min_price, max_price ve total_stock BİLEREK yok. Bunlar
 * listeleme önbelleğidir; varyant kaydedildiğinde gözlemci yeniden
 * hesaplar. Forma konulsaydı yönetici fiyatı değiştirdiğini sanır,
 * değeri ilk varyant kaydında sessizce geri alınırdı.
 *
 * Fiyat ve stok VARYANT sekmesinde, her kombinasyonun kendisinde.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs([

                Tab::make('Genel')->schema([
                    TextInput::make('name')
                        ->label('Ürün adı')
                        ->required()
                        ->maxLength(190)
                        ->columnSpanFull(),

                    TextInput::make('slug')
                        ->label('Adres eki')
                        ->helperText('Boş bırakırsanız ürün adından türetilir.')
                        ->maxLength(190),

                    TextInput::make('base_sku')
                        ->label('SKU kökü')
                        ->helperText('Varyant SKU\'ları bundan üretilir: ZEYS-001-M-SIYAH')
                        ->maxLength(60),

                    Select::make('collection_id')
                        ->label('Koleksiyon')
                        ->relationship('collection', 'name')
                        ->searchable()
                        ->preload(),

                    Select::make('categories')
                        ->label('Kategoriler')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->preload(),

                    Textarea::make('short_description')
                        ->label('Kısa açıklama')
                        ->rows(2)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Açıklama')
                        ->rows(6)
                        ->columnSpanFull(),
                ])->columns(2),

                Tab::make('Varyantlar')->schema([
                    Section::make('Eksenler')
                        ->description(
                            'Önce eksenleri ve değerlerini tanımlayın (Beden, Renk) ve kaydedin. '
                            .'Sonra üstteki "Kombinasyonları üret" düğmesiyle her Beden × Renk '
                            .'birleşimi için bir SKU oluşturulur. Fiyat ve stok aşağıdaki '
                            .'varyant tablosunda, her kombinasyonun kendisinde tutulur.'
                        )
                        ->schema([
                            Repeater::make('options')
                                ->label('Eksen')
                                ->relationship('options')
                                ->orderColumn('position')
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->addActionLabel('Eksen ekle')
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Eksen adı')
                                        ->placeholder('Beden / Renk')
                                        ->required(),

                                    Select::make('kind')
                                        ->label('Gösterim')
                                        ->options([
                                            'text' => 'Metin (beden gibi)',
                                            'color' => 'Renk noktası',
                                        ])
                                        ->default('text')
                                        ->required(),

                                    Repeater::make('values')
                                        ->label('Değerler')
                                        ->relationship('values')
                                        ->orderColumn('position')
                                        ->itemLabel(fn (array $state): ?string => $state['value'] ?? null)
                                        ->addActionLabel('Değer ekle')
                                        ->schema([
                                            TextInput::make('value')
                                                ->label('Değer')
                                                ->placeholder('S / M / L — Siyah / Bej')
                                                ->required(),

                                            TextInput::make('color_hex')
                                                ->label('Renk kodu')
                                                ->placeholder('#1c1a15')
                                                ->helperText('Yalnızca renk ekseninde.')
                                                ->maxLength(9),
                                        ])
                                        ->columns(2)
                                        ->columnSpanFull()
                                        ->defaultItems(0),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->columnSpanFull(),
                        ]),
                ]),

                Tab::make('Görsel')->schema([
                    FileUpload::make('hero_image')
                        ->label('Kapak görseli')
                        ->image()
                        ->directory('urunler')
                        ->columnSpanFull(),

                    TextInput::make('badge')
                        ->label('Rozet')
                        ->placeholder('Yeni / Son parçalar')
                        ->maxLength(40),

                    TextInput::make('position')
                        ->label('Sıra')
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
                        ->label('Satışta')
                        ->default(true),

                    Toggle::make('is_featured')
                        ->label('Öne çıkar'),
                ])->columns(2),

                Tab::make('Ürün bilgisi')->schema([
                    Textarea::make('material')
                        ->label('Kumaş')
                        ->placeholder('%100 pamuk')
                        ->rows(2),

                    Textarea::make('care_notes')
                        ->label('Bakım')
                        ->placeholder('30 derecede yıkayınız')
                        ->rows(2),

                    TextInput::make('model_note')
                        ->label('Model ölçüsü')
                        ->placeholder('Model 1.75 m boyunda ve M beden giymektedir.')
                        ->maxLength(190)
                        ->columnSpanFull(),

                    Select::make('size_chart_id')
                        ->label('Beden tablosu')
                        ->relationship('sizeChart', 'name')
                        ->helperText('Boş bırakılırsa kategorinin beden tablosu kullanılır.')
                        ->searchable()
                        ->preload()
                        ->columnSpanFull(),

                    Select::make('related')
                        ->label('Kombin önerisi')
                        ->relationship('related', 'name')
                        ->multiple()
                        ->preload()
                        ->columnSpanFull(),
                ])->columns(2),

                Tab::make('SEO')->schema([
                    TextInput::make('meta_title')
                        ->label('Meta başlık')
                        ->maxLength(190)
                        ->columnSpanFull(),

                    Textarea::make('meta_description')
                        ->label('Meta açıklama')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }
}
