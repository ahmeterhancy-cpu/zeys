<?php

namespace App\Filament\Resources\ProductReviews;

use App\Filament\Resources\ProductReviews\Pages\ListProductReviews;
use App\Models\ProductReview;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ürün yorumları — moderasyon.
 *
 * Yorumlar müşteriden gelir, panelden yazılmaz ve DÜZENLENMEZ: mağazanın
 * müşteri yorumunu değiştirmesi yanıltıcı ticari uygulama sayılır. Panel
 * yalnız yayımlar, reddeder ya da siler.
 */
class ProductReviewResource extends Resource
{
    protected static ?string $model = ProductReview::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Yorumlar';

    protected static ?string $modelLabel = 'yorum';

    protected static ?string $pluralModelLabel = 'yorumlar';

    protected static ?int $navigationSort = 6;

    protected static string|\UnitEnum|null $navigationGroup = 'Katalog';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product', 'order']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Ürün')
                    ->searchable()
                    ->weight('medium')
                    ->url(fn (ProductReview $kayit) => $kayit->product ? route('products.show', $kayit->product->slug) : null, true),

                TextColumn::make('rating')
                    ->label('Puan')
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color(fn (int $state) => $state <= 2 ? 'danger' : 'warning')
                    ->sortable(),

                TextColumn::make('body')
                    ->label('Yorum')
                    ->description(fn (ProductReview $kayit) => $kayit->title, position: 'above')
                    ->wrap()
                    ->lineClamp(4)
                    ->searchable(),

                TextColumn::make('author_name')
                    ->label('Yazan')
                    ->description(fn (ProductReview $kayit) => $kayit->order?->number),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ProductReview::DURUMLAR[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'gray',
                        default => 'warning',
                    }),

                TextColumn::make('created_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options(ProductReview::DURUMLAR)
                    ->default('pending'),
            ])
            ->recordActions([
                Action::make('yayimla')
                    ->label('Yayımla')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ProductReview $kayit) => $kayit->status !== 'approved')
                    ->action(fn (ProductReview $record) => $record->update([
                        'status' => 'approved',
                        'approved_at' => now(),
                    ])),

                Action::make('reddet')
                    ->label(fn (ProductReview $kayit) => $kayit->status === 'approved' ? 'Yayından kaldır' : 'Reddet')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (ProductReview $kayit) => $kayit->status !== 'rejected')
                    ->requiresConfirmation()
                    ->modalDescription('Yorum vitrinde görünmez; kayıt panelde kalır.')
                    ->action(fn (ProductReview $record) => $record->update(['status' => 'rejected'])),

                DeleteAction::make()->label('Sil'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('topluYayimla')
                        ->label('Seçilenleri yayımla')
                        ->icon('heroicon-o-check')
                        ->action(function (Collection $records) {
                            // Tek tek: her kayıt ürün puan önbelleğini tazelesin
                            $records->each(fn (ProductReview $r) => $r->status === 'approved'
                                ?: $r->update(['status' => 'approved', 'approved_at' => now()]));
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()->label('Sil'),
                ]),
            ])
            ->emptyStateHeading('Bekleyen yorum yok')
            ->emptyStateDescription('Teslim edilen siparişlerin sahipleri sipariş sayfasından yorum yazabilir.');
    }

    public static function getNavigationBadge(): ?string
    {
        $bekleyen = ProductReview::where('status', 'pending')->count();

        return $bekleyen > 0 ? (string) $bekleyen : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Onay bekleyen yorumlar';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductReviews::route('/'),
        ];
    }
}
