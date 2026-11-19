<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAddress extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function oneLine(): string
    {
        return collect([$this->line1, $this->line2, $this->landmark, $this->city, $this->state, $this->postal_code])
            ->filter()->implode(', ');
    }
}
