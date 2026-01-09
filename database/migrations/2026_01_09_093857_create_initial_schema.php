<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Core Laravel tables
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
                $table->text('settings')->nullable();
                $table->boolean('is_admin')->default(false);
                $table->json('roles')->nullable();
                $table->json('options')->nullable();
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // Application tables
        if (!Schema::hasTable('properties')) {
            Schema::create('properties', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('name');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->json('options')->nullable();
            });
        }

        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties');
                $table->string('name');
                $table->string('slug');
                $table->string('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->string('unit_type')->nullable()->index();
                $table->integer('bedrooms')->nullable();
                $table->integer('max_guests')->nullable();
                $table->json('options')->nullable();
            });
        }

        if (!Schema::hasTable('ical_sources')) {
            Schema::create('ical_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
                $table->string('name');
                $table->string('type')->default('ical');
                $table->string('url');
                $table->boolean('sync_enabled')->default(true);
                $table->timestamps();
                $table->timestamp('last_synced_at')->nullable();
                $table->text('last_error')->nullable();
            });
        }

        if (!Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
                $table->string('uid')->nullable()->index();
                $table->string('source_name')->nullable();
                $table->string('status')->default('undefined');
                $table->string('guest_name');
                $table->date('check_in');
                $table->date('check_out');
                $table->integer('adults')->nullable();
                $table->integer('children')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->decimal('commission', 10, 2)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_manual')->default(false)->index();
                $table->unsignedBigInteger('group_id')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
                $table->unsignedBigInteger('property_id')->nullable()->index();
                $table->integer('guests')->nullable();
                $table->json('sync_data')->nullable();
                $table->json('metadata')->nullable();

                $table->index(['unit_id', 'check_in', 'check_out']);
            });
        }

        if (!Schema::hasTable('property_user')) {
            Schema::create('property_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties');
                $table->foreignId('user_id')->constrained('users');
                $table->string('role')->default('user');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('source_events')) {
            Schema::create('source_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
                $table->string('source_type', 20);
                $table->unsignedBigInteger('source_id');
                $table->string('source_event_id');
                $table->unsignedBigInteger('property_id');
                $table->timestamps();

                $table->unique(['source_type', 'source_id', 'source_event_id', 'property_id'], 'source_events_unique');
                $table->index(['booking_id']);
                $table->index(['source_type', 'source_id']);
                $table->index(['property_id', 'source_type']);
            });
        }

        if (!Schema::hasTable('source_mappings')) {
            Schema::create('source_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
                $table->string('control_string')->unique();
                $table->timestamps();

                $table->index(['booking_id']);
                $table->index(['control_string']);
            });
        }

        if (!Schema::hasTable('rates_calculations')) {
            Schema::create('rates_calculations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
                $table->decimal('total_amount', 10, 2);
                $table->decimal('base_amount', 10, 2);
                $table->json('calculation_snapshot');
                $table->timestamps();

                $table->index(['booking_id']);
            });
        }

        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
                $table->decimal('discount_amount', 10, 2);
                $table->string('discount_type')->default('percentage');
                $table->json('conditions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['property_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('rates')) {
            Schema::create('rates', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable()->unique();
                $table->foreignId('unit_id')->nullable()->constrained('units')->onDelete('cascade');
                $table->string('unit_type')->nullable();
                $table->foreignId('property_id')->nullable()->constrained('properties')->onDelete('cascade');
                $table->decimal('base', 10, 2);
                $table->string('calculation_formula')->default('booking_nights * rate');
                $table->boolean('is_active')->default(true);
                $table->string('priority')->nullable()->default('normal');
                $table->timestamps();
                $table->foreignId('parent_rate_id')->nullable()->constrained('rates')->onDelete('set null');
                $table->date('booking_from')->nullable();
                $table->date('booking_to')->nullable();
                $table->date('stay_from')->nullable();
                $table->date('stay_to')->nullable();
                $table->json('conditions')->nullable();
                $table->string('coupon_code')->nullable();
                $table->json('options')->nullable();

                $table->index(['is_active', 'priority']);
            });
        }

        if (!Schema::hasTable('sync_logs')) {
            Schema::create('sync_logs', function (Blueprint $table) {
                $table->id();
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->string('source');
                $table->string('field');
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->timestamp('created_at');

                $table->index(['model_type', 'model_id']);
                $table->index(['created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('rates');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('rates_calculations');
        Schema::dropIfExists('source_mappings');
        Schema::dropIfExists('source_events');
        Schema::dropIfExists('property_user');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('ical_sources');
        Schema::dropIfExists('units');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
