<?php

namespace App\Console\Commands;

use App\Models\KnowledgeArticle;
use App\Models\MenuItem;
use Database\Seeders\InfoDemoSeeder;
use Database\Seeders\MenuDemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills an empty restaurant with the synthetic [DEMO] menu and info articles,
 * so the site and the AI host have something to show before the real content
 * is entered. Idempotent: dishes and articles are matched by slug and never
 * updated. It refuses to run when real (non-demo) content already exists, and
 * in production it needs an explicit --confirm-production flag.
 */
#[Signature('restaurant:seed-demo-content
    {--dry-run : Show what would be added without writing anything}
    {--confirm-production : Required in production; the content is labelled [DEMO]}')]
#[Description('Add the [DEMO] menu and info articles to an empty restaurant (never touches existing content)')]
class SeedDemoContentCommand extends Command
{
    private const DEMO_SLUG_PREFIX = 'demo-';

    public function handle(): int
    {
        $realDishes = MenuItem::query()->where('slug', 'not like', self::DEMO_SLUG_PREFIX.'%')->count();
        $realArticles = KnowledgeArticle::query()->where('slug', 'not like', self::DEMO_SLUG_PREFIX.'%')->count();

        if ($realDishes > 0 || $realArticles > 0) {
            $this->components->error("Real content already exists ({$realDishes} dishes, {$realArticles} articles). Demo content is only for an empty restaurant; nothing was changed.");

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Current content: %d demo dishes, %d demo articles. Missing demo items will be added; existing ones are left as they are.',
            MenuItem::query()->count(),
            KnowledgeArticle::query()->count(),
        ));

        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        if ($this->laravel->environment('production') && ! $this->option('confirm-production')) {
            $this->components->error('This is production. Re-run with --confirm-production once the owner has approved [DEMO] content.');

            return self::FAILURE;
        }

        MenuDemoSeeder::$allowOutsideLocal = true;
        InfoDemoSeeder::$allowOutsideLocal = true;

        try {
            DB::transaction(function (): void {
                $this->laravel->make(MenuDemoSeeder::class)->setContainer($this->laravel)->setCommand($this)->__invoke();
                $this->laravel->make(InfoDemoSeeder::class)->setContainer($this->laravel)->setCommand($this)->__invoke();
            });
        } finally {
            MenuDemoSeeder::$allowOutsideLocal = false;
            InfoDemoSeeder::$allowOutsideLocal = false;
        }

        $this->components->info(sprintf(
            'Done: %d dishes, %d info articles.',
            MenuItem::query()->count(),
            KnowledgeArticle::query()->count(),
        ));

        return self::SUCCESS;
    }
}
