<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! ($data['commission_enabled'] ?? true)) {
            unset($data['commission_rate_bp']); // keep stored override; disabled flag wins
        }
        $record->fill($data);
        if ($record->isDirty('status') && $record->isPublished() && ! $record->published_at) {
            $record->published_at = now();
        }
        $changes = Audit::diff($record, ['price_paise', 'regular_price_paise', 'commission_enabled', 'commission_rate_bp', 'status', 'warranty_eligible', 'warranty_months']);
        $record->save();
        if ($changes) {
            Audit::log('product.updated', $record, $changes);
        }

        return $record;
    }
}
