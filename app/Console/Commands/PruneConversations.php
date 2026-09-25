<?php

namespace App\Console\Commands;

use App\Services\Contacts\ContactDeletionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Data retention: hard-delete contacts (conversations + messages) with no activity for N months.
 * The Privacy Policy states this period (config legal.retention.conversations_months / DATA_RETENTION_MONTHS).
 * Scheduled daily in routes/console.php. A value of 0 disables pruning.
 */
#[Signature('contacts:prune {--months= : Delete contacts inactive for this many months (default: DATA_RETENTION_MONTHS)} {--dry-run : Only count what would be deleted} {--chunk=200 : Contacts deleted per batch}')]
#[Description('Delete contacts (conversations and their messages) inactive longer than the retention period')]
class PruneConversations extends Command
{
    public function handle(ContactDeletionService $deletion): int
    {
        $option = $this->option('months');
        $months = $option === null || $option === ''
            ? (int) config('legal.retention.conversations_months', 12)
            : (int) $option;

        if ($option !== null && $option !== '' && ! ctype_digit((string) $option)) {
            $this->error('--months must be a whole number.');

            return self::FAILURE;
        }

        if ($months < 1) {
            $this->info('Contact retention pruning is disabled (retention months = 0).');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subMonths($months);
        $dryRun = (bool) $this->option('dry-run');
        $result = $deletion->prune($cutoff, $dryRun, max(1, (int) $this->option('chunk')));

        $this->info(sprintf(
            '%s %d contact(s) and %d message(s) inactive since before %s (%d month%s).',
            $dryRun ? '[dry run] Would delete' : 'Deleted',
            $result['conversations'],
            $result['messages'],
            $cutoff->toDateString(),
            $months,
            $months === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
