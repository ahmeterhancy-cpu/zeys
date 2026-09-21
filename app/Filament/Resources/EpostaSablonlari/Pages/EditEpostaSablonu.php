<?php

namespace App\Filament\Resources\EpostaSablonlari\Pages;

use App\Filament\Resources\EpostaSablonlari\EpostaSablonuResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEpostaSablonu extends EditRecord
{
    protected static string $resource = EpostaSablonuResource::class;

    public function getTitle(): string
    {
        return $this->record->ad;
    }

    protected function getHeaderActions(): array
    {
        return [
            EpostaSablonuResource::onizlemeEylemi()->record($this->record),

            Action::make('varsayilan')
                ->label('Varsayılana döndür')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Bu e-postadaki bütün özel metinler silinir; varsayılan metinler kullanılır.')
                ->visible(fn () => $this->record->degistirildi_mi)
                ->action(function () {
                    $this->record->update(['konu' => null, 'baslik' => null, 'metin' => null, 'not' => null]);
                    $this->fillForm();

                    Notification::make()->title('Varsayılan metinlere dönüldü')->success()->send();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
