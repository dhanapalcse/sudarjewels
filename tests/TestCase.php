<?php

namespace Tests;

use App\Domain\Orders\OrderService;
use App\Enums\SalesChannel;
use App\Enums\StockMovementType;
use App\Domain\Inventory\InventoryService;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function admin(): User
    {
        $this->seedRoles();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Permissions::SUPER_ADMIN);

        return $user;
    }

    protected function staff(string $role): User
    {
        $this->seedRoles();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function affiliate(array $attributes = []): Affiliate
    {
        $this->seedRoles();
        $affiliate = Affiliate::factory()->create($attributes);
        $affiliate->user->assignRole(Permissions::AFFILIATE);

        return $affiliate->load('user');
    }

    protected function variant(int $pricePaise = 100000, int $stock = 10, array $productAttributes = []): ProductVariant
    {
        $product = Product::factory()->create(['price_paise' => $pricePaise] + $productAttributes);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        if ($stock > 0) {
            app(InventoryService::class)->adjust($variant, $stock, 'test-initial:'.$variant->id.':'.Str::random(6), null, 'test', StockMovementType::Initial);
        }

        return $variant->refresh();
    }

    /** @param array<int, array{0: ProductVariant, 1: int}> $lines */
    protected function order(User $actor, array $lines, ?Affiliate $affiliate = null, array $extra = []): Order
    {
        $context = ['allow_overrides' => true];
        if ($affiliate) {
            $context['affiliate'] = $affiliate;
        }

        return app(OrderService::class)->saveQuote(null, array_merge([
            'customer_name' => 'Test Customer',
            'customer_phone' => '9876543210',
            'delivery_required' => false,
            'channel' => SalesChannel::WhatsApp->value,
            'lines' => array_map(fn ($l) => ['variant_id' => $l[0]->id, 'quantity' => $l[1]], $lines),
        ], $extra), $actor, $context);
    }
}
