<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // One registration per order; later-delivered units are attached to it.
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // As entered by the customer; the order's own name is never rewritten.
            $table->string('registered_name');
            $table->string('registered_phone', 20);
            $table->string('channel', 20)->default('self_service');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('registered_at');
            $table->timestamps();
        });

        Schema::create('purchased_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('unit_number');
            $table->string('code', 40)->unique();
            $table->string('status', 20)->default('allocated')->index();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('restocked_at')->nullable();
            $table->foreignId('restock_approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('refund_item_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();
            // Warranty snapshot and coverage (stored in UTC, computed in business timezone).
            $table->boolean('warranty_eligible')->default(true);
            $table->unsignedTinyInteger('warranty_months')->default(3);
            $table->text('warranty_terms')->nullable();
            $table->string('warranty_status', 20)->default('unregistered')->index();
            $table->timestamp('warranty_starts_at')->nullable();
            $table->timestamp('warranty_ends_at')->nullable();
            $table->foreignId('warranty_registration_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('warranty_registered_at')->nullable();
            $table->foreignId('replacement_of_unit_id')->nullable()->constrained('purchased_units')->nullOnDelete();
            $table->string('replacement_policy', 20)->nullable();
            $table->timestamps();
            $table->unique(['order_item_id', 'unit_number']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 32)->nullable()->unique();
            $table->foreignId('purchased_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warranty_registration_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('submitted')->index();
            $table->text('issue_description');
            $table->text('decision_reason')->nullable();
            $table->string('resolution', 30)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('replacement_unit_id')->nullable()->constrained('purchased_units')->nullOnDelete();
            $table->foreignId('decided_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('claim_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('author');
            $table->string('status_from', 30)->nullable();
            $table->string('status_to', 30)->nullable();
            $table->text('message')->nullable();
            $table->boolean('visible_to_customer')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('claim_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_update_id')->nullable()->constrained()->nullOnDelete();
            // Always a private disk; served only through an authorised controller.
            $table->string('disk', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_attachments');
        Schema::dropIfExists('claim_updates');
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('purchased_units');
        Schema::dropIfExists('warranty_registrations');
    }
};
