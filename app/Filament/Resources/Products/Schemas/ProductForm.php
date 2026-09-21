<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Ozellik;
use App\Models\OzellikDegeri;
use App\Models\Product;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
 *
 * "eksenler" ve "varsayilan_*" alanları modelde sütun DEĞİL: sayfa
 * sınıfları (CreateProduct / EditProduct) bunları ayırıp
 * App\Services\UrunVaryantlari ile matrisi eşitler.
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
                    Section::make('Özellikler')
                        ->description(
                            'Ortak listeden özellik (Beden, Renk…) ve değerlerini seçin. Kaydettiğinizde '
                            .'bütün kombinasyonlar kendiliğinden oluşur; fiyat ve stok aşağıdaki varyant '
                            .'tablosunda satır satır ya da toplu düzenlenir. Listede olmayan değeri buradan '
                            .'ekleyebilirsiniz; özellikleri Katalog → Özellikler ekranından yönetin.'
                        )
                        ->schema([
                            Repeater::make('eksenler')
                                ->hiddenLabel()
                                ->addActionLabel('Özellik ekle')
                                ->reorderable()
                                ->collapsible()
                                ->defaultItems(0)
                                ->itemLabel(function (array $state): ?string {
                                    $ozellik = filled($state['ozellik_id'] ?? null) ? Ozellik::find($state['ozellik_id']) : null;

                                    return $ozellik
                                        ? $ozellik->ad.' · '.count($state['degerler'] ?? []).' değer'
                                        : 'Yeni özellik';
                                })
                                ->columns(3)
                                ->schema([
                                    Select::make('ozellik_id')
                                        ->label('Özellik')
                                        ->options(fn () => Ozellik::orderBy('sira')->orderBy('ad')->pluck('ad', 'id'))
                                        ->required()
                                        ->live()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                        ->afterStateUpdated(fn (Set $set) => $set('degerler', []))
                                        ->createOptionForm([
                                            TextInput::make('ad')->label('Özellik adı')->placeholder('Numara, Kumaş, Boy…')->required()->unique('ozellikler', 'ad'),
                                            Select::make('tur')->label('Gösterim')->options(Ozellik::TURLER)->default('text')->required(),
                                        ])
                                        ->createOptionUsing(fn (array $data) => Ozellik::create([
                                            'ad' => $data['ad'],
                                            'tur' => $data['tur'],
                                            'sira' => (int) Ozellik::max('sira') + 1,
                                        ])->id)
                                        ->columnSpan(1),

                                    Select::make('degerler')
                                        ->label('Değerler')
                                        ->multiple()
                                        ->searchable()
                                        ->preload()
                                        ->allowHtml()
                                        ->required()
                                        ->live()
                                        ->options(fn (Get $get) => filled($get('ozellik_id'))
                                            ? OzellikDegeri::where('ozellik_id', $get('ozellik_id'))->orderBy('sira')->orderBy('id')->get()
                                                ->mapWithKeys(fn (OzellikDegeri $d) => [(string) $d->id => $d->etiket_html])->all()
                                            : [])
                                        ->disabled(fn (Get $get) => blank($get('ozellik_id')))
                                        ->placeholder('Önce özelliği seçin')
                                        ->hintActions([
                                            Action::make('tumunuSec')
                                                ->label('Tümünü seç')
                                                ->link()
                                                ->visible(fn (Get $get) => filled($get('ozellik_id')))
                                                ->action(fn (Get $get, Set $set) => $set('degerler', OzellikDegeri::where('ozellik_id', $get('ozellik_id'))
                                                    ->pluck('id')->map(fn ($id) => (string) $id)->all())),
                                            Action::make('temizle')
                                                ->label('Temizle')
                                                ->link()
                                                ->color('gray')
                                                ->visible(fn (Get $get) => filled($get('degerler')))
                                                ->action(fn (Set $set) => $set('degerler', [])),
                                        ])
                                        ->createOptionForm(fn (Get $get) => [
                                            TextInput::make('deger')->label('Yeni değer')->required()->maxLength(40),
                                            ColorPicker::make('renk_kodu')
                                                ->label('Renk')
                                                ->visible(fn () => Ozellik::find($get('ozellik_id'))?->tur === 'color'),
                                        ])
                                        ->createOptionUsing(function (array $data, Get $get) {
                                            $ozellikId = $get('ozellik_id');

                                            return (string) OzellikDegeri::firstOrCreate(
                                                ['ozellik_id' => $ozellikId, 'deger' => trim($data['deger'])],
                                                ['renk_kodu' => $data['renk_kodu'] ?? null, 'sira' => (int) OzellikDegeri::where('ozellik_id', $ozellikId)->max('sira') + 1],
                                            )->id;
                                        })
                                        ->columnSpan(2),
                                ]),

                            // Seçime göre kaç kombinasyon oluşacağı — kaydetmeden önce görünsün
                            Text::make(function (Get $get) {
                                $sayilar = collect($get('eksenler') ?? [])
                                    ->map(fn ($e) => count($e['degerler'] ?? []))
                                    ->filter();

                                if ($sayilar->isEmpty()) {
                                    return 'Henüz özellik seçilmedi. Satışa çıkmak için en az bir özellik (ör. Beden) gerekir.';
                                }

                                return $sayilar->implode(' × ').' = '.$sayilar->reduce(fn ($c, $n) => $c * $n, 1)
                                    .' kombinasyon. Kaydettiğinizde eksik olanlar eklenir; seçimden çıkanlar '
                                    .'hiç satılmadıysa silinir, satıldıysa satıştan kaldırılır.';
                            })->color('gray'),
                        ]),

                    Section::make('Yeni kombinasyonlar için başlangıç değerleri')
                        ->description('Yalnız bu kayıtta YENİ oluşacak kombinasyonlara uygulanır; var olanların fiyatı ve stoğu değişmez.')
                        ->columns(3)
                        ->schema([
                            TextInput::make('varsayilan_fiyat')
                                ->label('Satış fiyatı (TL)')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder(fn (?Product $record) => $record && (float) $record->min_price > 0
                                    ? number_format((float) $record->min_price, 2, ',', '.').' (mevcut en düşük)'
                                    : '0,00')
                                ->disabled(fn () => ! Yetki::yonetici())
                                ->helperText(fn () => Yetki::yonetici() ? null : 'Fiyatı yalnız yönetici belirler; mevcut en düşük fiyat kullanılır.'),

                            TextInput::make('varsayilan_eski_fiyat')
                                ->label('Eski fiyat (TL, isteğe bağlı)')
                                ->numeric()
                                ->minValue(0)
                                ->gt('varsayilan_fiyat')
                                ->disabled(fn () => ! Yetki::yonetici())
                                ->helperText('Doluysa vitrinde üstü çizili gösterilir.'),

                            TextInput::make('varsayilan_stok')
                                ->label('Stok (her kombinasyona)')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ]),
                ]),

                Tab::make('Görsel')->schema([
                    FileUpload::make('hero_image')
                        ->disk('public') // vitrin storage/ altından okur; .env'ye bırakılmaz
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
