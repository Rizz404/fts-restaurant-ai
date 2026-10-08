<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restaurant info articles (hours, location, policy, FAQ). Same revision
     * model as the base project; the audience is always the public, so there
     * are no department tables or columns.
     */
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('knowledge_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_articles')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16);
            $table->string('category', 16)->default('general');
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_until')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'version']);
            $table->index(['status', 'category']);
            $table->index(['status', 'effective_from', 'effective_until']);
        });

        // One published and at most one draft revision per article. Partial
        // indexes work on PostgreSQL (target) and SQLite (local).
        DB::statement("CREATE UNIQUE INDEX knowledge_revisions_one_published ON knowledge_revisions (article_id) WHERE status = 'published'");
        DB::statement("CREATE UNIQUE INDEX knowledge_revisions_one_draft ON knowledge_revisions (article_id) WHERE status = 'draft'");

        Schema::create('knowledge_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('knowledge_revisions')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 180)->default('');
            $table->text('body');
            $table->jsonb('tags');
            $table->jsonb('aliases');
            // Derived, NFKC + lowercase; written by the model, used by retrieval.
            $table->text('search_title');
            $table->text('search_body');
            $table->text('search_keywords');
            $table->timestamps();

            $table->unique(['revision_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_translations');
        DB::statement('DROP INDEX IF EXISTS knowledge_revisions_one_draft');
        DB::statement('DROP INDEX IF EXISTS knowledge_revisions_one_published');
        Schema::dropIfExists('knowledge_revisions');
        Schema::dropIfExists('knowledge_articles');
    }
};
