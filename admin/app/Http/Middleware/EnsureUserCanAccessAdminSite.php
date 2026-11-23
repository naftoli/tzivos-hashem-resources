<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The News site level may not use the admin site. */
class EnsureUserCanAccessAdminSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->canAccessAdminSite()) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                return response()->view('auth.no-admin-access', [], 403);
            }

            return redirect()->route('access.denied');
        }

        return $next($request);
    }
}
