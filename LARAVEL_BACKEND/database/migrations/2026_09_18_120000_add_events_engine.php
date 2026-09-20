<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'enable_events')) {
                $table->boolean('enable_events')->default(true)->after('enable_bookings');
            }
            if (! Schema::hasColumn('company_settings', 'event_reminder_hours')) {
                $table->unsignedSmallInteger('event_reminder_hours')->default(24)->after('enable_events');
            }
        });

        Schema::create('event_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_series_id')->nullable()->constrained('event_series')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('format', 24)->default('in_person'); // in_person, online, hybrid
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->string('online_url', 2048)->nullable();
            $table->string('timezone', 64)->default('Africa/Nairobi');
            $table->string('status', 24)->default('draft'); // draft, published, cancelled, completed
            $table->string('visibility', 24)->default('unlisted'); // public, unlisted
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->string('organizer_name')->nullable();
            $table->string('organizer_contact')->nullable();
            $table->json('custom_fields')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('event_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('venue_name')->nullable();
            $table->text('venue_address')->nullable();
            $table->string('online_url', 2048)->nullable();
            $table->string('status', 24)->default('scheduled'); // scheduled, cancelled, completed
            $table->timestamps();

            $table->index(['event_id', 'starts_at']);
            $table->index(['company_id', 'starts_at']);
        });

        Schema::create('event_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_session_id')->nullable()->constrained('event_sessions')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 8)->default('KES');
            $table->unsignedInteger('quantity_total')->nullable();
            $table->timestamp('sales_start_at')->nullable();
            $table->timestamp('sales_end_at')->nullable();
            $table->unsignedSmallInteger('min_per_order')->default(1);
            $table->unsignedSmallInteger('max_per_order')->default(10);
            $table->boolean('is_free')->default(false);
            $table->string('status', 24)->default('active'); // active, archived
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->foreignId('event_ticket_type_id')->constrained('event_ticket_types')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_product_id')->nullable()->constrained('order_products')->nullOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status', 32)->default('pending_payment');
            $table->timestamp('hold_expires_at')->nullable();
            $table->json('answers')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['event_session_id', 'status']);
            $table->index(['order_id']);
        });

        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_session_id')->constrained('event_sessions')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('ticket_code', 32)->unique();
            $table->string('qr_secret', 64)->unique();
            $table->timestamp('checked_in_at')->nullable();
            $table->string('status', 24)->default('pending'); // pending, valid, cancelled, used
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['company_id', 'ticket_code']);
        });

        Schema::create('event_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_attendee_id')->constrained('event_attendees')->cascadeOnDelete();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 24)->default('dashboard'); // dashboard, scan, manual
            $table->timestamp('checked_in_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_check_ins');
        Schema::dropIfExists('event_attendees');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_ticket_types');
        Schema::dropIfExists('event_sessions');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_series');

        Schema::table('company_settings', function (Blueprint $table) {
            if (Schema::hasColumn('company_settings', 'event_reminder_hours')) {
                $table->dropColumn('event_reminder_hours');
            }
            if (Schema::hasColumn('company_settings', 'enable_events')) {
                $table->dropColumn('enable_events');
            }
        });
    }
};
