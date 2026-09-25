<?php

namespace App\Console\Commands;

use App\Models\WebhookEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meta:prune-webhook-events {--days=30 : Delete events older than this many days} {--include-pending : Also delete events still pending}')]
#[Description('Delete old stored Meta webhook events')]
class PruneWebhookEvents extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be at least 1.');

            return self::FAILURE;
        }

        $query = WebhookEvent::query()->where('created_at', '<', now()->subDays($days));

        if (! $this->option('include-pending')) {
            $query->where('status', '!=', 'pending');
        }

        $deleted = 0;

        // Chunked deletes keep lock times short on large tables.
        do {
            $ids = (clone $query)->limit(1000)->pluck('id');
            $count = $ids->isEmpty() ? 0 : WebhookEvent::query()->whereKey($ids)->delete();
            $deleted += $count;
        } while ($count > 0);

        $this->info("Deleted {$deleted} webhook event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
