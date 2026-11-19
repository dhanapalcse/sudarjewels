<?php

namespace App\Enums;

enum ProductStatus: string
{
    use HasLabel;

    case Draft = 'draft';
    case Published = 'published';
}
