<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates X-Hub-Signature-256 (HMAC-SHA256 of the raw body with the Meta app secret).
 */
class VerifyMetaSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('meta.verify_signature')) {
            Log::channel('meta')->debug('Webhook signature verification is DISABLED (META_VERIFY_SIGNATURE=false).', [
                'ip' => $request->ip(),
            ]);

            return $next($request);
        }

        $secret = (string) config('meta.app_secret');

        if ($secret === '') {
            Log::channel('meta')->error('META_APP_SECRET not configured; rejecting webhook.', ['ip' => $request->ip()]);

            return response('Forbidden', 403);
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if ($header === '' || ! hash_equals($expected, strtolower($header))) {
            Log::channel('meta')->warning('Webhook signature invalid or missing.', [
                'ip' => $request->ip(),
                'has_header' => $header !== '',
                'content_length' => strlen($request->getContent()),
            ]);

            return response('Forbidden', 403);
        }

        return $next($request);
    }
}
