<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('customer_phone', 20)->index();
            $table->string('customer_email')->nullable();
            $table->boolean('delivery_required')->default(true);
            $table->string('shipping_name')->nullable();
            $table->string('shipping_phone', 20)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 2)->default('IN');

            $table->string('channel', 20)->index();
            $table->string('status', 20)->default('draft')->index();
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->string('fulfilment_status', 20)->default('unfulfilled')->index();

            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('attribution_source', 30)->nullable();
            $table->string('referral_code', 32)->nullable();
            $table->timestamp('attributed_at')->nullable();

            $table->unsignedInteger('order_discount_paise')->default(0);
            $table->unsignedInteger('subtotal_paise')->default(0);
            $table->unsignedInteger('discount_total_paise')->default(0);
            $table->unsignedInteger('tax_paise')->default(0);
            $table->unsignedInteger('shipping_paise')->default(0);
            $table->unsignedInteger('grand_total_paise')->default(0);
            $table->unsignedInteger('amount_paid_paise')->default(0);
            $table->unsignedInteger('amount_refunded_paise')->default(0);
            $table->unsignedInteger('commission_estimate_paise')->default(0);

            $table->unsignedSmallInteger('quote_revision')->default(0);
            $table->timestamp('quote_revised_at')->nullable();
            $table->string('idempotency_key', 64)->nullable();

            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('cancellation_reason', 500)->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('analytics_reported_at')->nullable();
            $table->timestamps();

            $table->unique(['created_by_id', 'idempotency_key']);
            $table->index(['affiliate_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // Purchase snapshot — never rewritten by later catalogue edits.
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku', 64);
            $table->json('variant_attributes')->nullable();
            $table->unsignedInteger('unit_price_paise');
            $table->unsignedInteger('regular_price_paise')->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedInteger('line_subtotal_paise');
            $table->unsignedInteger('item_discount_paise')->default(0);
            $table->unsignedInteger('allocated_discount_paise')->default(0);
            $table->unsignedInteger('tax_paise')->default(0);
            $table->unsignedInteger('line_total_paise');
            $table->boolean('commission_enabled')->default(true);
            $table->unsignedSmallInteger('commission_rate_bp')->default(0);
            $table->unsignedInteger('commission_eligible_paise')->default(0);
            $table->unsignedInteger('commission_paise')->default(0);
            $table->boolean('warranty_eligible')->default(true);
            $table->unsignedTinyInteger('warranty_months')->default(3);
            $table->text('warranty_terms')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('field', 30);
            $table->string('from', 40)->nullable();
            $table->string('to', 40)->nullable();
            $table->text('note')->nullable();
            $table->nullableMorphs('actor');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->json('changes');
            $table->string('reason', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['order_id', 'revision']);
        });

        Schema::create('order_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status', 20)->default('open')->index();
            $table->text('response')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('courier', 100)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->boolean('is_handover')->default(false);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->unsignedSmallInteger('fulfilled_quantity')->default(0);
            $table->unsignedSmallInteger('released_quantity')->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->nullableMorphs('source');
            // Idempotency key: the same source event can never move stock twice.
            $table->string('reference', 120)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('order_change_requests');
        Schema::dropIfExists('order_revisions');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
