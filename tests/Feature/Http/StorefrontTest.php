<?php

namespace Tests\Feature\Http;

use App\Models\ReferralVisit;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_pages_render_with_seo_and_filters(): void
    {
        $variant = $this->variant(89900, 3);
        $product = $variant->product;

        $this->get('/')->assertOk()->assertSee('SUDAR');
        $this->get(route('shop', ['sort' => 'price_asc', 'in_stock' => 1, 'finish' => ['Gold']]))->assertOk()->assertSee($product->name)
            ->assertSee('<link rel="canonical" href="'.route('shop').'">', false);
        $this->get(route('shop', ['q' => 'nothing-matches-this']))->assertOk()->assertSee('No pieces match');
        $this->get(route('shop.category', $product->category))->assertOk()->assertSee($product->name);
        $this->get(route('products.show', $product))->assertOk()->assertSee($variant->sku)->assertSee('application/ld+json', false)->assertSee('"priceCurrency":"INR"', false);
        $this->get(route('sitemap'))->assertOk()->assertSee(route('products.show', $product));
    }

    public function test_draft_products_are_hidden(): void
    {
        $variant = $this->variant(10000, 1, ['status' => 'draft']);
        $this->get(route('products.show', $variant->product))->assertNotFound();
        $this->get(route('shop'))->assertDontSee($variant->product->name);
    }

    public function test_private_areas_are_noindex(): void
    {
        $this->get(route('warranty.start'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('customer.login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_referral_link_is_captured_and_included_in_whatsapp_message(): void
    {
        app(Settings::class)->set(['business.whatsapp_number' => '9000000000']);
        $aff = $this->affiliate(['code' => 'SUDAR101']);
        $variant = $this->variant(89900, 3);

        $this->get(route('products.show', $variant->product).'?ref=sudar101')->assertOk()->assertCookie('sj_ref');
        $this->assertSame(1, ReferralVisit::where('affiliate_id', $aff->id)->count());

        $res = $this->withCredentials()->withCookie('sj_ref', json_encode(['code' => 'SUDAR101', 'at' => now()->timestamp]))
            ->postJson(route('enquiry.whatsapp'), ['variant_id' => $variant->id, 'quantity' => 2])->assertOk();
        $text = urldecode(parse_url($res->json('url'), PHP_URL_QUERY));
        $this->assertStringStartsWith('https://wa.me/919000000000', $res->json('url'));
        foreach ([$variant->product->name, $variant->sku, 'Quantity: 2', 'Referral code: SUDAR101'] as $needle) {
            $this->assertStringContainsString($needle, $text);
        }
    }

    public function test_explicit_code_beats_saved_referral_and_invalid_codes_are_rejected(): void
    {
        app(Settings::class)->set(['business.whatsapp_number' => '9000000000']);
        $this->affiliate(['code' => 'SAVED1']);
        $this->affiliate(['code' => 'EXPLICIT1']);
        $this->affiliate(['code' => 'INACTIVE1', 'status' => 'suspended']);
        $variant = $this->variant(89900, 3);
        $cookie = json_encode(['code' => 'SAVED1', 'at' => now()->timestamp]);

        $res = $this->withCredentials()->withCookie('sj_ref', $cookie)->postJson(route('enquiry.whatsapp'), ['variant_id' => $variant->id, 'quantity' => 1, 'ref' => 'EXPLICIT1']);
        $this->assertStringContainsString('EXPLICIT1', urldecode($res->json('url')));

        $this->withCredentials()->withCookie('sj_ref', $cookie)->postJson(route('enquiry.whatsapp'), ['variant_id' => $variant->id, 'quantity' => 1, 'ref' => 'INACTIVE1'])->assertStatus(422);

        // Inactive code via link never overwrites the saved referral.
        $this->get(route('products.show', $variant->product).'?ref=INACTIVE1')->assertCookieMissing('sj_ref');
    }

    public function test_expired_referral_window_is_ignored(): void
    {
        app(Settings::class)->set(['business.whatsapp_number' => '9000000000']);
        $this->affiliate(['code' => 'OLD1']);
        $variant = $this->variant(89900, 3);
        $res = $this->withCredentials()->withCookie('sj_ref', json_encode(['code' => 'OLD1', 'at' => now()->subDays(31)->timestamp]))
            ->postJson(route('enquiry.whatsapp'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->assertStringNotContainsString('Referral', urldecode($res->json('url')));
    }

    public function test_enquiry_validates_selection_and_stock(): void
    {
        app(Settings::class)->set(['business.whatsapp_number' => '9000000000']);
        $variant = $this->variant(89900, 1);
        $this->postJson(route('enquiry.whatsapp'), ['quantity' => 1])->assertStatus(422);
        $this->postJson(route('enquiry.whatsapp'), ['variant_id' => $variant->id, 'quantity' => 3])->assertStatus(422);
    }
}
