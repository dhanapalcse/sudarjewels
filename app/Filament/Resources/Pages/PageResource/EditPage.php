<?php

namespace App\Filament\Resources\Pages\PageResource;

use App\Filament\Resources\Pages\PageResource;
use App\Support\Audit;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function afterSave(): void
    {
        Audit::log('content.page_updated', $this->record, ['slug' => $this->record->slug]);
    }
}
