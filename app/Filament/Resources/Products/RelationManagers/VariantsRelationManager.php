<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Services\OrderStock;
use App\Support\Yetki;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Varyant tablosu — WooCommerce'in "Varyasyonlar" bölümü gibi.
 *
 * Satırlar elle eklenmez: ürün formundaki Varyantlar sekmesinde özellik
 * ve değer seçilip kaydedilince kendiliğinden oluşur. Fiyat, eski fiyat,
 * stok ve satış durumu tabloda doğrudan (satır içinde) düzenlenir; çok
 * satırı birden değiştirmek için toplu eylemler var.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varyantlar';

    protected static ?string $modelLabel = 'varyant';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')
                ->label('SKU')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(80),

            TextInput::make('barcode')
                ->label('Barkod')
                ->maxLength(60),

            TextInput::make('price')
                ->disabled(fn () => ! Yetki::yonetici()) // fiyatı yalnız yönetici değiştirir
                ->label('Fiyat (TL)')
                ->numeric()
                ->required()
                ->minValue(0),

            TextInput::make('compare_at_price')
                ->disabled(fn () => ! Yetki::yonetici())
                ->label('Eski fiyat (TL)')
                ->helperText('Doluysa vitrinde üstü çizili gösterilir.')
                ->numeric()
                ->minValue(0)
                ->gt('price'),

            TextInput::make('stock')
                ->label('Stok')
                ->numeric()
                ->required()
                ->minValue(fn (?ProductVariant $record) => $record?->reserved ?? 0)
                ->helperText(fn (?ProductVariant $record) => $record && $record->reserved > 0
                    ? $record->reserved.' adet ödemesi beklenen siparişte ayrılmış; stok bunun altına inemez.'
                    : null),

            Toggle::make('is_active')
                ->label('Satışta')
                ->default(true),

            FileUpload::make('image')
                ->disk('public') // vitrin storage/ altından okur; .env'ye bırakılmaz
                ->label('Varyant görseli')
                ->helperText('İsteğe bağlı. Müşteri bu kombinasyonu seçince ana görsel olur; sepette ve siparişte de bu görünür. Boşsa renk galerisi / ürün kapağı kullanılır.')
                ->image()
                ->directory('urunler/varyant')
                ->maxSize(4096)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->defaultSort('position')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('optionValues.option'))
            ->paginated([25, 50, 100, 'all'])
            ->description('Fiyat, eski fiyat ve stok hücrelerine tıklayıp doğrudan yazabilirsiniz; Enter ya da dışarı tıklayınca kaydedilir.')
            ->columns([
                ImageColumn::make('image')
                    ->disk('public')
                    ->label('Görsel')
                    ->square()
                    ->size(40)
                    ->placeholder('—'),

                TextColumn::make('label')
                    ->label('Kombinasyon')
                    ->getStateUsing(fn (ProductVariant $kayit) => $kayit->label ?: '—')
                    ->description(fn (ProductVariant $kayit) => $kayit->sku)
                    ->weight('medium')
                    // Gruplu: orWhere ürün kapsamının (product_id) dışına taşmasın
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($q) => $q
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('optionValues', fn ($v) => $v->where('value', 'like', "%{$search}%")))),

                TextInputColumn::make('price')
                    ->label('Fiyat (TL)')
                    ->type('number')
                    ->step('0.01')
                    ->rules(['required', 'numeric', 'min:0'])
                    ->disabled(fn () => ! Yetki::yonetici())
                    ->sortable(),

                TextInputColumn::make('compare_at_price')
                    ->label('Eski fiyat')
                    ->type('number')
                    ->step('0.01')
                    ->placeholder('—')
                    ->rules(fn (ProductVariant $record) => ['nullable', 'numeric', 'gt:'.$record->price])
                    ->disabled(fn () => ! Yetki::yonetici()),

                TextInputColumn::make('stock')
                    ->label('Stok')
                    ->type('number')
                    ->rules(fn (ProductVariant $record) => ['required', 'integer', 'min:'.$record->reserved])
                    ->sortable(),

                TextColumn::make('available_stock')
                    ->label('Satılabilir')
                    ->getStateUsing(fn (ProductVariant $kayit) => $kayit->available_stock)
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state < 1 => 'danger',
                        (int) $state <= (int) config('shop.dusuk_stok_esigi') => 'warning',
                        default => 'success',
                    })
                    ->description(fn (ProductVariant $kayit) => $kayit->reserved > 0 ? $kayit->reserved.' rezerve' : null)
                    ->tooltip('Stok eksi ödemesi beklenen siparişlerde ayrılan adet'),

                ToggleColumn::make('is_active')
                    ->label('Satışta'),
            ])
            ->filters([
                SelectFilter::make('deger')
                    ->label('Değer')
                    ->multiple()
                    ->options(fn () => ProductOptionValue::query()
                        ->whereHas('option', fn ($q) => $q->where('product_id', $this->getOwnerRecord()->id))
                        ->with('option')
                        ->get()
                        ->groupBy(fn ($v) => $v->option->name)
                        ->map(fn ($grup) => $grup->pluck('value', 'id')->all())
                        ->all())
                    // Seçilen değerlerin HEPSİNİ içeren kombinasyonlar (ör. Siyah + M)
                    ->query(function (Builder $query, array $data) {
                        foreach ($data['values'] ?? [] as $id) {
                            $query->whereHas('optionValues', fn ($v) => $v->whereKey($id));
                        }
                    }),

                TernaryFilter::make('is_active')->label('Satışta'),

                TernaryFilter::make('stoksuz')
                    ->label('Stok')
                    ->trueLabel('Satılabilir stoğu yok')
                    ->falseLabel('Stokta olanlar')
                    ->queries(
                        true: fn (Builder $q) => $q->whereColumn('stock', '<=', 'reserved'),
                        false: fn (Builder $q) => $q->whereColumn('stock', '>', 'reserved'),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Ayrıntı')->icon('heroicon-o-pencil-square'),

                Action::make('rezervOnar')
                    ->label('Rezervi onar')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription(
                        'Rezerve adedi, bu varyantı bekleyen açık siparişlerin toplamına '
                        .'eşitlenir. Rezerv şişmiş ya da takılı kalmışsa kullanın.'
                    )
                    ->visible(fn (ProductVariant $kayit) => $kayit->reserved > 0)
                    ->action(function (ProductVariant $kayit) {
                        app(OrderStock::class)->reconcile($kayit);

                        Notification::make()
                            ->title('Rezerv onarıldı')
                            ->body('Yeni rezerve adedi: '.$kayit->fresh()->reserved)
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('fiyatAta')
                        ->label('Fiyat ata')
                        ->icon('heroicon-o-banknotes')
                        ->authorize(fn () => Yetki::yonetici())
                        ->schema([
                            TextInput::make('fiyat')->label('Fiyat (TL)')->numeric()->required()->minValue(0),
                        ])
                        ->action(fn (Collection $kayitlar, array $data) => $this->toplu($kayitlar, fn (ProductVariant $v) => $v->update(['price' => (float) $data['fiyat']]), ':n varyantın fiyatı güncellendi'))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('fiyatYuzde')
                        ->label('Fiyatı yüzde değiştir')
                        ->icon('heroicon-o-receipt-percent')
                        ->authorize(fn () => Yetki::yonetici())
                        ->schema([
                            Radio::make('yon')->label('İşlem')->options(['artir' => 'Artır', 'azalt' => 'Azalt'])->default('artir')->inline()->required(),
                            TextInput::make('yuzde')->label('Yüzde')->numeric()->required()->minValue(0)->maxValue(90)->suffix('%'),
                            Toggle::make('eskiyi_koru')
                                ->label('İndirimde mevcut fiyatı "eski fiyat" olarak göster')
                                ->helperText('Azaltırken işaretlenirse vitrinde üstü çizili eski fiyat görünür.'),
                        ])
                        ->action(function (Collection $kayitlar, array $data) {
                            $carpan = $data['yon'] === 'artir' ? 1 + $data['yuzde'] / 100 : 1 - $data['yuzde'] / 100;

                            $this->toplu($kayitlar, function (ProductVariant $v) use ($carpan, $data) {
                                $yeni = round((float) $v->price * $carpan, 2);
                                $degisiklik = ['price' => $yeni];

                                if ($data['yon'] === 'azalt' && ! empty($data['eskiyi_koru'])) {
                                    $degisiklik['compare_at_price'] = $v->compare_at_price ?: $v->price;
                                }

                                $v->update($degisiklik);
                            }, ':n varyantın fiyatı güncellendi');
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('eskiFiyat')
                        ->label('Eski fiyat ata / kaldır')
                        ->icon('heroicon-o-tag')
                        ->authorize(fn () => Yetki::yonetici())
                        ->schema([
                            TextInput::make('eski')->label('Eski fiyat (TL)')->numeric()->minValue(0)
                                ->helperText('Boş bırakılırsa eski fiyat kaldırılır (indirim biter).'),
                        ])
                        ->action(function (Collection $kayitlar, array $data) {
                            $eski = filled($data['eski'] ?? null) ? (float) $data['eski'] : null;
                            $atlanan = $eski !== null ? $kayitlar->filter(fn ($v) => $eski <= (float) $v->price)->count() : 0;

                            $this->toplu(
                                $kayitlar->filter(fn ($v) => $eski === null || $eski > (float) $v->price),
                                fn (ProductVariant $v) => $v->update(['compare_at_price' => $eski]),
                                ':n varyantın eski fiyatı güncellendi',
                                $atlanan ? $atlanan.' satırda eski fiyat satış fiyatından büyük olmadığı için atlandı.' : null,
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('stokAta')
                        ->label('Stok ata')
                        ->icon('heroicon-o-square-3-stack-3d')
                        ->schema([
                            Radio::make('islem')->label('İşlem')->options(['ata' => 'Şu adede eşitle', 'ekle' => 'Mevcuda ekle'])->default('ata')->inline()->required(),
                            TextInput::make('stok')->label('Adet')->numeric()->required()->minValue(0),
                        ])
                        ->action(function (Collection $kayitlar, array $data) {
                            $adet = (int) $data['stok'];
                            $sinirda = 0;

                            $this->toplu($kayitlar, function (ProductVariant $v) use ($adet, $data, &$sinirda) {
                                $yeni = ($data['islem'] ?? 'ata') === 'ekle' ? $v->stock + $adet : $adet;

                                // Ödemesi beklenen siparişlerin ayırdığı adedin altına inilmez
                                if ($yeni < $v->reserved) {
                                    $yeni = $v->reserved;
                                    $sinirda++;
                                }

                                $v->update(['stock' => $yeni]);
                            }, ':n varyantın stoğu güncellendi');

                            if ($sinirda) {
                                Notification::make()
                                    ->title($sinirda.' satırda stok rezerv adedine eşitlendi')
                                    ->body('Ödemesi beklenen siparişlerin ayırdığı adedin altına inilemez.')
                                    ->warning()
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('satisaAc')
                        ->label('Satışa aç')
                        ->icon('heroicon-o-eye')
                        ->action(fn (Collection $kayitlar) => $this->toplu($kayitlar, fn (ProductVariant $v) => $v->update(['is_active' => true]), ':n varyant satışa açıldı'))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('satistanKaldir')
                        ->label('Satıştan kaldır')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->action(fn (Collection $kayitlar) => $this->toplu($kayitlar, fn (ProductVariant $v) => $v->update(['is_active' => false]), ':n varyant satıştan kaldırıldı'))
                        ->deselectRecordsAfterCompletion(),

                    /*
                     * Aynı rengin bütün bedenleri çoğu zaman aynı fotoğrafı
                     * paylaşır: "Siyah" satırları seçilip tek yüklemeyle atanır.
                     */
                    BulkAction::make('gorselAta')
                        ->label('Görsel ata')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload::make('gorsel')
                                ->disk('public') // vitrin storage/ altından okur; .env'ye bırakılmaz
                                ->label('Görsel')
                                ->image()
                                ->directory('urunler/varyant')
                                ->maxSize(4096)
                                ->required(),
                        ])
                        ->action(fn (Collection $kayitlar, array $data) => $this->toplu($kayitlar, fn (ProductVariant $v) => $v->update(['image' => $data['gorsel']]), ':n varyanta görsel atandı'))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('gorselKaldir')
                        ->label('Görseli kaldır')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalDescription('Varyant görseli kaldırılır; vitrin renk galerisine / ürün kapağına döner. Dosya silinmez.')
                        ->action(fn (Collection $kayitlar) => $this->toplu($kayitlar, fn (ProductVariant $v) => $v->update(['image' => null]), ':n varyantın görseli kaldırıldı'))
                        ->deselectRecordsAfterCompletion(),
                ])->label('Toplu işlemler'),
            ])
            ->emptyStateHeading('Henüz varyant yok')
            ->emptyStateDescription(
                'Yukarıdaki Varyantlar sekmesinden özellik (Beden, Renk…) ve değerlerini seçip '
                .'kaydedin; bütün kombinasyonlar kendiliğinden oluşur.'
            );
    }

    /**
     * Toplu işlem: her satır model üzerinden güncellenir (gözlemci ürün
     * önbelleğini ve "stokta" bildirimlerini işlesin), sonunda tek bildirim.
     */
    private function toplu(Collection $kayitlar, \Closure $islem, string $mesaj, ?string $ek = null): void
    {
        $kayitlar->each($islem);

        Notification::make()
            ->title(str_replace(':n', (string) $kayitlar->count(), $mesaj))
            ->body($ek)
            ->success()
            ->send();
    }
}
