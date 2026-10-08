<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton row (id = 1) seeded by ReferenceSeeder. Brand and contact
     * links are Plan 3; opening hours and reservation limits are Plan 6
     * (nullable columns: null means "use config/restaurant.php").
     */
    public function up(): void
    {
        Schema::create('restaurant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name', 160);
            $table->jsonb('welcome_text');
            $table->jsonb('contact_links')->nullable();
            $table->jsonb('opening_hours')->nullable();
            $table->unsignedInteger('slot_capacity')->nullable();
            $table->unsignedInteger('max_party_size')->nullable();
            $table->unsignedInteger('min_lead_minutes')->nullable();
            $table->unsignedInteger('max_advance_days')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_settings');
    }
};
