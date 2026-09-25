<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MessengerProfileRequest;
use App\Models\MetaAccount;
use App\Services\Meta\MetaMessagingService;
use Illuminate\Http\JsonResponse;

/** Welcome screen (greeting, Get Started) and ice breakers, read from / written to Meta for one page. */
class MessengerProfileController extends Controller
{
    public function show(MetaAccount $metaAccount, MetaMessagingService $messaging): JsonResponse
    {
        $result = $messaging->getMessengerProfile($metaAccount);

        if (! $result['ok']) {
            return response()->json(['message' => 'Meta: '.($result['error'] ?? 'could not load the profile.')], 422);
        }

        return response()->json(['message' => 'Loaded the live settings from Meta.', 'profile' => $result['profile']]);
    }

    public function update(MessengerProfileRequest $request, MetaAccount $metaAccount, MetaMessagingService $messaging): JsonResponse
    {
        $result = $messaging->setMessengerProfile($metaAccount, $request->profileConfig($metaAccount));

        if (! $result['ok']) {
            return response()->json(['message' => 'Meta: '.($result['error'] ?? 'could not save the profile.')], 422);
        }

        return response()->json([
            'message' => 'Saved to Meta. Changes can take a few minutes to show in the app.',
            'profile' => $result['profile'] ?? [],
            'warnings' => $result['warnings'] ?? [],
        ]);
    }
}
