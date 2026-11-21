<?php

declare(strict_types=1);

namespace Login512\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Login512\Laravel\Login512Client;

/**
 * Route middleware alias: 'login512.verified'.
 *
 * Requires a FRESH Login512 token on the request (header "X-Login512-Token" or input "login512_token").
 * Tokens are single-use, so this suits sensitive actions (step-up) rather than every page view.
 * On success the verified claims are available as $request->attributes->get('login512_claims').
 */
class VerifyLogin512Token
{
    public function __construct(private readonly Login512Client $client)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $token = (string) ($request->header('X-Login512-Token') ?: $request->input('login512_token', ''));
        if ($token === '') {
            return response()->json(['error' => 'LOGIN512_TOKEN_REQUIRED'], 401);
        }

        $nonce = $request->hasSession() ? $request->session()->pull('login512_nonce') : null;
        $res = $this->client->verifyToken($token, is_string($nonce) ? $nonce : null);
        if (empty($res['session_allowed'])) {
            $error = !empty($res['step_up_required']) ? 'STEP_UP_REQUIRED' : ($res['error'] ?? 'TOKEN_INVALID');
            return response()->json(['error' => $error], 401);
        }

        $request->attributes->set('login512_claims', $res['claims']);
        return $next($request);
    }
}
