<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContactBulkRequest;
use App\Models\Conversation;
use App\Services\Contacts\ContactDeletionService;
use App\Services\Contacts\ContactService;
use App\Support\CurrentPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Bulk actions on the Contacts list: add / remove a tag, set the lead stage, delete.
 * Targets the selected ids or every contact matching the filters, always inside the current page scope.
 */
class ContactBulkController extends Controller
{
    public function __invoke(ContactBulkRequest $request, ContactService $contacts, CurrentPage $currentPage, ContactDeletionService $deletion): JsonResponse|RedirectResponse
    {
        $query = $this->targets($request, $contacts, $currentPage);
        $action = (string) $request->validated('action');

        $affected = match ($action) {
            'add_tag' => $this->eachContact($query, fn (Conversation $c) => $c->hasTag($request->validated('tag')) ? false : (bool) $c->addTag($request->validated('tag'))),
            'remove_tag' => $this->eachContact($query, fn (Conversation $c) => $c->hasTag($request->validated('tag')) && (bool) $c->removeTag($request->validated('tag'))),
            'set_stage' => $this->eachContact($query, function (Conversation $c) use ($contacts, $request) {
                if ($c->leadStage()->value === $request->validated('lead_stage')) {
                    return false;
                }
                $contacts->update($c, ['lead_stage' => $request->validated('lead_stage')]);

                return true;
            }),
            'delete' => $deletion->deleteMatching($query, 'bulk', $request->user()?->id)['conversations'],
        };

        $tag = Conversation::normalizeTag((string) $request->validated('tag'));
        $noun = fn (int $n) => $n.' contact'.($n === 1 ? '' : 's');
        $message = match ($action) {
            'add_tag' => "Tagged {$noun($affected)} with \"{$tag}\".",
            'remove_tag' => "Removed \"{$tag}\" from {$noun($affected)}.",
            'set_stage' => "Moved {$noun($affected)} to ".LeadStage::from($request->validated('lead_stage'))->label().'.',
            'delete' => "Deleted {$noun($affected)} and their messages.",
        };

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'affected' => $affected])
            : back()->with('success', $message);
    }

    private function targets(ContactBulkRequest $request, ContactService $contacts, CurrentPage $currentPage): Builder
    {
        if ($request->selectsAll()) {
            return $request->contactQuery($contacts, $currentPage);
        }

        $ids = array_values(array_unique(array_map('intval', (array) $request->validated('ids'))));

        return $currentPage->scope(Conversation::query())->whereKey($ids);
    }

    /** @param  callable(Conversation): bool  $apply  returns whether the contact changed */
    private function eachContact(Builder $query, callable $apply): int
    {
        $changed = 0;

        (clone $query)->reorder()->select('conversations.*')->lazyById(200, 'conversations.id', 'id')
            ->each(function (Conversation $c) use ($apply, &$changed) {
                $changed += $apply($c) ? 1 : 0;
            });

        return $changed;
    }
}
