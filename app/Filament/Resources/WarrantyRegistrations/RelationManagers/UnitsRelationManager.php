<?php

namespace App\Filament\Resources\WarrantyRegistrations\RelationManagers;

use App\Domain\Warranty\WarrantyCalculator;
use App\Models\PurchasedUnit;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Covered pieces';

    public function table(Table $table): Table
    {
        $tz = config('sudar.business.timezone');

        return $table->modifyQueryUsing(fn ($query) => $query->with('orderItem'))->columns([
            TextColumn::make('code'),
            TextColumn::make('orderItem.product_name')->label('Item'),
            TextColumn::make('status')->badge(),
            TextColumn::make('warranty_status')->badge(),
            TextColumn::make('warranty_starts_at')->label('From')->dateTime('j M Y', $tz),
            TextColumn::make('warranty_ends_at')->label('Until')->dateTime('j M Y', $tz),
            TextColumn::make('warranty_registered_at')->label('Registered')->dateTime('j M Y', $tz),
        ])->recordActions([
            Action::make('correctCoverage')->label('Correct coverage')->authorize('warranty.manage')
                ->schema([
                    DateTimePicker::make('delivered_at')->label('Actual delivery / handover')->required()->timezone($tz)->default(fn (PurchasedUnit $r) => $r->delivered_at),
                    Textarea::make('reason')->required(),
                ])
                ->action(function (PurchasedUnit $r, array $data) {
                    [$start, $end] = WarrantyCalculator::coverage(Carbon::parse($data['delivered_at']), $r->warranty_months);
                    $before = ['starts' => $r->warranty_starts_at?->toIso8601String(), 'ends' => $r->warranty_ends_at?->toIso8601String()];
                    $r->update(['delivered_at' => $start, 'warranty_starts_at' => $start, 'warranty_ends_at' => $end]);
                    Audit::log('warranty.coverage_corrected', $r, ['from' => $before, 'to' => ['starts' => $start->toIso8601String(), 'ends' => $end->toIso8601String()]], $data['reason']);
                    Notification::make()->title('Coverage corrected')->success()->send();
                }),
        ])->paginated(false);
    }
}
