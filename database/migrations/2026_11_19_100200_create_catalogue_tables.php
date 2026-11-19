<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'is_active', 'sort_order']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 64)->unique();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->text('materials')->nullable();
            $table->text('care_instructions')->nullable();
            // Money is stored as integer paise.
            $table->unsignedInteger('regular_price_paise')->nullable();
            $table->unsignedInteger('price_paise');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->boolean('is_new_arrival')->default(false);
            $table->boolean('commission_enabled')->default(true);
            // Basis points (2000 = 20.00 %). NULL inherits the global rate; 0 is an explicit 0 %.
            $table->unsignedSmallInteger('commission_rate_bp')->nullable();
            $table->boolean('warranty_eligible')->default(true);
            $table->unsignedTinyInteger('warranty_months')->nullable();
            $table->text('warranty_terms')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_sample')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id']);
            $table->index(['status', 'subcategory_id']);
            $table->index(['status', 'price_paise']);
            $table->index(['status', 'published_at']);
            $table->index(['status', 'is_new_arrival']);
            $table->index(['status', 'is_best_seller']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name')->nullable();
            $table->string('finish', 60)->nullable()->index();
            $table->string('size', 40)->nullable()->index();
            $table->string('colour', 40)->nullable()->index();
            $table->unsignedInteger('price_paise')->nullable();
            $table->unsignedInteger('regular_price_paise')->nullable();
            $table->integer('stock_on_hand')->default(0);
            $table->integer('stock_reserved')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['product_id', 'is_active']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20); // upload | google_drive | sample
            $table->string('drive_file_id', 128)->nullable()->index();
            $table->string('drive_file_name')->nullable();
            // Private staging path of an uploaded original awaiting processing.
            $table->string('staging_path')->nullable();
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            // Published, optimised renditions (only replaced after a successful import).
            $table->json('renditions')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_main')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'is_main', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
