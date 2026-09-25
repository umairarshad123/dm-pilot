<?php

namespace App\Console\Commands;

use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use App\Services\Meta\MetaTokenService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('meta:connect
    {--page=* : Page ID(s) to connect (skips the interactive choice)}
    {--all : Connect every Page the token can access}
    {--subscribe : Subscribe each saved account to the app webhooks}')]
#[Description('Exchange a Graph API Explorer user token for Page tokens and save Facebook/Instagram accounts')]
class MetaConnect extends Command
{
    private const PERMISSIONS = 'pages_show_list, pages_messaging, pages_manage_metadata, pages_read_engagement, instagram_basic, instagram_manage_messages, business_management';

    public function handle(MetaTokenService $tokens, MetaMessagingService $meta): int
    {
        $this->line('Get a User access token from https://developers.facebook.com/tools/explorer (select this app) with:');
        $this->line('  '.self::PERMISSIONS);

        $shortToken = trim((string) $this->secret('Paste the user access token (input hidden)'));
        if ($shortToken === '') {
            $this->error('No token given.');

            return self::FAILURE;
        }

        try {
            $longLived = $tokens->exchangeForLongLivedUserToken($shortToken);
            $this->info('Exchanged for a long-lived user token'.($longLived['expires_at'] ? ' (expires '.$longLived['expires_at']->toDateString().')' : '').'.');

            $this->reportScopes($tokens, $longLived['access_token']);

            $pages = $tokens->listPages($longLived['access_token']);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($pages === []) {
            $this->error('No Pages found. The token needs pages_show_list and you must manage at least one Page.');

            return self::FAILURE;
        }

        $this->table(['Page ID', 'Page', 'Instagram'], array_map(fn (array $p) => [
            $p['id'],
            $p['name'],
            $p['instagram_business_account'] ? '@'.($p['instagram_business_account']['username'] ?? '?').' ('.$p['instagram_business_account']['id'].')' : '-',
        ], $pages));

        $selected = $this->selectPages($pages);
        if ($selected === null) {
            return self::FAILURE;
        }

        $rows = [];
        foreach ($selected as $page) {
            foreach ($tokens->upsertAccountsForPage($page) as $account) {
                $rows[] = [
                    $account->id,
                    $account->platform->value,
                    $account->page_name,
                    $account->page_id,
                    $account->instagram_account_id ?? '-',
                    $account->maskedToken(),
                    $account->wasRecentlyCreated ? 'created' : 'updated',
                    $this->option('subscribe') ? $this->subscribe($meta, $account) : '-',
                ];
            }
        }

        $this->table(['ID', 'Platform', 'Name', 'Page ID', 'IG ID', 'Token', 'Saved', 'Webhooks'], $rows);
        $this->info('Done. Page tokens obtained from a long-lived user token do not expire.');

        if (! $this->option('subscribe')) {
            $this->line('Tip: re-run with --subscribe, or use "Subscribe webhooks" in the admin, so Meta delivers messages to this app.');
        }

        return self::SUCCESS;
    }

    /** @return list<array>|null */
    private function selectPages(array $pages): ?array
    {
        if ($this->option('all')) {
            return $pages;
        }

        $wanted = array_map('strval', (array) $this->option('page'));
        if ($wanted !== []) {
            $selected = array_values(array_filter($pages, fn (array $p) => in_array($p['id'], $wanted, true)));
            $missing = array_diff($wanted, array_column($selected, 'id'));
            if ($missing !== []) {
                $this->error('Page(s) not accessible with this token: '.implode(', ', $missing));

                return null;
            }

            return $selected;
        }

        $labels = [];
        foreach ($pages as $p) {
            $labels[$p['id']] = $p['name'].' ['.$p['id'].']';
        }

        $choices = (array) $this->choice('Which Pages should be connected? (comma-separated)', array_values($labels), 0, null, true);

        return array_values(array_filter($pages, fn (array $p) => in_array($labels[$p['id']], $choices, true)));
    }

    private function reportScopes(MetaTokenService $tokens, string $token): void
    {
        try {
            $debug = $tokens->debugToken($token);
        } catch (RuntimeException) {
            return; // informational only
        }

        $required = array_map('trim', explode(',', self::PERMISSIONS));
        $missing = array_diff($required, $debug['scopes']);

        if ($missing !== [] && $debug['scopes'] !== []) {
            $this->warn('Token is missing permissions: '.implode(', ', $missing));
        }
    }

    private function subscribe(MetaMessagingService $meta, MetaAccount $account): string
    {
        try {
            $result = $meta->subscribeApp($account);
        } catch (Throwable $e) {
            return 'error ('.class_basename($e).')';
        }

        return $result['ok'] ? 'subscribed' : 'failed: '.($result['error'] ?? '?');
    }
}
