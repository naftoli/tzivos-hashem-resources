<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 'owner' = the Owner account(s) only (Admin requests) */
class EnsureUserIsOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isOwner()) {
            return response()->view('errors.access-denied', ['message' => 'Only the Owner can open this page.'], 403);
        }

        return $next($request);
    }
}
