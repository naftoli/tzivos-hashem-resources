<?php

namespace App\Models\Concerns;

use Illuminate\Support\Carbon;

/** Who can see / download, and when. HQ admin always sees (unless the file was removed). */
trait FileAccessRule
{
    public function visibleToRole(string $role, ?Carbon $now = null): bool
    {
        if ($this->getAttribute('removed')) {
            return false;
        }
        if ($role === 'hq_admin') {
            return true;
        }
        $now ??= now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return $this->roles === null || in_array($role, $this->roles, true);
    }

    public function canDownload(string $role, ?Carbon $now = null): bool
    {
        if (! $this->visibleToRole($role, $now)) {
            return false;
        }

        return $role === 'hq_admin' || $this->download_roles === null || in_array($role, $this->download_roles, true);
    }

    /** Nothing set: same as having no setting */
    public function isPlain(): bool
    {
        return ! $this->getAttribute('removed') && $this->roles === null && $this->download_roles === null
            && $this->starts_at === null && $this->ends_at === null;
    }
}
