<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Affiliates\ReferralService;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Builds the WhatsApp enquiry link server-side after validating the selection,
 * so the message always carries the true product, SKU, variant and referral code.
 */
class EnquiryController extends Controller
{
    public function store(Request $request, Settings $settings, ReferralService $referrals): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'ref' => ['nullable', 'string', 'max:32'],
        ], [
            'variant_id.required' => 'Please choose an option first.',
        ]);

        $variant = ProductVariant::with('product')->where('is_active', true)->find($data['variant_id']);
        if (! $variant || ! $variant->product?->isPublished()) {
            throw new BusinessRuleException('This product is not available.');
        }
        if ($variant->available() < $data['quantity']) {
            throw new BusinessRuleException($variant->available() > 0 ? "Only {$variant->available()} available for this option." : 'This option is out of stock.');
        }
        $number = $settings->whatsappNumber();
        if (! $number) {
            throw new BusinessRuleException('WhatsApp ordering is not available right now. Please contact us.');
        }

        $attribution = $referrals->resolve($request, $data['ref'] ?? null);
        $product = $variant->product;
        $lines = [
            "Hello {$settings->get('business.name')}, I'd like to order:",
            "Product: {$product->name}",
            "SKU: {$variant->sku}",
            'Option: '.$variant->displayName(),
            "Quantity: {$data['quantity']}",
            'Price: '.Money::format($variant->effectivePrice()).' each',
            'Link: '.route('products.show', $product),
        ];
        if ($attribution) {
            $lines[] = 'Referral code: '.$attribution->affiliate->code;
        }
        $url = 'https://wa.me/'.$number.'?text='.rawurlencode(implode("\n", $lines));

        return $request->expectsJson() ? response()->json(['url' => $url]) : redirect()->away($url);
    }
}
