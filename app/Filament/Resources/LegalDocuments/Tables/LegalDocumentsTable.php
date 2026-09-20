<?php

namespace App\Filament\Resources\LegalDocuments\Tables;

use App\Filament\Resources\LegalDocuments\Schemas\LegalDocumentForm;
use App\Models\LegalDocument;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LegalDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Metin')
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('slug')
                    ->label('Tür')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => LegalDocumentForm::slugSecenekleri()[$state] ?? $state),

                TextColumn::make('version')
                    ->label('Sürüm')
                    ->searchable()
                    ->copyable(),

                IconColumn::make('is_current')
                    ->label('Yürürlükte')
                    ->boolean(),

                TextColumn::make('published_at')
                    ->label('Yayım')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                /*
                 * Bu sürüme dayanan sipariş sayısı. Sıfırdan büyükse metin
                 * yerinde düzenlenmemeli — o siparişlerin müşterileri BU
                 * metni onayladı.
                 */
                TextColumn::make('bagli_siparis')
                    ->label('Bağlı sipariş')
                    ->getStateUsing(fn (LegalDocument $kayit) => Order::where('contract_version', $kayit->version)->count())
                    ->badge()
                    ->color(fn ($state) => (int) $state > 0 ? 'warning' : 'gray')
                    ->tooltip('Bu sürümü onaylamış sipariş sayısı'),
            ])
            ->filters([
                SelectFilter::make('slug')
                    ->label('Metin türü')
                    ->options(LegalDocumentForm::slugSecenekleri()),

                TernaryFilter::make('is_current')
                    ->label('Yürürlük')
                    ->placeholder('Hepsi')
                    ->trueLabel('Yürürlükte')
                    ->falseLabel('Eski sürümler'),
            ])
            ->recordActions([
                Action::make('vitrindeGor')
                    ->label('Vitrinde gör')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->visible(fn (LegalDocument $kayit) => (bool) $kayit->is_current)
                    ->url(fn (LegalDocument $kayit) => url('/sayfa/'.$kayit->slug))
                    ->openUrlInNewTab(),

                EditAction::make()->label('Düzenle'),
            ])
            ->emptyStateHeading('Henüz yasal metin yok')
            ->emptyStateDescription('LegalDocumentSeeder şablonları yükler; sonra buradan düzenleyin.');
    }
}
