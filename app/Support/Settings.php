<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime business settings stored in the `settings` table, with typed defaults.
 * Secrets never live here — they stay in the environment.
 */
class Settings
{
    private const CACHE_KEY = 'business-settings';

    /** @var array<string,mixed>|null */
    private ?array $loaded = null;

    /** @return array<string, mixed> key => default */
    public static function defaults(): array
    {
        return [
            'business.name' => config('sudar.business.name'),
            'business.tagline' => 'Korean-inspired anti-tarnish jewellery',
            'business.phone' => null,
            'business.whatsapp_number' => null,
            'business.email' => null,
            'business.address' => null,
            'business.hours' => null,
            'business.gstin' => null,
            'social.instagram' => null,
            'social.facebook' => null,
            'social.youtube' => null,
            'social.pinterest' => null,
            'commission.default_rate_bp' => config('sudar.commission.default_rate_bp'),
            // Must be explicitly set by the business before commissions become payable automatically.
            'commission.return_window_days' => null,
            'referral.window_days' => config('sudar.referral.window_days'),
            'warranty.months' => config('sudar.warranty.months'),
            'warranty.replacement_policy' => config('sudar.warranty.replacement_policy'),
            'warranty.default_terms' => 'Covers plating and stone-setting defects under normal use for three calendar months from delivery. Excludes loss, theft, physical damage, misuse and normal wear.',
            'shipping.flat_fee_paise' => 0,
            'shipping.free_above_paise' => null,
            'shipping.delivery_summary' => 'Dispatched within 2 business days. Delivery usually takes 3–7 business days across India.',
            'returns.summary' => 'Unused items in original packaging can be returned within the return window. See our Returns policy.',
            'tax.prices_include_tax' => true,
            'tax.gst_rate_bp' => 0,
            'inventory.low_stock_threshold' => config('sudar.inventory.low_stock_threshold'),
            'home.hero_heading' => 'Everyday shine that stays',
            'home.hero_subheading' => 'Korean-inspired, anti-tarnish jewellery made for daily wear.',
            'home.hero_image' => null,
            'home.warranty_heading' => 'Three-month warranty on every piece',
            'home.warranty_text' => 'Register your purchase in a minute using your order number and phone.',
            'analytics.ga4_measurement_id' => null,
        ];
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : ($fallback ?? (self::defaults()[$key] ?? null));
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                return Setting::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                return [];
            }
        });

        return $this->loaded = array_merge(self::defaults(), $stored);
    }

    public function isExplicitlySet(string $key): bool
    {
        return Setting::where('key', $key)->whereNotNull('value')->exists();
    }

    /** @param array<string, mixed> $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    public function whatsappNumber(): ?string
    {
        return Phone::forWhatsApp($this->get('business.whatsapp_number'));
    }

    /** @return array<string, string> */
    public function socialLinks(): array
    {
        return array_filter(Arr::only($this->all(), ['social.instagram', 'social.facebook', 'social.youtube', 'social.pinterest']));
    }

    public function ga4Id(): ?string
    {
        return $this->get('analytics.ga4_measurement_id') ?: config('sudar.analytics.ga4_measurement_id');
    }
}
