<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EagerLoadUserRelations
{
    /**
     * Loads the authenticated user's roles & permissions once per request,
     * so every policy and gate check resolves from memory.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $user->loadMissing('roles.permissions', 'permissions');
        }

        return $next($request);
    }
}