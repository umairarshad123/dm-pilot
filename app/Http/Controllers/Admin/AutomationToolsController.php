<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\MetaAccount;
use App\Services\Automation\AutomationMatcher;
use App\Services\Automation\KeywordMatcher;
use App\Services\Automation\ReplyTemplate;
use App\Support\CurrentPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read-only JSON helpers for the Automations editor. Nothing here writes to the database. */
class AutomationToolsController extends Controller
{
    /** Live validation of regex keywords (same compiler the matcher uses). */
    public function regex(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patterns' => ['required', 'array', 'max:50'],
            'patterns.*' => ['nullable', 'string', 'max:1000'],
        ]);

        $errors = [];

        foreach ($data['patterns'] as $i => $pattern) {
            $errors[$i] = KeywordMatcher::regexError(trim((string) $pattern));
        }

        return response()->json(['errors' => $errors]);
    }

    /** Render a reply template with sample values. */
    public function preview(Request $request, CurrentPage $currentPage): JsonResponse
    {
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:2000'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'meta_account_id' => ['nullable', 'integer', 'exists:meta_accounts,id'],
        ]);

        $account = isset($data['meta_account_id']) ? MetaAccount::find($data['meta_account_id']) : $currentPage->account();
        $name = $data['customer_name'] ?? 'Sara Khan';

        return response()->json([
            'text' => ReplyTemplate::render((string) ($data['text'] ?? ''), ReplyTemplate::variables($name, $account?->page_name ?? 'Your Page')),
        ]);
    }

    /** Which rule (if any) would answer a customer message on the selected page. */
    public function test(Request $request, CurrentPage $currentPage, AutomationMatcher $matcher): JsonResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'first_contact' => ['nullable', 'boolean'],
            'meta_account_id' => ['nullable', 'integer', 'exists:meta_accounts,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
        ]);

        $account = array_key_exists('meta_account_id', $data)
            ? ($data['meta_account_id'] ? MetaAccount::find($data['meta_account_id']) : null)
            : $currentPage->account();

        if (! filter_var(config('bot.automations.enabled', true), FILTER_VALIDATE_BOOL)) {
            return response()->json(['matched' => false, 'reason' => 'disabled', 'message' => 'Automations are switched off on the server (BOT_AUTOMATIONS_ENABLED=false), so the AI answers everything.']);
        }

        $rule = $matcher->matchText($account, $data['text'], null, (bool) ($data['first_contact'] ?? false));

        if ($rule === null) {
            return response()->json(['matched' => false, 'reason' => 'ai', 'message' => 'No rule matches: the AI would answer this message.']);
        }

        $reply = ReplyTemplate::render($rule->reply_text, ReplyTemplate::variables($data['customer_name'] ?? 'Sara Khan', $account?->page_name ?? 'Your Page'));

        return response()->json([
            'matched' => true,
            'reason' => $rule->trigger,
            'message' => $rule->trigger === AutomationRule::TRIGGER_WELCOME
                ? 'No keyword matched, so the welcome message is sent (first contact).'
                : 'Keyword rule matched: this reply is sent instead of the AI.',
            'rule' => AutomationController::present($rule),
            'reply' => $reply,
        ]);
    }
}
