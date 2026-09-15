<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNiatReviewer
{
    private const ALLOWED_ROLES = [
        'reviewer',
        'supervisor',
        'administrator',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user === null
            || ! $user->is_active
            || ! in_array($user->role, self::ALLOWED_ROLES, true)
        ) {
            abort(403, 'This account does not have active NIAT reviewer access.');
        }

        return $next($request);
    }
}
