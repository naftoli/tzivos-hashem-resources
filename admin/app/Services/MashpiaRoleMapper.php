<?php

namespace App\Services;

use App\Models\MashpiaRoleMap;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MashpiaRoleMapper
{
    /** Lowest admin level (used when Mashpia sends a role that is not in the map). */
    public const UNKNOWN_LEVEL = 'news';

    /** Next level down from HQ admin — the highest level login can assign by itself. */
    public const HIGHEST_AUTO_LEVEL = 'hq_staff';

    public static function normalize(?string $role): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $role)));
    }

    /**
     * Look up a Mashpia role. Matching ignores case and extra spaces.
     * Returns ['admin_role' => string, 'can_request_admin' => bool, 'known' => bool, 'mashpia_role' => ?string].
     * admin_role is never hq_admin.
     */
    public function resolve(?string $mashpiaRole): array
    {
        $key = self::normalize($mashpiaRole);
        if ($key === '') {
            return ['admin_role' => null, 'can_request_admin' => false, 'known' => false, 'mashpia_role' => null];
        }

        foreach ($this->maps() as $row) {
            if (self::normalize($row['mashpia_role']) === $key) {
                return [
                    'admin_role' => $this->safeLevel($row['admin_role']),
                    'can_request_admin' => (bool) $row['can_request_admin'],
                    'known' => true,
                    'mashpia_role' => $row['mashpia_role'],
                ];
            }
        }

        return [
            'admin_role' => $this->safeLevel((string) config('mashpia.unknown_role_level', self::UNKNOWN_LEVEL)),
            'can_request_admin' => false,
            'known' => false,
            'mashpia_role' => null,
        ];
    }

    /**
     * On Mashpia sign-in: remember the Mashpia role, and update the admin level
     * unless it was set by hand, the person is already HQ admin, or they are the owner.
     */
    public function applyOnLogin(User $user, ?string $mashpiaRole): void
    {
        $key = self::normalize($mashpiaRole);
        if ($key === '') {
            return;
        }

        $resolved = $this->resolve($mashpiaRole);
        $user->mashpia_role = $resolved['mashpia_role'] ?: $mashpiaRole;

        if (! $resolved['known']) {
            Log::warning('Unknown Mashpia role on login; using the lowest admin level.', [
                'email' => $user->email,
                'mashpia_role' => $mashpiaRole,
                'assigned' => $resolved['admin_role'],
            ]);
        }

        if ($user->isOwner() || $user->isHQAdmin() || $user->role_locked) {
            $user->save();
            return;
        }

        if ($resolved['admin_role']) {
            $user->role = $resolved['admin_role'];
        }
        $user->save();
    }

    /** Clear the "set by hand" flag and apply the Mashpia role again. */
    public function resetToAutomatic(User $user): string
    {
        $user->role_locked = false;
        $resolved = $this->resolve($user->mashpia_role);
        if ($resolved['admin_role']) {
            $user->role = $resolved['admin_role'];
        }
        $user->save();

        return $user->role;
    }

    public function canRequestAdmin(User $user): bool
    {
        if ($user->isHQAdmin() || $user->isOwner()) {
            return false;
        }
        $resolved = $this->resolve($user->mashpia_role);

        return $resolved['can_request_admin'];
    }

    public function pendingRequest(User $user): ?\App\Models\AdminAccessRequest
    {
        return $user->accessRequests()->where('status', 'pending')->latest('id')->first();
    }

    /** Admin levels the map is allowed to use (never HQ admin). */
    public static function assignableLevels(): array
    {
        return array_filter(User::ROLES, fn ($key) => $key !== 'hq_admin', ARRAY_FILTER_USE_KEY);
    }

    public function safeLevel(?string $level): string
    {
        if ($level === 'hq_admin') {
            return self::HIGHEST_AUTO_LEVEL;
        }
        if (array_key_exists((string) $level, User::ROLES)) {
            return $level;
        }
        $fallback = (string) config('mashpia.unknown_role_level', self::UNKNOWN_LEVEL);
        if ($fallback !== 'hq_admin' && array_key_exists($fallback, User::ROLES)) {
            return $fallback;
        }

        return self::UNKNOWN_LEVEL;
    }

    /** @return list<array{mashpia_role: string, admin_role: string, can_request_admin: bool}> */
    protected function maps(): array
    {
        if (Schema::hasTable('mashpia_role_maps')) {
            $rows = MashpiaRoleMap::query()->orderBy('id')->get(['mashpia_role', 'admin_role', 'can_request_admin']);
            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($r) => [
                    'mashpia_role' => $r->mashpia_role,
                    'admin_role' => $r->admin_role,
                    'can_request_admin' => $r->can_request_admin,
                ])->all();
            }
        }

        $request = array_map([self::class, 'normalize'], config('mashpia.request_admin_roles', []));
        $maps = [];
        foreach (config('mashpia.role_map', []) as $mashpia => $admin) {
            $maps[] = [
                'mashpia_role' => (string) $mashpia,
                'admin_role' => (string) $admin,
                'can_request_admin' => in_array(self::normalize((string) $mashpia), $request, true),
            ];
        }

        return $maps;
    }
}
