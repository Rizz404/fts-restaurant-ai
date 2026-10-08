<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Anonymous guest sessions. Rows are created lazily on the first write
     * (AI turn, draft, confirmation), never on a page view, and hold no
     * personal data. Expiry and purge are handled by restaurant:purge-data.
     */
    public function up(): void
    {
        Schema::create('guest_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('locale', 5);
            $table->string('table_label', 20)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('expires_at');

            $table->index('expires_at');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_sessions');
    }
};
