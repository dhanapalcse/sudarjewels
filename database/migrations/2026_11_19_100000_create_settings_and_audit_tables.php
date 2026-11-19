<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->boolean('is_published')->default(true);
            // True while the page still contains placeholder text that must be replaced before launch.
            $table->boolean('needs_review')->default(true);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('actor');
            $table->string('action', 100)->index();
            $table->nullableMorphs('subject');
            $table->json('changes')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->string('driver', 30);
            $table->string('template', 80);
            $table->string('recipient_masked', 40);
            $table->string('recipient_hash', 64)->index();
            $table->string('status', 20)->index();
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->nullableMorphs('notifiable');
            $table->string('dedupe_key')->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 40);
            $table->string('phone', 20);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['phone', 'purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
    }
};
