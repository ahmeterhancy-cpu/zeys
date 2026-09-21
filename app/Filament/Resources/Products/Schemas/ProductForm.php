<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Ozellik;
use App\Models\OzellikDegeri;
use App\Models\Product;
use App\Services\UrunVaryantlari;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Livewire;
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
            Tabs::make()->columnSpanFull()->persistTabInQueryString()->tabs([

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

                Tab::make('Varyantlar')->id('varyantlar')->schema([
                    /*
                     * Çip seçimi: kütüphanedeki her özelliğin bütün değerleri
                     * tek bakışta, tıklayıp aç/kapat. Önceki "özellik ekle →
                     * özellik seç → açılır listeden değer seç" akışı fazla adımdı.
                     */
                    Section::make('Hangi beden ve renkler var?')
                        ->key('secenekler')
                        ->description('Üründe olanlara tıklayın; kaydedince bütün kombinasyonlar kendiliğinden oluşur. Kullanmadığınız özelliği boş bırakın.')
                        ->headerActions([
                            Action::make('yeniOzellik')
                                ->label('Yeni özellik')
                                ->icon('heroicon-m-plus')
                                ->link()
                                ->modalHeading('Yeni özellik')
                                ->modalDescription('Ör. Numara, Kumaş, Boy. Bütün ürünlerde kullanılabilir.')
                                ->schema([
                                    TextInput::make('ad')->label('Özellik adı')->required()->maxLength(40)->unique('ozellikler', 'ad'),
                                    Select::make('tur')->label('Gösterim')->options(Ozellik::TURLER)->default('text')->required(),
                                ])
                                ->action(fn (array $data) => Ozellik::create([
                                    'ad' => trim($data['ad']),
                                    'tur' => $data['tur'],
                                    'sira' => (int) Ozellik::max('sira') + 1,
                                ])),
                        ])
                        ->schema(fn () => [
                            ...Ozellik::orderBy('sira')->orderBy('ad')->get()
                                ->map(fn (Ozellik $o) => static::degerCipleri($o))
                                ->all(),

                            // Seçime göre kaç kombinasyon oluşacağı — kaydetmeden önce görünsün
                            Text::make(function (Get $get) {
                                $sayilar = collect($get('secim') ?? [])->map(fn ($d) => count((array) $d))->filter();

                                if ($sayilar->isEmpty()) {
                                    return 'Henüz seçim yok. Satışa çıkmak için en az bir değer (ör. bir beden) seçin.';
                                }

                                $toplam = $sayilar->reduce(fn ($c, $n) => $c * $n, 1);

                                return ($sayilar->count() > 1 ? $sayilar->implode(' × ').' = ' : '').$toplam
                                    .' kombinasyon. Seçimden çıkardığınız hiç satılmadıysa silinir, satıldıysa satıştan kaldırılır.';
                            })->color('gray'),
                        ]),

                    Section::make('Yeni kombinasyonlar için başlangıç değerleri')
                        ->description('Yalnız bu kayıtta YENİ oluşacak kombinasyonlara uygulanır; var olanların fiyatı ve stoğu değişmez.')
                        ->columns(3)
                        ->schema([
                            TextInput::make('varsayilan_fiyat')
                                ->live(onBlur: true)
                                ->label('Satış fiyatı (TL)')
                                ->numeric()
                                ->minValue(0)
                                ->placeholder(fn (?Product $record) => $record && (float) $record->min_price > 0
                                    ? number_format((float) $record->min_price, 2, ',', '.').' (mevcut en düşük)'
                                    : '0,00')
                                ->disabled(fn () => ! Yetki::yonetici())
                                ->helperText(fn () => Yetki::yonetici() ? null : 'Fiyatı yalnız yönetici belirler; mevcut en düşük fiyat kullanılır.'),

                            TextInput::make('varsayilan_eski_fiyat')
                                ->live(onBlur: true)
                                ->label('Eski fiyat (TL, isteğe bağlı)')
                                ->numeric()
                                ->minValue(0)
                                ->gt('varsayilan_fiyat')
                                ->disabled(fn () => ! Yetki::yonetici())
                                ->helperText('Doluysa vitrinde üstü çizili gösterilir.'),

                            TextInput::make('varsayilan_stok')
                                ->live(onBlur: true)
                                ->label('Stok (her kombinasyona)')
                                ->numeric()
                                ->minValue(0)
                                ->default(0),
                        ]),

                    /*
                     * Varyant tablosu sekmenin İÇİNDE (WooCommerce gibi): her
                     * kombinasyonun fiyatı, stoğu ve görseli seçimin hemen altında.
                     * Önceden sayfanın en altında, sekmelerin dışındaydı ve
                     * gözden kaçıyordu.
                     */
                    Section::make('Kombinasyonlar — fiyat, stok, görsel')
                        ->description('Hücreye tıklayıp yazın. Görsel ve barkod için satırdaki "Ayrıntı"; birden çok satır için soldaki kutuları işaretleyip "Toplu işlemler".')
                        ->visible(fn (string $operation) => $operation === 'edit')
                        ->schema([
                            Livewire::make(VariantsRelationManager::class, fn (?Product $record) => [
                                'ownerRecord' => $record,
                                'pageClass' => EditProduct::class,
                            ])->key('varyant-tablosu'),
                        ]),

                    /*
                     * Oluştururken kombinasyon tablosu KAYDETMEDEN görünür: çiplere
                     * tıkladıkça satırlar oluşur, fiyat/stok/görsel hemen girilir.
                     * Kayıtta bu değerler oluşan varyantlara yazılır
                     * (VaryantlariEsitler::satirlariUygula).
                     */
                    Section::make('Kombinasyonlar — fiyat, stok, görsel')
                        ->description('Seçim yaptıkça satırlar burada oluşur. Boş bıraktığınız hücreye yukarıdaki başlangıç değeri yazılır.')
                        ->visible(fn (string $operation) => $operation === 'create')
                        ->schema([
                            Text::make('Yukarıdan en az bir değer seçin; kombinasyonlar burada listelenecek.')
                                ->color('gray')
                                ->visible(fn (Get $get) => blank($get('kombinasyonlar'))),

                            Repeater::make('kombinasyonlar')
                                ->hiddenLabel()
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->default([])
                                ->visible(fn (Get $get) => filled($get('kombinasyonlar')))
                                ->table([
                                    TableColumn::make('Kombinasyon')->width('9rem'),
                                    TableColumn::make('Fiyat (TL)'),
                                    TableColumn::make('Eski fiyat'),
                                    TableColumn::make('Stok')->width('7rem'),
                                    TableColumn::make('Görsel')->width('10rem'),
                                    TableColumn::make('Satışta')->width('5rem'),
                                ])
                                ->schema([
                                    Hidden::make('anahtar'),
                                    Hidden::make('etiket'),
                                    Text::make(fn (Get $get) => $get('etiket'))->weight('medium'),
                                    TextInput::make('price')->hiddenLabel()->numeric()->minValue(0)
                                        ->placeholder(fn (Get $get) => filled($get('../../varsayilan_fiyat')) ? (string) $get('../../varsayilan_fiyat') : '0,00')
                                        ->disabled(fn () => ! Yetki::yonetici()),
                                    TextInput::make('compare_at_price')->hiddenLabel()->numeric()->minValue(0)
                                        ->placeholder(fn (Get $get) => filled($get('../../varsayilan_eski_fiyat')) ? (string) $get('../../varsayilan_eski_fiyat') : '—')
                                        ->disabled(fn () => ! Yetki::yonetici()),
                                    TextInput::make('stock')->hiddenLabel()->numeric()->minValue(0)
                                        ->placeholder(fn (Get $get) => (string) ($get('../../varsayilan_stok') ?: 0)),
                                    FileUpload::make('image')
                                        ->hiddenLabel()
                                        ->disk('public') // vitrin storage/ altından okur
                                        ->directory('urunler/varyant')
                                        ->image()
                                        ->maxSize(4096)
                                        ->panelLayout('compact'),
                                    Toggle::make('is_active')->hiddenLabel()->default(true),
                                ]),
                        ]),
                ]),

                Tab::make('Görsel')->id('gorsel')->schema([
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

                    // Galeri (genel + renk başına fotoğraflar) da bu sekmede
                    Section::make('Galeri')
                        ->description('Renge bağlı fotoğraf, o renk seçilince vitrinde ana görsel olur. Birden çok fotoğraf için "Toplu yükle".')
                        ->visible(fn (string $operation) => $operation === 'edit')
                        ->columnSpanFull()
                        ->schema([
                            Livewire::make(MediaRelationManager::class, fn (?Product $record) => [
                                'ownerRecord' => $record,
                                'pageClass' => EditProduct::class,
                            ])->key('galeri-tablosu'),
                        ]),
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

    /**
     * Bir özelliğin değerleri çip olarak (renkte nokta ile). Durum yolu
     * secim.{ozellik_id}; sayfa sınıfları bunu eksenlere çevirir.
     */
    protected static function degerCipleri(Ozellik $ozellik): CheckboxList
    {
        $yol = 'secim.'.$ozellik->id;

        return CheckboxList::make($yol)
            ->label($ozellik->ad)
            ->options(fn () => OzellikDegeri::where('ozellik_id', $ozellik->id)->orderBy('sira')->orderBy('id')->get()
                ->mapWithKeys(fn (OzellikDegeri $d) => [(string) $d->id => $d->etiket_html])->all())
            ->allowHtml()
            ->bulkToggleable()
            ->live()
            ->default([])
            ->afterStateUpdated(fn (Get $get, Set $set) => static::kombinasyonlariKur($get, $set))
            ->extraAttributes(['class' => 'zeys-cipler'])
            ->hintAction(
                Action::make('degerEkle'.$ozellik->id)
                    ->label('Değer ekle')
                    ->icon('heroicon-m-plus')
                    ->link()
                    ->modalHeading($ozellik->ad.' — yeni değer')
                    ->modalWidth('sm')
                    ->schema([
                        TextInput::make('deger')->label('Değer')->required()->maxLength(40)
                            ->placeholder($ozellik->tur === 'color' ? 'Zümrüt' : '3XL'),
                        ColorPicker::make('renk_kodu')->label('Renk')->visible($ozellik->tur === 'color'),
                    ])
                    ->action(function (array $data, Get $get, Set $set) use ($ozellik, $yol) {
                        $deger = OzellikDegeri::firstOrCreate(
                            ['ozellik_id' => $ozellik->id, 'deger' => trim($data['deger'])],
                            ['renk_kodu' => $data['renk_kodu'] ?? null, 'sira' => (int) OzellikDegeri::where('ozellik_id', $ozellik->id)->max('sira') + 1],
                        );

                        // Eklenen değer seçili gelsin
                        $set($yol, array_values(array_unique([...(array) $get($yol), (string) $deger->id])));
                        static::kombinasyonlariKur($get, $set);
                    }),
            );
    }

    /**
     * Oluşturma ekranında seçime göre tablo satırlarını kur; önceden
     * girilen satır değerleri (anahtar aynıysa) korunur. Düzenlemede
     * bu tablo yok (varyant tablosu kayıtlı satırlarla çalışır).
     */
    protected static function kombinasyonlariKur(Get $get, Set $set): void
    {
        $mevcut = collect((array) $get('kombinasyonlar'))->keyBy('anahtar');
        $satirlar = [];

        foreach (app(UrunVaryantlari::class)->onizleme((array) $get('secim')) as $k) {
            $satirlar['k'.$k['anahtar']] = [
                ...($mevcut->get($k['anahtar']) ?? [
                    'price' => null,
                    'compare_at_price' => null,
                    'stock' => null,
                    'image' => [],
                    'is_active' => true,
                ]),
                'anahtar' => $k['anahtar'],
                'etiket' => $k['etiket'],
            ];
        }

        $set('kombinasyonlar', $satirlar);
    }
}
