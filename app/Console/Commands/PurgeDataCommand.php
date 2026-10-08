<?php

namespace App\Console\Commands;

use App\Modules\Operations\Retention\PurgeExpiredData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Applies the retention baseline. --dry-run only counts expired records
 * (no personal data is printed). Scheduled daily at 02:00 Asia/Jakarta.
 */
#[Signature('restaurant:purge-data {--dry-run : Count expired records without deleting}')]
#[Description('Delete closed requests, drafts, conversations, guest sessions, usage rows, audit events, and receipts past retention')]
class PurgeDataCommand extends Command
{
    public function handle(PurgeExpiredData $purge): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $counts = $dryRun ? $purge->counts() : $purge->run();

        $this->table(
            [__('ui.operations.retention.category'), $dryRun ? __('ui.operations.retention.expired') : __('ui.operations.retention.deleted')],
            collect($counts)->map(fn (int $count, string $category) => [$category, $count])->values()->all(),
        );

        Log::info('retention.purge', ['dry_run' => $dryRun] + $counts);

        return self::SUCCESS;
    }
}
