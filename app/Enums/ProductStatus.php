<?php

namespace App\Enums;

enum ProductStatus: string implements \Filament\Support\Contracts\HasLabel
{
    use HasLabel;

    case Draft = 'draft';
    case Published = 'published';
}
