<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AutomationRuleRequest;
use App\Models\AutomationRule;
use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use App\Support\CurrentPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Automations: welcome message, keyword rules, "test a message", welcome screen & ice breakers.
 * Follows the page switcher: "All pages" = global rules; a page = its own rules (+ the global ones that also apply).
 * Mutations answer JSON for the Alpine UI and redirect back for plain form posts.
 */
class AutomationController extends Controller
{
    public function index(CurrentPage $currentPage): View
    {
        $account = $currentPage->account();

        $keywordRules = AutomationRule::query()
            ->with('metaAccount:id,page_name,platform')
            ->where('trigger', AutomationRule::TRIGGER_KEYWORD)
            ->when($account, fn ($q) => $q->applicableTo($account), fn ($q) => $q->whereNull('meta_account_id'))
            ->inMatchOrder()
            ->get();

        $welcomes = AutomationRule::query()
            ->where('trigger', AutomationRule::TRIGGER_WELCOME)
            ->applicableTo($account)
            ->inMatchOrder()
            ->get();

        $otherPages = $account ? collect() : AutomationRule::query()
            ->whereNotNull('meta_account_id')
            ->select('meta_account_id', DB::raw('count(*) as rules'))
            ->groupBy('meta_account_id')
            ->pluck('rules', 'meta_account_id');

        return view('admin.automations.index', [
            'account' => $account,
            'rules' => $keywordRules->map(fn (AutomationRule $r) => self::present($r))->values(),
            'welcome' => ($w = $welcomes->firstWhere('meta_account_id', $account?->id)) ? self::present($w) : null,
            'inheritedWelcome' => $account && ($g = $welcomes->firstWhere('meta_account_id', null)) ? self::present($g) : null,
            'otherPages' => $currentPage->connectedPages()->filter(fn (MetaAccount $p) => isset($otherPages[$p->id]))
                ->map(fn (MetaAccount $p) => ['id' => $p->id, 'name' => $p->page_name ?: 'Page #'.$p->id, 'rules' => (int) $otherPages[$p->id]])->values(),
            'pages' => $currentPage->connectedPages(),
            'profile' => $account?->messengerProfile() ?? [],
            'limits' => [
                'greeting' => MetaMessagingService::GREETING_MAX_CHARS,
                'ice_breakers' => MetaMessagingService::ICE_BREAKERS_MAX,
                'question' => MetaMessagingService::ICE_BREAKER_QUESTION_MAX_CHARS,
            ],
            'automationsEnabled' => filter_var(config('bot.automations.enabled', true), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function store(AutomationRuleRequest $request): JsonResponse|RedirectResponse
    {
        $rule = AutomationRule::create($request->ruleAttributes());

        return $this->respond($request, 'Rule "'.$rule->name.'" created.', ['rule' => self::present($rule)], 201);
    }

    public function update(AutomationRuleRequest $request, AutomationRule $rule): JsonResponse|RedirectResponse
    {
        $rule->fill($request->ruleAttributes())->save();

        return $this->respond($request, 'Rule "'.$rule->name.'" saved.', ['rule' => self::present($rule->fresh())]);
    }

    public function destroy(Request $request, AutomationRule $rule): JsonResponse|RedirectResponse
    {
        $rule->delete();

        return $this->respond($request, 'Rule "'.$rule->name.'" deleted.', ['id' => $rule->id]);
    }

    /** Copy a rule (inactive, so the copy never double-fires until you switch it on). */
    public function duplicate(Request $request, AutomationRule $rule): JsonResponse|RedirectResponse
    {
        $copy = $rule->replicate(['trigger_count', 'last_triggered_at']);
        $copy->name = mb_substr($rule->name.' (copy)', 0, 120);
        $copy->active = false;
        $copy->trigger_count = 0;
        $copy->save();

        return $this->respond($request, 'Duplicated as "'.$copy->name.'" (off until you enable it).', ['rule' => self::present($copy->fresh())], 201);
    }

    public function toggle(Request $request, AutomationRule $rule): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $rule->update(['active' => (bool) $data['active']]);

        return $this->respond($request, $rule->active ? 'Rule on.' : 'Rule off.', ['rule' => self::present($rule)]);
    }

    /** New match order for a group of keyword rules with the same scope: first id = highest priority. */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $rules = AutomationRule::query()->whereIn('id', $data['ids'])->where('trigger', AutomationRule::TRIGGER_KEYWORD)->get()->keyBy('id');

        if ($rules->count() !== count($data['ids']) || $rules->pluck('meta_account_id')->unique()->count() > 1) {
            return response()->json(['message' => 'Only keyword rules with the same scope can be reordered together.'], 422);
        }

        $count = count($data['ids']);

        DB::transaction(function () use ($data, $rules, $count) {
            foreach (array_values($data['ids']) as $index => $id) {
                $rules[$id]->update(['priority' => max(-1000, min(1000, $count - $index))]);
            }
        });

        return response()->json([
            'message' => 'Order saved.',
            'rules' => collect($data['ids'])->map(fn ($id) => self::present($rules[$id]->fresh()))->values(),
        ]);
    }

    /** Create / update the welcome message of a scope (page or All pages). */
    public function saveWelcome(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'meta_account_id' => ['nullable', 'integer', 'exists:meta_accounts,id'],
            'active' => ['required', 'boolean'],
            'reply_text' => ['required', 'string', 'max:2000'],
        ], ['reply_text.required' => 'Write the welcome message first.']);

        $scope = $data['meta_account_id'] ?? null;
        $rule = AutomationRule::query()
            ->where('trigger', AutomationRule::TRIGGER_WELCOME)
            ->where(fn ($q) => $scope === null ? $q->whereNull('meta_account_id') : $q->where('meta_account_id', $scope))
            ->orderByDesc('priority')->orderBy('id')
            ->first() ?? new AutomationRule([
                'meta_account_id' => $scope,
                'trigger' => AutomationRule::TRIGGER_WELCOME,
                'name' => 'Welcome message',
                'keywords' => null,
            ]);

        $rule->fill(['reply_text' => trim($data['reply_text']), 'active' => (bool) $data['active']])->save();

        return $this->respond($request, $rule->active ? 'Welcome message saved and live.' : 'Welcome message saved (off).', ['rule' => self::present($rule->fresh())]);
    }

    /** @return array<string, mixed> */
    public static function present(AutomationRule $rule): array
    {
        $page = $rule->meta_account_id ? ($rule->relationLoaded('metaAccount') ? $rule->metaAccount : MetaAccount::find($rule->meta_account_id)) : null;

        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'trigger' => $rule->trigger,
            'match_type' => $rule->match_type,
            'keywords' => array_values((array) $rule->keywords),
            'reply_text' => $rule->reply_text,
            'priority' => (int) $rule->priority,
            'active' => (bool) $rule->active,
            'trigger_count' => (int) $rule->trigger_count,
            'last_triggered_at' => $rule->last_triggered_at?->toIso8601String(),
            'meta_account_id' => $rule->meta_account_id,
            'scope_label' => $page ? ($page->page_name ?: 'Page #'.$page->id) : 'All pages',
            'scope_platform' => $page?->platform?->value,
        ];
    }

    private function respond(Request $request, string $message, array $payload, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $payload, $status);
        }

        return redirect()->route('admin.automations.index')->with('success', $message);
    }
}
