<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** The News site level may not use the admin site. */
class EnsureUserCanAccessAdminSite
{
    public const MESSAGE = "This account doesn't have access to the admin.";

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->canAccessAdminSite()) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => self::MESSAGE], 403);
            }

            return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
