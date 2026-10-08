<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu CMS (Plan 4). Dishes are edited in place (price, availability and
     * allergens apply immediately and are audited); only info articles keep
     * immutable revisions. Allergen codes and dietary tags come from
     * config/restaurant.php, so they are plain strings here.
     */
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 120);

            $table->unique(['category_id', 'locale']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->foreignId('category_id')->constrained('menu_categories')->restrictOnDelete();
            $table->string('status', 16);
            // Smallest currency unit (IDR has no decimals).
            $table->unsignedBigInteger('price')->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->string('photo_path', 255)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_chef_pick')->default(false);
            $table->unsignedTinyInteger('spice_level')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampTz('allergen_info_verified_at')->nullable();
            $table->foreignId('allergen_info_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('first_published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['status', 'category_id', 'sort_order']);
            $table->index(['status', 'is_chef_pick', 'is_available']);
        });

        Schema::create('menu_item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 120)->default('');
            $table->text('description');
            $table->jsonb('aliases');
            // Derived, NFKC + lowercase; written by the model, used by retrieval.
            $table->text('search_name');
            $table->text('search_description');
            $table->text('search_keywords');

            $table->unique(['item_id', 'locale']);
        });

        Schema::create('menu_item_allergens', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('allergen_code', 32);
            $table->string('relation', 16);

            $table->primary(['item_id', 'allergen_code']);
            $table->index('allergen_code');
        });

        Schema::create('menu_item_dietary_tags', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('menu_items')->cascadeOnDelete();
            $table->string('tag', 32);

            $table->primary(['item_id', 'tag']);
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_dietary_tags');
        Schema::dropIfExists('menu_item_allergens');
        Schema::dropIfExists('menu_item_translations');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_category_translations');
        Schema::dropIfExists('menu_categories');
    }
};
