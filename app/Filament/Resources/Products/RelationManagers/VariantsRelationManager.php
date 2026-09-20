<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductVariant;
use App\Services\OrderStock;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Varyant matrisi.
 *
 * Her satır bir Beden × Renk kombinasyonu. Fiyat ve stok BURADA tutulur,
 * ürün seviyesinde değil. Satırlar elle eklenmez — ürün formundaki
 * eksenlerden "Kombinasyonları üret" ile oluşturulur; bu yüzden
 * "Yeni oluştur" ve "İlişkilendir" eylemleri kaldırıldı.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varyantlar (Beden × Renk)';

    protected static ?string $modelLabel = 'varyant';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')
                ->label('SKU')
                ->required()
                ->maxLength(80),

            TextInput::make('barcode')
                ->label('Barkod')
                ->maxLength(60),

            TextInput::make('price')
                ->label('Fiyat (TL)')
                ->numeric()
                ->required()
                ->minValue(0),

            TextInput::make('compare_at_price')
                ->label('Eski fiyat (TL)')
                ->helperText('Doluysa vitrinde üstü çizili gösterilir.')
                ->numeric()
                ->minValue(0),

            TextInput::make('stock')
                ->label('Stok')
                ->numeric()
                ->required()
                ->minValue(0),

            Toggle::make('is_active')
                ->label('Satışta')
                ->default(true),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('label')
                    ->label('Kombinasyon')
                    ->getStateUsing(fn (ProductVariant $kayit) => $kayit->label ?: '—')
                    ->weight('medium'),

                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable()
                    ->color('gray'),

                TextColumn::make('price')
                    ->label('Fiyat')
                    ->money('TRY', locale: 'tr')
                    ->sortable(),

                TextColumn::make('stock')
                    ->label('Stok')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state < 1 => 'danger',
                        (int) $state <= (int) config('shop.dusuk_stok_esigi') => 'warning',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('reserved')
                    ->label('Rezerve')
                    ->tooltip('Ödemesi sürmekte olan siparişlerin tuttuğu adet')
                    ->color('gray'),

                TextColumn::make('available_stock')
                    ->label('Satılabilir')
                    ->getStateUsing(fn (ProductVariant $kayit) => $kayit->available_stock)
                    ->tooltip('Stok eksi rezerve'),

                IconColumn::make('is_active')
                    ->label('Satışta')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),

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
                    BulkAction::make('stokAta')
                        ->label('Seçilenlere stok ata')
                        ->icon('heroicon-o-square-3-stack-3d')
                        ->schema([
                            TextInput::make('stok')
                                ->label('Stok adedi')
                                ->numeric()
                                ->required()
                                ->minValue(0),
                        ])
                        ->action(function (Collection $kayitlar, array $veri) {
                            $kayitlar->each->update(['stock' => (int) $veri['stok']]);

                            Notification::make()
                                ->title($kayitlar->count().' varyantın stoğu güncellendi')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Henüz varyant yok')
            ->emptyStateDescription(
                'Ürün formundaki Varyantlar sekmesinden eksenleri tanımlayıp kaydedin, '
                .'sonra üstteki "Kombinasyonları üret" düğmesini kullanın.'
            );
    }
}
