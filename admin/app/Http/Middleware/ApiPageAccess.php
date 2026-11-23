<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page data (/api/...) is not public. It is given to:
 *  - a signed-in person whose level may see pages in the admin (HQ admin, HQ staff), or
 *  - the live site's server, sending "Authorization: Bearer <RESOURCES_API_TOKEN>".
 * Anyone else: 401 (not signed in) or 403 (signed in, but their level can't see pages).
 */
class ApiPageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('admin.api_token');
        $given = (string) $request->bearerToken();
        if ($token !== '' && $given !== '' && hash_equals($token, $given)) {
            // The secret proves this call came from the Mashpia proxy, not a browser.
            $request->attributes->set('resources_proxy', true);

            return $next($request);
        }
        $user = $request->user();
        if ($user && $user->canEditPages()) {
            return $next($request);
        }
        return $user
            ? response()->json(['message' => 'Your level can\'t read page data.'], 403)
            : response()->json(['message' => 'Sign in (or send the site\'s token) to read page data.'], 401, ['WWW-Authenticate' => 'Bearer']);
    }
}
