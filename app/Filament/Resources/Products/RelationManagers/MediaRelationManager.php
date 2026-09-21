<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\ProductMedia;
use App\Models\ProductOptionValue;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Ürün galerisi.
 *
 * Görsel bir RENGE bağlanabilir: vitrinde o renk seçilince galeri o
 * rengin fotoğraflarına geçer. Renge bağlanmayan görsel "genel"dir ve
 * renk seçilmeden önce gösterilir.
 *
 * Tek tek yüklemek konfeksiyonda çok zahmetli (5 renk × 4 açı = 20
 * fotoğraf), bu yüzden "Toplu yükle" eylemi var: bir kerede birden
 * fazla dosya seçilip hepsi aynı renge atanır.
 */
class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Galeri';

    protected static ?string $modelLabel = 'görsel';

    /** Bu ürünün RENK eksenindeki değerleri. */
    private function renkSecenekleri(): array
    {
        return ProductOptionValue::query()
            ->whereHas('option', fn ($q) => $q
                ->where('product_id', $this->getOwnerRecord()->id)
                ->where('kind', 'color'))
            ->orderBy('position')
            ->pluck('value', 'id')
            ->all();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->disk('public') // vitrin storage/ altından okur; .env'ye bırakılmaz
                ->label('Görsel')
                ->image()
                ->directory('urunler/galeri')
                ->maxSize(4096)
                ->required()
                ->columnSpanFull(),

            Select::make('product_option_value_id')
                ->label('Renk')
                ->options(fn () => $this->renkSecenekleri())
                ->placeholder('Genel (renge bağlı değil)')
                ->helperText('Seçilirse vitrinde bu renk seçilince gösterilir.'),

            TextInput::make('alt')
                ->label('Açıklama (erişilebilirlik)')
                ->placeholder('Siyah saten elbise, önden')
                ->maxLength(190),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('path')
                    ->label('Görsel')
                    ->disk('public')
                    ->height(88),

                TextColumn::make('optionValue.value')
                    ->label('Renk')
                    ->badge()
                    ->placeholder('Genel'),

                TextColumn::make('alt')
                    ->label('Açıklama')
                    ->placeholder('—')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('product_option_value_id')
                    ->label('Renk')
                    ->options(fn () => $this->renkSecenekleri()),
            ])
            ->headerActions([
                Action::make('topluYukle')
                    ->label('Toplu yükle')
                    ->icon('heroicon-o-photo')
                    ->color('primary')
                    ->modalHeading('Görselleri toplu yükle')
                    ->modalDescription('Seçtiğiniz dosyaların hepsi aynı renge atanır. Sonra sürükleyerek sıralayabilirsiniz.')
                    ->schema([
                        FileUpload::make('dosyalar')
                            ->disk('public') // vitrin storage/ altından okur; .env'ye bırakılmaz
                            ->label('Görseller')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->directory('urunler/galeri')
                            ->maxSize(4096)
                            ->maxFiles(30)
                            ->required(),

                        Select::make('renk')
                            ->label('Renk')
                            ->options(fn () => $this->renkSecenekleri())
                            ->placeholder('Genel (renge bağlı değil)'),
                    ])
                    ->action(function (array $data) {
                        $urun = $this->getOwnerRecord();
                        $sira = (int) $urun->media()->max('position');

                        foreach ((array) $data['dosyalar'] as $yol) {
                            ProductMedia::create([
                                'product_id' => $urun->id,
                                'product_option_value_id' => $data['renk'] ?? null,
                                'path' => $yol,
                                'position' => ++$sira,
                            ]);
                        }

                        /*
                         * Kapak görseli boşsa ilk yüklenen kapak olsun —
                         * yoksa ürün kartı listede "Z" yer tutucusuyla
                         * kalır, yönetici neden görünmediğini anlamaz.
                         */
                        if (! $urun->hero_image && ! empty($data['dosyalar'])) {
                            $urun->update(['hero_image' => array_values((array) $data['dosyalar'])[0]]);
                        }

                        Notification::make()
                            ->title(count((array) $data['dosyalar']).' görsel eklendi')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('kapakYap')
                    ->label('Kapak yap')
                    ->icon('heroicon-o-star')
                    ->color('gray')
                    ->action(function (ProductMedia $record) {
                        $this->getOwnerRecord()->update(['hero_image' => $record->path]);

                        Notification::make()->title('Kapak görseli güncellendi')->success()->send();
                    }),

                EditAction::make()->label('Düzenle'),
                DeleteAction::make()->label('Sil'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Henüz görsel yok')
            ->emptyStateDescription('"Toplu yükle" ile bir rengin bütün fotoğraflarını bir kerede ekleyin.');
    }
}
