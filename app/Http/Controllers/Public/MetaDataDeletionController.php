<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\DataDeletionRequest;
use App\Models\Message;
use App\Models\MetaAccount;
use App\Models\WebhookEvent;
use App\Services\Meta\SignedRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

/**
 * Meta "Data Deletion Request" and "Deauthorize" callbacks (App settings → Basic / Facebook Login settings).
 *
 * IMPORTANT limitation: the `user_id` in a signed_request is the *app-scoped user ID* (ASID) of the Facebook user
 * who used Facebook Login with this app. It is NOT the Page-scoped ID (PSID) or Instagram-scoped ID (IGSID) that
 * identifies customers in Messenger/Instagram conversations, and Meta offers no API to map one to the other.
 * We still delete any rows that match the ID exactly (conversations, messages, raw webhook events), record the
 * request for audit, and direct end users (DM customers) to the manual process described on /data-deletion.
 */
class MetaDataDeletionController extends Controller
{
    public function callback(Request $request, SignedRequest $signedRequest): JsonResponse
    {
        $payload = $this->verify($request, $signedRequest);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $userId = trim((string) ($payload['user_id'] ?? ''));

        if ($userId === '') {
            return response()->json(['error' => 'signed_request has no user_id'], 400);
        }

        $deletion = DataDeletionRequest::create([
            'confirmation_code' => DataDeletionRequest::newConfirmationCode(),
            'type' => DataDeletionRequest::TYPE_DELETION,
            'meta_user_id' => $userId,
            'status' => DataDeletionRequest::STATUS_PENDING,
            'issued_at' => $this->issuedAt($payload),
        ]);

        try {
            $counts = DB::transaction(fn () => $this->deleteUserData($userId));

            $found = $counts['conversations_deleted'] + $counts['messages_deleted'] + $counts['webhook_events_deleted'];

            $deletion->fill($counts + [
                'status' => $found > 0 ? DataDeletionRequest::STATUS_COMPLETED : DataDeletionRequest::STATUS_NO_DATA,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $deletion->fill([
                'status' => DataDeletionRequest::STATUS_FAILED,
                'details' => ['error' => class_basename($e).': '.$e->getMessage()],
            ])->save();

            Log::channel('meta')->error('Data deletion callback failed', ['code' => $deletion->confirmation_code, 'exception' => $e]);
        }

        Log::channel('meta')->info('Data deletion request processed', [
            'code' => $deletion->confirmation_code,
            'status' => $deletion->status,
        ]);

        return response()->json([
            'url' => route('meta.data-deletion.status', $deletion->confirmation_code),
            'confirmation_code' => $deletion->confirmation_code,
        ]);
    }

    public function status(string $code): View
    {
        $deletion = DataDeletionRequest::query()
            ->where('confirmation_code', strtoupper($code))
            ->where('type', DataDeletionRequest::TYPE_DELETION)
            ->firstOrFail();

        return view('public.data-deletion-status', ['deletion' => $deletion]);
    }

    public function deauthorize(Request $request, SignedRequest $signedRequest): Response|JsonResponse
    {
        $payload = $this->verify($request, $signedRequest);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $userId = trim((string) ($payload['user_id'] ?? ''));

        if ($userId === '') {
            return response()->json(['error' => 'signed_request has no user_id'], 400);
        }

        // Page tokens derived from this user's login stop working once they remove the app. We can only match
        // accounts whose settings recorded the connecting user's app-scoped id; others fail on the next API call
        // and show up in "Test connection".
        $accounts = $this->accountsConnectedBy($userId);

        foreach ($accounts as $account) {
            $account->forceFill([
                'active' => false,
                'settings' => array_merge($account->settings ?? [], [
                    'deauthorized_at' => now()->toIso8601String(),
                    'deauthorized_by' => $userId,
                ]),
            ])->save();
        }

        DataDeletionRequest::create([
            'confirmation_code' => DataDeletionRequest::newConfirmationCode(),
            'type' => DataDeletionRequest::TYPE_DEAUTHORIZE,
            'meta_user_id' => $userId,
            'status' => DataDeletionRequest::STATUS_COMPLETED,
            'accounts_affected' => $accounts->count(),
            'details' => ['account_ids' => $accounts->pluck('id')->all()],
            'issued_at' => $this->issuedAt($payload),
            'completed_at' => now(),
        ]);

        Log::channel('meta')->warning('App deauthorized by a Facebook user', [
            'accounts_deactivated' => $accounts->pluck('id')->all(),
        ]);

        return response()->noContent(200);
    }

    /** @return array<string, mixed>|JsonResponse */
    private function verify(Request $request, SignedRequest $signedRequest): array|JsonResponse
    {
        try {
            $maxAge = config('legal.signed_request_max_age');

            return $signedRequest->parse($request->input('signed_request'), $maxAge === null ? null : (int) $maxAge);
        } catch (UnexpectedValueException $e) {
            Log::channel('meta')->warning('Rejected Meta signed_request', ['reason' => $e->getMessage(), 'path' => $request->path()]);

            return response()->json(['error' => 'Invalid signed_request'], 400);
        }
    }

    /** @return array{conversations_deleted: int, messages_deleted: int, webhook_events_deleted: int} */
    private function deleteUserData(string $userId): array
    {
        $conversationIds = Conversation::query()->where('external_user_id', $userId)->pluck('id');

        $messages = $conversationIds->isEmpty() ? 0 : Message::query()->whereIn('conversation_id', $conversationIds)->delete();
        $conversations = $conversationIds->isEmpty() ? 0 : Conversation::query()->whereKey($conversationIds)->delete();

        // Raw webhook payloads store Meta ids as JSON strings. Only match purely numeric ids to keep LIKE safe.
        $events = 0;
        if (ctype_digit($userId)) {
            $events = WebhookEvent::query()->where('payload', 'like', '%"'.$userId.'"%')->delete();
        }

        return [
            'conversations_deleted' => $conversations,
            'messages_deleted' => $messages,
            'webhook_events_deleted' => $events,
        ];
    }

    /** @return Collection<int, MetaAccount> */
    private function accountsConnectedBy(string $userId): Collection
    {
        if (! ctype_digit($userId)) {
            return collect();
        }

        return MetaAccount::query()
            ->where('settings', 'like', '%"'.$userId.'"%')
            ->get()
            ->filter(fn (MetaAccount $account) => in_array($userId, array_map('strval', array_filter(
                $account->settings ?? [], fn ($v) => is_scalar($v),
            )), true))
            ->values();
    }

    private function issuedAt(array $payload): ?Carbon
    {
        return isset($payload['issued_at']) && is_numeric($payload['issued_at'])
            ? Carbon::createFromTimestamp((int) $payload['issued_at'], config('app.timezone'))
            : null;
    }
}
