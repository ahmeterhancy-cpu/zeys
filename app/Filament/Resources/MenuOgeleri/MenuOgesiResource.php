<?php

namespace App\Filament\Resources\MenuOgeleri;

use App\Filament\Concerns\YalnizYonetici;
use App\Filament\Resources\MenuOgeleri\Pages\CreateMenuOgesi;
use App\Filament\Resources\MenuOgeleri\Pages\EditMenuOgesi;
use App\Filament\Resources\MenuOgeleri\Pages\ListMenuOgeleri;
use App\Models\MenuOgesi;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

/**
 * Menüler — ana menü, üst şerit ve alt bilgi "Yardım" sütunu.
 * Bir konum boşsa vitrin varsayılan bağlantıları gösterir.
 */
class MenuOgesiResource extends Resource
{
    use YalnizYonetici;

    protected static ?string $model = MenuOgesi::class;

    protected static ?string $slug = 'menuler';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = 'Menüler';

    protected static ?string $modelLabel = 'menü bağlantısı';

    protected static ?string $pluralModelLabel = 'menü bağlantıları';

    protected static string|\UnitEnum|null $navigationGroup = 'Vitrin';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('konum')
                        ->label('Menü')
                        ->options(MenuOgesi::KONUMLAR)
                        ->required()
                        ->default('ana')
                        ->columnSpanFull()
                        ->helperText('Bir menüye ilk bağlantıyı eklediğinizde o menünün varsayılan bağlantıları kalkar; yalnız burada eklenenler görünür.'),

                    // Kolaylık: sitedeki bir sayfayı seçince bağlantı ve etiket dolar
                    Select::make('hazir')
                        ->label('Sitedeki bir sayfayı seç (isteğe bağlı)')
                        ->options(fn () => MenuOgesi::hazirSayfalar())
                        ->searchable()
                        ->dehydrated(false)
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if (! $state) {
                                return;
                            }
                            $set('baglanti', $state);
                            foreach (MenuOgesi::hazirSayfalar() as $grup) {
                                if (isset($grup[$state])) {
                                    $set('etiket', $grup[$state]);
                                }
                            }
                        })
                        ->columnSpanFull(),

                    TextInput::make('etiket')
                        ->label('Görünen ad')
                        ->required()
                        ->maxLength(40),

                    TextInput::make('baglanti')
                        ->label('Bağlantı')
                        ->required()
                        ->placeholder('/koleksiyonlar')
                        ->rule('regex:/^(\/|https?:\/\/)/')
                        ->validationMessages(['regex' => 'Bağlantı "/" ya da "https://" ile başlamalı.'])
                        ->maxLength(255),

                    TextInput::make('sira')->label('Sıra')->numeric()->default(0),

                    Toggle::make('yeni_sekme')->label('Yeni sekmede aç')->inline(false),

                    Toggle::make('aktif')->label('Görünsün')->default(true)->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sira')
            ->reorderable('sira')
            ->defaultGroup(Group::make('konum')
                ->label('Menü')
                ->getTitleFromRecordUsing(fn (MenuOgesi $kayit) => MenuOgesi::KONUMLAR[$kayit->konum] ?? $kayit->konum))
            ->columns([
                TextColumn::make('etiket')->label('Görünen ad')->weight('medium'),
                TextColumn::make('baglanti')->label('Bağlantı')->color('gray'),
                ToggleColumn::make('yeni_sekme')->label('Yeni sekme'),
                ToggleColumn::make('aktif')->label('Görünsün'),
            ])
            ->filters([
                SelectFilter::make('konum')->label('Menü')->options(MenuOgesi::KONUMLAR),
            ])
            ->recordActions([
                EditAction::make()->label('Düzenle'),
                DeleteAction::make()->label('Sil'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->label('Sil')]),
            ])
            ->emptyStateHeading('Menüler varsayılan bağlantılarla çalışıyor')
            ->emptyStateDescription('Ana menü: Ana Sayfa, Koleksiyonlar, Yeni Gelenler, Sipariş Sorgula, İletişim. Değiştirmek için bağlantı ekleyin.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenuOgeleri::route('/'),
            'create' => CreateMenuOgesi::route('/create'),
            'edit' => EditMenuOgesi::route('/{record}/edit'),
        ];
    }
}
