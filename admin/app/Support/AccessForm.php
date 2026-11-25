<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** The "who can see / who can download / when" fields shared by Site pages › Files and the Files dashboard. */
class AccessForm
{
    /** Levels that can be picked (HQ admin always sees everything) */
    public static function levels(): array
    {
        return array_diff_key(User::ROLES, ['hq_admin' => true]);
    }

    /** @return array{roles: ?array, download_roles?: ?array, starts_at: ?Carbon, ends_at: ?Carbon} */
    public static function read(Request $request, bool $withDownload): array
    {
        $levels = array_keys(self::levels());
        $pick = function (string $field) use ($request, $levels) {
            $picked = array_values(array_intersect($levels, (array) $request->input($field, [])));

            return count($picked) === count($levels) ? null : $picked;   // all ticked = everyone
        };
        $out = [
            'roles' => $pick('roles'),
            'starts_at' => self::time($request->input('starts_at')),
            'ends_at' => self::time($request->input('ends_at')),
        ];
        if ($withDownload) {
            $out['download_roles'] = $pick('download_roles');
        }
        if ($out['starts_at'] && $out['ends_at'] && $out['ends_at']->lte($out['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => '"Until" must be after "From".']);
        }

        return $out;
    }

    /** For the form: a saved time in the admin's time zone */
    public static function show(?Carbon $t): string
    {
        return $t ? $t->copy()->setTimezone(config('admin.timezone'))->format('Y-m-d\TH:i') : '';
    }

    private static function time(mixed $v): ?Carbon
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d\TH:i', $v, config('admin.timezone'))->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['starts_at' => 'Enter the date and time again.']);
        }
    }
}
