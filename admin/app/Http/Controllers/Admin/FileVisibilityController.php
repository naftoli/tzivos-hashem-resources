<?php

namespace App\Http\Controllers\Admin;

use App\Models\FileVisibility;
use App\Support\CurrentSite;
use Illuminate\Http\Request;

/**
 * Files: the on/off switches for Full and each feature, and Choose weeks for each
 * (each week on/off, plus on/off for all).
 * Full is the master switch: turning it off or on turns every feature off or on with it.
 * A feature can still be turned on or off on its own after that.
 */
class FileVisibilityController extends FileDashboardController
{
    /** What the Files page needs for the switches: Full, the features, and the weeks (oldest first) */
    public static function switchesFor(int $siteId, array $issues, array $features): array
    {
        $weeks = [];
        foreach (array_reverse($issues, true) as $num => $i) {
            $weeks[] = ['id' => (string) $num, 'name' => $i['label'], 'features' => array_values(array_unique(array_column($i['parts'], 'feature')))];
        }
        $feats = [];
        foreach ($features as $key => $f) {
            $feats[] = ['id' => (string) $key, 'name' => $f['name']];
        }

        return [
            'weeks' => $weeks,
            'features' => $feats,
            'state' => self::state(FileVisibility::forSite($siteId), array_column($weeks, 'id'), array_keys($features)),
        ];
    }

    public function update(Request $request)
    {
        $site = CurrentSite::get();
        [$issues, $features] = $this->structure($site->id);
        $weekIds = array_map('strval', array_keys($issues));
        $in = (array) $request->json()->all();

        $feats = [];
        foreach ((array) ($in['features'] ?? []) as $key => $f) {
            if (! isset($features[$key]) || ! is_array($f)) {
                continue;
            }
            $weeks = $f['weeks'] ?? null;
            $feats[$key] = [
                'on' => (bool) ($f['on'] ?? true),
                'weeks' => is_array($weeks) ? array_values(array_intersect($weekIds, array_map('strval', $weeks))) : null,
            ];
        }

        $v = FileVisibility::forSite($site->id);
        $full = (bool) ($in['full'] ?? true);
        if ($full !== (bool) $v->full) {
            // Full is the master switch: turning it off (or on) turns every feature off (or on) too
            foreach (array_keys($features) as $key) {
                $feats[$key] = ['on' => $full, 'weeks' => $feats[$key]['weeks'] ?? ((($v->features ?? [])[$key]['weeks'] ?? null))];
            }
        }
        $v->fill([
            'full' => $full,
            'weeks_off' => array_values(array_intersect($weekIds, array_map('strval', (array) ($in['weeks_off'] ?? [])))),
            'features' => $feats,
            'updated_by' => $request->user()->id,
        ])->save();

        return response()->json(['saved' => true, 'state' => self::state($v, $weekIds, array_keys($features))]);
    }

    private static function state(FileVisibility $v, array $weekIds, array $featureIds): array
    {
        $features = [];
        foreach ($featureIds as $key) {
            $f = ($v->features ?? [])[$key] ?? [];
            $features[$key] = ['on' => ($f['on'] ?? true) !== false, 'weeks' => isset($f['weeks']) && is_array($f['weeks']) ? array_values($f['weeks']) : null];
        }

        return [
            'full' => (bool) $v->full,
            'weeks_off' => array_values(array_intersect($weekIds, $v->weeks_off ?? [])),
            'features' => $features,
        ];
    }
}
