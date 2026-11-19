<?php

namespace App\Domain\Orders;

use App\Domain\Catalogue\CommissionRateResolver;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\Settings;

/**
 * Server-side pricing. Clients send only variant ids and quantities (plus, for staff,
 * optional price overrides and discounts); every amount is recomputed here.
 *
 * Discount allocation: order-level discount is split across lines in proportion to each
 * line's subtotal after its own item discount (largest-remainder, sums exactly).
 * Tax: GST portion derived per line (inclusive or exclusive per settings).
 * Commission basis: line amount after allocated discounts, excluding tax and shipping.
 */
class QuoteCalculator
{
    public function __construct(private CommissionRateResolver $rates, private Settings $settings) {}

    /**
     * @param  array<int, array{variant_id:int, quantity:int, unit_price_paise?:?int, item_discount_paise?:?int}>  $lines
     * @param  array{order_discount_paise?:int, shipping_paise?:?int, delivery_required?:bool, with_commission?:bool, allow_overrides?:bool}  $options
     * @return array{items: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function calculate(array $lines, array $options = []): array
    {
        $allowOverrides = (bool) ($options['allow_overrides'] ?? false);
        $withCommission = (bool) ($options['with_commission'] ?? false);

        $merged = [];
        foreach ($lines as $line) {
            $id = (int) ($line['variant_id'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 0);
            if ($id <= 0 || $qty <= 0) {
                continue;
            }
            if ($qty > 100) {
                throw new BusinessRuleException('Quantity per line cannot exceed 100.');
            }
            $merged[] = $line + ['variant_id' => $id, 'quantity' => $qty];
        }
        if ($merged === []) {
            throw new BusinessRuleException('Add at least one product.');
        }

        $variants = ProductVariant::with('product')->whereIn('id', array_column($merged, 'variant_id'))->get()->keyBy('id');

        $items = [];
        foreach ($merged as $i => $line) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($line['variant_id']);
            if (! $variant || ! $variant->is_active || ! $variant->product || $variant->product->trashed()) {
                throw new BusinessRuleException('One of the selected products is no longer available.');
            }
            $product = $variant->product;
            $unit = $variant->effectivePrice();
            if ($allowOverrides && isset($line['unit_price_paise']) && $line['unit_price_paise'] !== null) {
                $unit = max(0, (int) $line['unit_price_paise']);
            }
            $subtotal = $unit * $line['quantity'];
            $itemDiscount = $allowOverrides ? min($subtotal, max(0, (int) ($line['item_discount_paise'] ?? 0))) : 0;
            $rate = $this->rates->forProduct($product);

            $items[$i] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'product_name' => $product->name,
                'variant_name' => $variant->displayName(),
                'sku' => $variant->sku,
                'variant_attributes' => $variant->attributesForDisplay(),
                'unit_price_paise' => $unit,
                'regular_price_paise' => $variant->effectiveRegularPrice(),
                'quantity' => $line['quantity'],
                'line_subtotal_paise' => $subtotal,
                'item_discount_paise' => $itemDiscount,
                'commission_enabled' => $rate['enabled'],
                'commission_rate_bp' => $rate['rate_bp'],
                'warranty_eligible' => (bool) $product->warranty_eligible,
                'warranty_months' => $product->warrantyMonths(),
                'warranty_terms' => $product->warranty_terms ?: $this->settings->get('warranty.default_terms'),
                'available' => $variant->available(),
                'price_overridden' => $unit !== $variant->effectivePrice(),
            ];
        }

        $subtotalAfterItemDiscounts = array_sum(array_map(fn ($it) => $it['line_subtotal_paise'] - $it['item_discount_paise'], $items));
        $orderDiscount = $allowOverrides ? min($subtotalAfterItemDiscounts, max(0, (int) ($options['order_discount_paise'] ?? 0))) : 0;
        $shares = Money::allocate($orderDiscount, array_map(fn ($it) => $it['line_subtotal_paise'] - $it['item_discount_paise'], $items));

        $gstBp = (int) $this->settings->get('tax.gst_rate_bp');
        $inclusive = (bool) $this->settings->get('tax.prices_include_tax');

        $totals = ['subtotal_paise' => 0, 'discount_total_paise' => 0, 'tax_paise' => 0, 'items_total_paise' => 0, 'commission_estimate_paise' => 0];
        foreach ($items as $i => &$it) {
            $it['allocated_discount_paise'] = $it['item_discount_paise'] + $shares[$i];
            $net = $it['line_subtotal_paise'] - $it['allocated_discount_paise'];
            if ($gstBp > 0 && $inclusive) {
                $tax = $net - intdiv($net * 10000 * 2 + (10000 + $gstBp), (10000 + $gstBp) * 2);
                $lineTotal = $net;
                $eligible = $net - $tax;
            } elseif ($gstBp > 0) {
                $tax = Money::applyRate($net, $gstBp);
                $lineTotal = $net + $tax;
                $eligible = $net;
            } else {
                $tax = 0;
                $lineTotal = $net;
                $eligible = $net;
            }
            $it['tax_paise'] = $tax;
            $it['line_total_paise'] = $lineTotal;
            $it['commission_eligible_paise'] = $it['commission_enabled'] ? $eligible : 0;
            $it['commission_paise'] = ($withCommission && $it['commission_enabled']) ? Money::applyRate($eligible, $it['commission_rate_bp']) : 0;

            $totals['subtotal_paise'] += $it['line_subtotal_paise'];
            $totals['discount_total_paise'] += $it['allocated_discount_paise'];
            $totals['tax_paise'] += $tax;
            $totals['items_total_paise'] += $lineTotal;
            $totals['commission_estimate_paise'] += $it['commission_paise'];
        }
        unset($it);

        $deliveryRequired = (bool) ($options['delivery_required'] ?? true);
        if (! $deliveryRequired) {
            $shipping = 0;
        } elseif ($allowOverrides && isset($options['shipping_paise']) && $options['shipping_paise'] !== null) {
            $shipping = max(0, (int) $options['shipping_paise']);
        } else {
            $shipping = $this->defaultShipping($totals['items_total_paise']);
        }

        $totals['order_discount_paise'] = $orderDiscount;
        $totals['shipping_paise'] = $shipping;
        $totals['grand_total_paise'] = $totals['items_total_paise'] + $shipping;

        return ['items' => array_values($items), 'totals' => $totals];
    }

    public function defaultShipping(int $itemsTotal): int
    {
        $free = $this->settings->get('shipping.free_above_paise');
        if ($free !== null && $itemsTotal >= (int) $free) {
            return 0;
        }

        return (int) $this->settings->get('shipping.flat_fee_paise');
    }
}
