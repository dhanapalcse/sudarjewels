<?php

namespace App\Console\Commands;

use App\Domain\Inventory\InventoryService;
use App\Domain\Orders\OrderService;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Verifies overselling protection with genuinely parallel processes against the configured
 * database (run it against MySQL before launch). Creates temporary data and removes it afterwards.
 * Not for production use.
 */
class ConcurrencyCheck extends Command
{
    protected $signature = 'sudar:concurrency-check {--workers=8} {--stock=1} {--confirm-order= : internal}';

    protected $description = 'Race N parallel order confirmations for limited stock and verify nothing oversells';

    public function handle(OrderService $orders, InventoryService $inventory): int
    {
        if ($id = $this->option('confirm-order')) {
            usleep(random_int(0, 20000));
            try {
                $orders->confirm(Order::findOrFail($id), User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->firstOrFail());
                $this->line('CONFIRMED');
            } catch (BusinessRuleException $e) {
                $this->line('REJECTED');
            }

            return self::SUCCESS;
        }

        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }
        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();
        if (! $admin) {
            $this->error('Seed a super admin first (php artisan db:seed).');

            return self::FAILURE;
        }

        $workers = max(2, (int) $this->option('workers'));
        $stock = max(1, (int) $this->option('stock'));
        $tag = 'concurrency-'.Str::lower(Str::random(6));
        $category = Category::firstOrCreate(['slug' => 'concurrency-check'], ['name' => 'Concurrency check', 'is_active' => false]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Concurrency check '.$tag, 'slug' => $tag, 'sku' => strtoupper($tag), 'price_paise' => 10000, 'status' => ProductStatus::Draft, 'is_sample' => true]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'sku' => strtoupper($tag).'-V']);
        $inventory->adjust($variant, $stock, "{$tag}:initial", $admin, 'Concurrency check', StockMovementType::Initial);

        $ids = [];
        for ($i = 0; $i < $workers; $i++) {
            $ids[] = $orders->saveQuote(null, ['customer_name' => 'Concurrency '.$i, 'customer_phone' => '9000000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'delivery_required' => false, 'channel' => 'whatsapp',
                'lines' => [['variant_id' => $variant->id, 'quantity' => 1]]], $admin)->id;
        }

        $php = (new PhpExecutableFinder)->find();
        $processes = array_map(fn ($id) => new Process([$php, base_path('artisan'), 'sudar:concurrency-check', '--confirm-order='.$id]), $ids);
        foreach ($processes as $p) {
            $p->start();
        }
        $confirmed = $rejected = 0;
        foreach ($processes as $p) {
            $p->wait();
            $out = $p->getOutput();
            $confirmed += str_contains($out, 'CONFIRMED') ? 1 : 0;
            $rejected += str_contains($out, 'REJECTED') ? 1 : 0;
            if (! str_contains($out, 'CONFIRMED') && ! str_contains($out, 'REJECTED')) {
                preg_match('/[A-Za-z\\\\]*(Exception|Error)[^\n]{0,160}/', $p->getErrorOutput().$out, $m);
                $errors[] = $m[0] ?? 'exit code '.$p->getExitCode();
            }
        }
        foreach (array_count_values($errors ?? []) as $message => $count) {
            $this->warn("{$count} worker(s) errored (database busy errors are expected on SQLite; use MySQL for a full check): ".mb_substr($message, 0, 200));
        }

        $variant->refresh();
        $ok = $confirmed === $stock && $variant->stock_reserved === $stock && $variant->available() === 0;
        $this->table(['Workers', 'Stock', 'Confirmed', 'Rejected', 'Reserved', 'Available', 'DB'], [[$workers, $stock, $confirmed, $rejected, $variant->stock_reserved, $variant->available(), config('database.default')]]);

        // Clean up temporary data.
        foreach (Order::whereIn('id', $ids)->get() as $order) {
            $order->units()->delete();
            StockReservation::whereIn('order_item_id', $order->items()->pluck('id'))->delete();
            $order->history()->delete();
            $order->items()->delete();
            $order->delete();
        }
        StockMovement::where('product_variant_id', $variant->id)->delete();
        $variant->forceDelete();
        $product->forceDelete();
        Customer::where('name', 'like', 'Concurrency %')->delete();

        $ok ? $this->info('PASS: no overselling under concurrent confirmation.') : $this->error('FAIL: stock invariant violated.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
