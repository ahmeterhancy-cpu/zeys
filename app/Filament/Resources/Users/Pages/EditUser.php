<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Sil')
                ->visible(fn (User $record) => UserResource::silinebilirMi($record)),
        ];
    }

    /**
     * Rol alanı kendi kaydında devre dışı olduğu için formdan hiç gelmez;
     * yine de sunucu tarafında da engelliyoruz — devre dışı alan istemcide
     * açılıp gönderilebilir.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->id === auth()->id()) {
            unset($data['role']);
        }

        return $data;
    }
}
