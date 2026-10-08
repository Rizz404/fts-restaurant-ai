<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guest requests (Plan 6): reservation, order, feedback, special request.
     *
     * Retention shape: a guest session purge deletes its conversations but
     * keeps requests (guest_session_id becomes null). Closed requests are
     * deleted before their drafts (the draft/request link is circular);
     * the circular link is added after both tables exist.
     */
    public function up(): void
    {
        Schema::create('request_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('guest_session_id')->nullable()->constrained('guest_sessions')->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            // At most one draft per AI turn (Plan 7).
            $table->foreignId('originating_turn_id')->nullable()->unique()->constrained('ai_turns')->nullOnDelete();
            $table->string('type', 24);
            $table->foreignId('team_id')->constrained('staff_teams')->restrictOnDelete();
            $table->jsonb('payload');
            // Contact is entered by the guest in the review step, never by the AI.
            $table->string('contact_name', 80)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_email', 255)->nullable();
            $table->string('table_label', 20)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->string('payload_fingerprint', 64);
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->index(['guest_session_id', 'created_at']);
            $table->index('expires_at');
        });

        Schema::create('guest_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('reference', 32)->unique();
            $table->string('type', 24);
            $table->foreignId('team_id')->constrained('staff_teams')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('guest_session_id')->nullable()->constrained('guest_sessions')->nullOnDelete();
            $table->string('contact_name', 80)->nullable();
            $table->string('contact_phone', 32)->nullable();
            $table->string('contact_phone_digits', 32)->nullable()->index();
            $table->string('contact_email', 255)->nullable();
            $table->string('table_label', 20)->nullable();
            $table->string('locale', 5);
            $table->jsonb('payload');
            // Orders: frozen unit prices and total (set at confirmation).
            $table->jsonb('pricing')->nullable();
            $table->date('reserved_date')->nullable();
            // HH:MM in the restaurant timezone.
            $table->string('reserved_slot', 5)->nullable();
            $table->unsignedInteger('party_size')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            // One request per draft; purge deletes the request first, then the draft.
            $table->foreignId('draft_id')->unique()->constrained('request_drafts')->restrictOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('confirmed_at');
            $table->timestampTz('terminal_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'updated_at']);
            $table->index(['type', 'reserved_date', 'reserved_slot']);
            $table->index('guest_session_id');
            $table->index('terminal_at');
        });

        // Circular link added after both tables exist.
        Schema::table('request_drafts', function (Blueprint $table) {
            $table->foreignId('consumed_request_id')->nullable()->after('expires_at')->constrained('guest_requests')->nullOnDelete();
        });

        Schema::create('request_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('guest_requests')->cascadeOnDelete();
            $table->string('author_type', 8);
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 16);
            $table->text('text');
            $table->timestampTz('created_at');

            $table->index(['request_id', 'id']);
        });

        Schema::create('request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('guest_requests')->cascadeOnDelete();
            $table->string('actor_type', 8);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_code', 32);
            $table->string('previous_status', 16)->nullable();
            $table->string('next_status', 16)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index(['request_id', 'id']);
        });

        Schema::create('operation_receipts', function (Blueprint $table) {
            $table->id();
            // guest_session or user; polymorphic, so no foreign key.
            $table->string('actor_type', 16);
            $table->unsignedBigInteger('actor_id');
            $table->string('operation_type', 48);
            $table->uuid('idempotency_key');
            $table->string('fingerprint', 64);
            $table->string('resource_type', 32);
            $table->string('resource_public_id', 64);
            $table->timestampTz('created_at');

            $table->unique(['actor_type', 'actor_id', 'operation_type', 'idempotency_key'], 'operation_receipts_actor_key_unique');
            $table->index(['resource_type', 'resource_public_id']);
            $table->index('created_at');
        });

        // Seats held per slot. Pending and confirmed reservations hold seats.
        Schema::create('reservation_slot_usage', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('slot_start', 5);
            $table->unsignedInteger('booked_seats')->default(0);
            $table->timestamps();

            $table->unique(['date', 'slot_start']);
        });

        Schema::create('closed_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Laravel database notifications for staff.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('closed_dates');
        Schema::dropIfExists('reservation_slot_usage');
        Schema::dropIfExists('operation_receipts');
        Schema::dropIfExists('request_events');
        Schema::dropIfExists('request_messages');
        Schema::table('request_drafts', fn (Blueprint $table) => $table->dropConstrainedForeignId('consumed_request_id'));
        Schema::dropIfExists('guest_requests');
        Schema::dropIfExists('request_drafts');
    }
};
