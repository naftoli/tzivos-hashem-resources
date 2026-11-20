<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Site;

class PageController extends Controller
{
    /** Read-only: /api/pages/{slug} = a Resources site page (the original address, unchanged) */
    public function show(string $page)
    {
        return $this->send(Site::where('slug', 'resources')->firstOrFail(), $page);
    }

    /** Read-only: /api/sites/{site}/pages/{slug} = any site's page */
    public function showForSite(Site $site, string $page)
    {
        return $this->send($site, $page);
    }

    /** The page in the same shape and format as src/data/pages/<slug>.json */
    private function send(Site $site, string $slug)
    {
        $page = Page::where('site_id', $site->id)->where('slug', $slug)->firstOrFail();
        return response($page->toJsonText(), 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
