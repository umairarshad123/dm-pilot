<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\AiProviderException;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\MetaAccount;
use App\Services\Bot\BotPlayground;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** "Test your bot" chat in Bot Studio. Uses the SAVED settings of the page (or global defaults). */
class BotStudioPlaygroundController extends Controller
{
    public function __invoke(Request $request, BotPlayground $playground): JsonResponse
    {
        $data = $request->validate([
            'meta_account_id' => ['nullable', 'integer', 'exists:meta_accounts,id'],
            'platform' => ['required', Rule::in(['facebook', 'instagram'])],
            'messages' => ['required', 'array', 'min:1', 'max:60'],
            'messages.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'max:4000'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'payload' => ['nullable', 'string', 'max:1000'],
        ]);

        $account = isset($data['meta_account_id']) ? MetaAccount::find($data['meta_account_id']) : null;

        try {
            $result = $playground->reply(
                $account,
                $data['platform'],
                $data['messages'],
                $data['customer_name'] ?? null,
                $data['payload'] ?? null,
            );
        } catch (AiProviderException|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (isset($result['rule_id'])) {
            $result['rule_name'] = AutomationRule::whereKey($result['rule_id'])->value('name');
        }

        return response()->json($result);
    }
}
