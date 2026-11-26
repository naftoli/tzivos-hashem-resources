<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Files › Visibility settings for one site: Full, each Hachayol week, each feature (optionally only some weeks).
 * A weekly feature shows on the site only if Full is on, its week is on, the feature is on,
 * and the feature uses every week or includes that week. Off hides it on the site for everyone; nothing is deleted.
 */
class FileVisibility extends Model
{
    protected $table = 'file_visibility';

    protected $fillable = ['site_id', 'full', 'weeks_off', 'features', 'updated_by'];

    protected $casts = ['full' => 'boolean', 'weeks_off' => 'array', 'features' => 'array'];

    public static function forSite(int $siteId): self
    {
        return static::firstOrNew(['site_id' => $siteId], ['full' => true, 'weeks_off' => [], 'features' => []]);
    }

    public function weekIsOn(string $num): bool
    {
        return ! in_array($num, $this->weeks_off ?? [], true);
    }

    /** A whole Hachayol week (its bar and all its features) is hidden */
    public function issueIsOff(string $num): bool
    {
        return ! $this->full || ! $this->weekIsOn($num);
    }

    /** One feature in one week is hidden */
    public function partIsOff(string $num, string $feature): bool
    {
        if ($this->issueIsOff($num)) {
            return true;
        }
        $f = ($this->features ?? [])[$feature] ?? null;
        if (! $f) {
            return false;
        }
        if (($f['on'] ?? true) === false) {
            return true;
        }
        $weeks = $f['weeks'] ?? null;

        return is_array($weeks) && ! in_array($num, $weeks, true);
    }
}
