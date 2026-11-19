<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('processing')->index();
            $table->string('method', 20)->nullable();
            $table->bigInteger('amount_paise');
            $table->string('reference')->nullable();
            $table->date('paid_on')->nullable();
            // Masked snapshot of the payout destination at payment time (encrypted).
            $table->text('destination_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('refund_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reverses_entry_id')->nullable()->constrained('commission_entries')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->index();
            // Signed: earnings are positive, reversals/adjustments negative.
            $table->bigInteger('amount_paise');
            $table->unsignedSmallInteger('rate_bp')->nullable();
            $table->unsignedInteger('eligible_paise')->nullable();
            $table->string('description', 500)->nullable();
            // Idempotency key per business event (e.g. "earning:item:12").
            $table->string('source_key', 120)->unique();
            // Set while held by a processing/completed payout; cleared if that payout fails.
            $table->foreignId('payout_id')->nullable()->constrained('affiliate_payouts')->nullOnDelete();
            $table->timestamp('payable_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['affiliate_id', 'status']);
        });

        Schema::create('payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_entry_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_paise');
            $table->timestamps();
            $table->unique(['affiliate_payout_id', 'commission_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_items');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('affiliate_payouts');
    }
};
