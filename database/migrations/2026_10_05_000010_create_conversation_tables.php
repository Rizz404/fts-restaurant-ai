<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guest AI conversations (Plan 5). Conversations belong to an anonymous
     * guest session and are deleted with it (cascade) when the session is
     * purged. Evidence points at dishes or info revisions by type + id.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('guest_session_id')->constrained('guest_sessions')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->jsonb('scene_context');
            $table->unsignedInteger('context_revision')->default(1);
            $table->timestampTz('last_activity_at');
            $table->timestamps();

            $table->index(['guest_session_id', 'last_activity_at']);
            $table->index('last_activity_at');
        });

        Schema::create('ai_turns', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_turn_id');
            $table->string('request_fingerprint', 64);
            $table->string('status', 16);
            $table->string('locale', 5);
            $table->jsonb('context_snapshot');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('deadline_at');
            $table->string('error_code', 64)->nullable();
            // answered, no_source, model_no_source, ai_unavailable, budget_exhausted, ...
            $table->string('outcome', 64)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('model', 160)->nullable();
            $table->string('prompt_version', 32);
            $table->jsonb('usage')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'client_turn_id']);
            $table->index(['status', 'started_at']);
            $table->index('started_at');
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('turn_id')->nullable()->constrained('ai_turns')->nullOnDelete();
            $table->string('role', 16);
            $table->text('text');
            $table->jsonb('ui')->nullable();
            $table->timestampTz('created_at');

            $table->index(['conversation_id', 'id']);
        });

        // Which dishes and info revisions a turn used; only a hash of the
        // content is stored, never the content itself.
        Schema::create('turn_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained('ai_turns')->cascadeOnDelete();
            $table->string('evidence_type', 16);
            $table->unsignedBigInteger('evidence_id');
            $table->string('locale', 5);
            $table->string('excerpt_hash', 64);

            $table->unique(['turn_id', 'evidence_type', 'evidence_id', 'locale']);
            $table->index(['evidence_type', 'evidence_id']);
        });

        Schema::create('tool_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained('ai_turns')->cascadeOnDelete();
            $table->string('call_id', 128);
            $table->string('name', 64);
            $table->string('status', 32);
            $table->jsonb('validated_input')->nullable();
            $table->jsonb('result_reference')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();

            $table->index(['turn_id', 'id']);
        });

        // Daily AI turn counters: one row per guest session per day, and one
        // global row per day (guest_session_id null).
        Schema::create('assistant_usage', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date');
            $table->foreignId('guest_session_id')->nullable()->constrained('guest_sessions')->cascadeOnDelete();
            $table->unsignedInteger('turns')->default(0);

            $table->unique(['usage_date', 'guest_session_id']);
            $table->index('usage_date');
        });

        // NULLs are distinct in a plain unique index, so the global row needs its own.
        DB::statement('CREATE UNIQUE INDEX assistant_usage_one_global_row ON assistant_usage (usage_date) WHERE guest_session_id IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS assistant_usage_one_global_row');
        Schema::dropIfExists('assistant_usage');
        Schema::dropIfExists('tool_invocations');
        Schema::dropIfExists('turn_evidence');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('ai_turns');
        Schema::dropIfExists('conversations');
    }
};
