<?php

namespace App\Services;

use App\Models\User;
use App\Support\SiteViewer;
use Illuminate\Http\Request;

/**
 * Who is asking. A browser never sends this itself.
 * The Mashpia proxy proves it with RESOURCES_API_TOKEN, then says the email and Mashpia role.
 * A person signed into the admin uses their own account.
 */
class SiteViewerResolver
{
    public function __construct(private MashpiaRoleMapper $roles) {}

    public function from(Request $request): ?SiteViewer
    {
        if ($request->attributes->get('resources_proxy')) {
            return $this->fromProxy($request);
        }

        $user = $request->user();
        if (! $user) {
            return null;
        }

        return new SiteViewer(strtolower($user->email), $user->role, $user->id, $user->mashpia_role);
    }

    private function fromProxy(Request $request): ?SiteViewer
    {
        $email = strtolower(trim((string) $request->header('X-Resources-Email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $mashpiaRole = trim((string) $request->header('X-Resources-Role'));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();
        if ($user) {
            return new SiteViewer($email, $user->role, $user->id, $mashpiaRole !== '' ? $mashpiaRole : $user->mashpia_role);
        }

        $mapped = $this->roles->resolve($mashpiaRole !== '' ? $mashpiaRole : null);

        return new SiteViewer($email, $mapped['admin_role'] ?: MashpiaRoleMapper::UNKNOWN_LEVEL, null, $mashpiaRole !== '' ? $mashpiaRole : null);
    }
}
