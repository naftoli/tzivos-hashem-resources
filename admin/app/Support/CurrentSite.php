<?php

namespace App\Support;

use App\Models\Site;

/**
 * The site the signed-in person is working on, kept in their session.
 * Falls back to the Resources site (or the first site that isn't archived).
 */
class CurrentSite
{
    private static ?Site $site = null;

    public static function get(): Site
    {
        if (self::$site) return self::$site;
        $id = session('site_id');
        $site = $id ? Site::active()->find($id) : null;
        $site ??= Site::active()->where('slug', 'resources')->first() ?? Site::active()->orderBy('id')->first() ?? Site::orderBy('id')->firstOrFail();
        if ((int) session('site_id') !== $site->id && session()->isStarted()) session(['site_id' => $site->id]);
        return self::$site = $site;
    }

    public static function id(): int
    {
        return self::get()->id;
    }

    public static function set(Site $site): void
    {
        session(['site_id' => $site->id]);
        self::$site = $site;
    }

    public static function forget(): void
    {
        self::$site = null;
    }
}
