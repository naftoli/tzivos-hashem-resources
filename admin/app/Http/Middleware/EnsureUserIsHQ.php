<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 'hq' = HQ admin only (User roles, Sign-in settings) */
class EnsureUserIsHQ
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isHQAdmin()) {
            return response()->view('errors.access-denied', ['roles' => ['hq_admin']], 403);
        }

        return $next($request);
    }
}
