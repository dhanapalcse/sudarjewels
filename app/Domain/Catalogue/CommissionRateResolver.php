<?php

namespace App\Domain\Catalogue;

use App\Models\Product;
use App\Support\Settings;

/**
 * Commission rate rules:
 *  - commission disabled on the product  => 0 %, regardless of the global default
 *  - product override set (including 0)  => that override
 *  - override NULL                       => global default (Settings: commission.default_rate_bp)
 */
class CommissionRateResolver
{
    public function __construct(private Settings $settings) {}

    /** @return array{enabled: bool, rate_bp: int, source: string} */
    public function forProduct(Product $product): array
    {
        if (! $product->commission_enabled) {
            return ['enabled' => false, 'rate_bp' => 0, 'source' => 'disabled'];
        }

        if ($product->commission_rate_bp !== null) {
            return ['enabled' => true, 'rate_bp' => (int) $product->commission_rate_bp, 'source' => 'product'];
        }

        return ['enabled' => true, 'rate_bp' => $this->globalRate(), 'source' => 'global'];
    }

    public function globalRate(): int
    {
        return (int) $this->settings->get('commission.default_rate_bp');
    }
}
