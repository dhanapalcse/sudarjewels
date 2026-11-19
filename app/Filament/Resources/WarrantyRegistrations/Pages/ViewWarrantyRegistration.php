<?php

namespace App\Filament\Resources\WarrantyRegistrations\Pages;

use App\Filament\Resources\WarrantyRegistrations\WarrantyRegistrationResource;
use App\Support\Audit;
use App\Support\Phone;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWarrantyRegistration extends ViewRecord
{
    protected static string $resource = WarrantyRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('correct')->label('Correct registration details')->icon('heroicon-o-pencil')->authorize('warranty.manage')
                ->schema([
                    TextInput::make('registered_name')->required()->default(fn () => $this->record->registered_name),
                    TextInput::make('registered_phone')->required()->default(fn () => $this->record->registered_phone),
                    Textarea::make('reason')->required()->helperText('Recorded in activity history. The original order is not changed.'),
                ])
                ->action(function (array $data) {
                    $this->record->fill(['registered_name' => $data['registered_name'], 'registered_phone' => Phone::normalize($data['registered_phone']) ?? $data['registered_phone']]);
                    $diff = Audit::diff($this->record);
                    $this->record->save();
                    Audit::log('warranty.registration_corrected', $this->record, $diff, $data['reason']);
                    Notification::make()->title('Registration corrected')->success()->send();
                }),
            Action::make('card')->label('Open card')->url(fn () => route('warranty.card', $this->record), true),
        ];
    }
}
