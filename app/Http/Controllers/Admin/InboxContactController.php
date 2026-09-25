<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Contacts\ContactService;
use App\Support\Inbox\InboxPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live Chat contact panel: inline edits of the contact (a conversation row) and profile refresh.
 */
class InboxContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contacts,
        private readonly InboxPresenter $presenter,
    ) {}

    /** Partial update: only the keys sent are changed (validated by ContactService::rules()). 422 JSON on invalid input. */
    public function update(Request $request, Conversation $conversation): JsonResponse
    {
        $fields = $request->only(array_keys(array_filter(ContactService::rules(), fn ($rule, $key) => ! str_contains($key, '.'), ARRAY_FILTER_USE_BOTH)));

        $this->contacts->update($conversation, $fields);

        return response()->json([
            'data' => $this->presenter->detail($conversation->refresh()),
            'tags' => $this->contacts->allTags(),
        ]);
    }

    public function refreshProfile(Conversation $conversation): JsonResponse
    {
        $this->contacts->refreshProfile($conversation);

        return response()->json(['message' => 'Profile refresh requested. It updates in a few seconds.']);
    }
}
