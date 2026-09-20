<?php

namespace App\Filament\Resources\LegalDocuments\Pages;

use App\Filament\Resources\LegalDocuments\LegalDocumentResource;
use App\Models\LegalDocument;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLegalDocument extends EditRecord
{
    protected static string $resource = LegalDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('yeniSurum')
                ->label('Yeni sürüm yayımla')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->modalHeading('Yeni sürüm yayımla')
                ->modalDescription(
                    'Bu metnin YENİ bir sürümü oluşturulur ve yürürlüğe girer. '
                    .'Mevcut sürüm silinmez, yalnızca yürürlükten kalkar — '
                    .'ona dayanan siparişler okunabilir kalır.'
                )
                ->modalSubmitActionLabel('Yayımla')
                ->fillForm(fn (LegalDocument $record) => [
                    'title' => $record->title,
                    'body' => $record->body,
                ])
                ->schema([
                    TextInput::make('title')
                        ->label('Başlık')
                        ->required()
                        ->maxLength(190),

                    Textarea::make('body')
                        ->label('Metin (HTML)')
                        ->required()
                        ->rows(18),
                ])
                ->action(function (array $data, LegalDocument $record) {
                    $yeni = LegalDocument::publish($record->slug, $data['title'], $data['body']);

                    Notification::make()
                        ->title('Yeni sürüm yayımlandı')
                        ->body('Sürüm '.$yeni->version.' yürürlükte. Önceki sürüm arşivde kaldı.')
                        ->success()
                        ->send();

                    $this->redirect(LegalDocumentResource::getUrl('edit', ['record' => $yeni]));
                }),

            Action::make('vitrindeGor')
                ->label('Vitrinde gör')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn (LegalDocument $record) => (bool) $record->is_current)
                ->url(fn (LegalDocument $record) => url('/sayfa/'.$record->slug))
                ->openUrlInNewTab(),

            /*
             * Silme, bu sürümü onaylamış sipariş varsa KAPALI.
             * Sipariş `contract_version` ile bu kayda işaret ediyor;
             * silinirse müşterinin neyi onayladığı kanıtlanamaz.
             */
            DeleteAction::make()
                ->label('Sil')
                ->visible(fn (LegalDocument $record) => Order::where('contract_version', $record->version)->doesntExist())
                ->modalDescription('Bu sürüme bağlı sipariş yok, güvenle silinebilir.'),
        ];
    }
}
