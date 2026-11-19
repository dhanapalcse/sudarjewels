<?php

namespace App\Filament\Resources\CommissionEntries\Pages;

use App\Domain\Commissions\CommissionService;
use App\Filament\Resources\CommissionEntries\CommissionEntryResource;
use App\Filament\Support\Guard;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCommissionEntries extends ListRecords
{
    protected static string $resource = CommissionEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runEligibility')->label('Run eligibility check now')->icon('heroicon-o-play')->authorize('commissions.manage')
                ->requiresConfirmation()
                ->modalDescription(fn () => app(Settings::class)->get('commission.return_window_days') === null
                    ? 'The return window is not configured in Business settings, so nothing will become payable.'
                    : 'Moves pending commission to payable for fully paid, delivered orders past the return window.')
                ->action(fn () => Guard::run(fn () => app(CommissionService::class)->markEligibleAsPayable(), 'Eligibility check finished')),
        ];
    }
}
