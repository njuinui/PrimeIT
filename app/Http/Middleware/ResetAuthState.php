<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResetAuthState
{
    /**
     * Clear any stale resolved auth state from a long-lived worker or test process
     * before the request resolves the current user from the incoming token/session.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        Auth::forgetGuards();
        $request->setUserResolver(fn () => null);

        return $response;
    }
}
