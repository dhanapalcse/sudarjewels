<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariant;
use App\Support\Audit;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        // Every product needs at least one sellable variant; add sizes/finishes in the Variants tab.
        ProductVariant::create(['product_id' => $this->record->id, 'sku' => $this->record->sku, 'is_active' => true]);
        if ($this->record->isPublished()) {
            $this->record->update(['published_at' => now()]);
        }
        Audit::log('product.created', $this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
