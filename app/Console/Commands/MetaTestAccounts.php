<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\MetaAccountController;
use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meta:accounts:test {--all : Include inactive accounts}')]
#[Description('Run a Graph API connection test for each active Meta account')]
class MetaTestAccounts extends Command
{
    public function handle(MetaMessagingService $meta): int
    {
        $accounts = MetaAccount::query()->when(! $this->option('all'), fn ($q) => $q->active())->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->warn('No Meta accounts to test.');

            return self::SUCCESS;
        }

        $rows = [];
        $failures = 0;

        foreach ($accounts as $account) {
            $result = MetaAccountController::runTest($meta, $account);
            $failures += $result['ok'] ? 0 : 1;

            $rows[] = [
                $account->id,
                $account->platform->value,
                $account->page_name,
                $account->ownExternalId(),
                $account->maskedToken(),
                $result['ok'] ? 'OK' : 'FAIL',
                $result['ok']
                    ? trim(($result['name'] ?? '').' '.(isset($result['expires_at']) && $result['expires_at'] ? 'expires '.$result['expires_at'] : ''))
                    : ($result['error'] ?? 'unknown error'),
            ];
        }

        $this->table(['ID', 'Platform', 'Name', 'External ID', 'Token', 'Result', 'Details'], $rows);

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
