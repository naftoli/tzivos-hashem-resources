<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /** Usage: ->middleware('role:hq_admin,hq_staff') with the stored role values from User::ROLES */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user() || ! in_array($request->user()->role, $roles, true)) {
            return response()->view('errors.access-denied', ['roles' => $roles], 403);
        }

        return $next($request);
    }
}
