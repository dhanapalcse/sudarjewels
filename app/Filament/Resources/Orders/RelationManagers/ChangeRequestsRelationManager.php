<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\OrderChangeRequest;
use App\Support\Audit;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ChangeRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'changeRequests';

    protected static ?string $title = 'Affiliate change requests';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->affiliate_id !== null;
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->since(),
            TextColumn::make('message')->wrap(),
            TextColumn::make('status')->badge(),
            TextColumn::make('response')->wrap()->placeholder('—'),
        ])->recordActions([
            Action::make('resolve')->authorize('orders.manage')->visible(fn (OrderChangeRequest $r) => $r->status === 'open')
                ->schema([
                    Select::make('status')->options(['resolved' => 'Done', 'declined' => 'Declined'])->required(),
                    Textarea::make('response')->required()->helperText('Make the actual change via refund, return or attribution actions — this records the reply.'),
                ])
                ->action(function (OrderChangeRequest $r, array $data) {
                    $r->update($data + ['resolved_by_id' => auth()->id(), 'resolved_at' => now()]);
                    Audit::log('order.change_request_'.$data['status'], $r->order, ['request_id' => $r->id], $data['response']);
                }),
        ])->paginated(false);
    }
}
