<?php

namespace Tests\Feature\Domain;

use App\Domain\Orders\OrderService;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_adds_catalogue_without_users_or_settings_changes_and_removes_cleanly(): void
    {
        Storage::fake('media');
        app(Settings::class)->set(['business.whatsapp_number' => '9876500000']);

        $this->artisan('sudar:demo')->assertSuccessful();

        $this->assertSame(16, Product::where('is_sample', true)->count());
        $this->assertSame(0, User::count(), 'No accounts are created');
        $this->assertSame('9876500000', app(Settings::class)->get('business.whatsapp_number'));
        $this->assertTrue(app(Settings::class)->get('home.hero_image')['is_sample']);
        $this->get('/')->assertOk()->assertSee('Pearl Hoops');

        // A sample product that was sold is hidden, not deleted, so order history survives.
        $admin = $this->admin();
        $sold = Product::where('slug', 'pearl-hoops')->first()->variants()->first();
        app(OrderService::class)->confirm($this->order($admin, [[$sold, 1]]), $admin);

        $this->artisan('sudar:demo', ['--remove' => true])
            ->expectsConfirmation('Remove all sample products, their images and empty demo categories?', 'yes')
            ->assertSuccessful();

        $this->assertSame(1, Product::where('is_sample', true)->count());
        $this->assertSame('draft', Product::where('slug', 'pearl-hoops')->first()->status->value);
        $this->assertNull(app(Settings::class)->get('home.hero_image'));
        $this->assertTrue(Category::where('slug', 'earrings')->exists(), 'Category still used by the kept product');
        $this->assertFalse(Category::where('slug', 'bracelets')->exists());
        $this->get('/')->assertOk()->assertDontSee('Pearl Hoops');
    }
}
