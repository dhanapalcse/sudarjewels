<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('code', 32)->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('phone', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('social_handle')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable();
            // Payout details are encrypted at rest (model casts).
            $table->string('payout_method', 20)->nullable();
            $table->text('payout_account_name')->nullable();
            $table->text('payout_account_number')->nullable();
            $table->text('payout_ifsc')->nullable();
            $table->text('payout_upi_id')->nullable();
            $table->timestamp('payout_details_updated_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('landing_path', 500);
            $table->string('visitor_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['affiliate_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_visits');
        Schema::dropIfExists('affiliates');
    }
};
