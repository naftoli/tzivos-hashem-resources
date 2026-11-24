<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentView;
use App\Models\PageBlock;
use App\Models\Site;
use App\Models\Upload;
use App\Services\FileGrant;
use App\Services\PageFileLinker;
use App\Services\SiteViewerResolver;
use App\Support\SiteViewer;
use Illuminate\Http\Request;

/** Pages, search, and file opens for the public Resources site. */
class ContentController extends Controller
{
    public function __construct(
        private SiteViewerResolver $viewers,
        private PageFileLinker $links,
        private FileGrant $grants,
    ) {}

    public function page(Request $request, string $pageId)
    {
        $viewer = $this->viewer($request);
        if ($viewer instanceof \Illuminate\Http\JsonResponse) {
            return $viewer;
        }

        $site = $this->resources();
        $block = PageBlock::query()
            ->where('site_id', $site->id)
            ->where('block_key', $pageId)
            ->orderBy('position')
            ->first();
        if (! $block) {
            return response()->json(['message' => 'No page with that name.'], 404);
        }

        $uploads = $this->uploads($site->id);
        $html = $this->links->rewrite($block->html, $uploads, $viewer->role);
        $html = $this->links->appendForPage($html, $uploads, $viewer->role, $block);
        ContentView::record($viewer, 'page', $this->title($html, $pageId), $pageId, null, $site->id);

        return response()->json(['id' => $pageId, 'html' => $html]);
    }

    public function search(Request $request)
    {
        $viewer = $this->viewer($request);
        if ($viewer instanceof \Illuminate\Http\JsonResponse) {
            return $viewer;
        }

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $site = $this->resources();
        $needle = mb_strtolower($q);
        $results = [];

        foreach (PageBlock::query()->where('site_id', $site->id)->orderBy('position')->get(['block_key', 'html']) as $block) {
            $title = $this->title($block->html, $block->block_key);
            $text = mb_strtolower(trim(preg_replace('/\s+/u', ' ', strip_tags($block->html)) ?? ''));
            $inTitle = mb_stripos($title, $q) !== false;
            $inText = mb_stripos($text, $needle) !== false;
            if (! $inTitle && ! $inText) {
                continue;
            }
            $results[] = ['rank' => $inTitle ? 0 : 1, 't' => $title, 'g' => $block->block_key, 'k' => 'Page', 'href' => null];
            if (count($results) >= 40) {
                break;
            }
        }

        $proxy = rtrim((string) config('admin.site_api'), '?');
        foreach ($this->uploads($site->id) as $upload) {
            if (! $upload->isApproved() || mb_stripos($upload->original_name, $q) === false || ! $upload->visibleToRole($viewer->role)) {
                continue;
            }
            $results[] = [
                'rank' => 2,
                't' => $upload->original_name,
                'g' => '',
                'k' => 'File',
                'href' => $proxy.'?file='.$upload->id,
            ];
        }

        usort($results, fn ($a, $b) => $a['rank'] <=> $b['rank'] ?: mb_strlen($a['t']) <=> mb_strlen($b['t']));
        $results = array_slice($results, 0, 20);
        foreach ($results as &$row) {
            unset($row['rank']);
        }

        return response()->json(['results' => $results]);
    }

    public function file(Request $request, Upload $upload)
    {
        $viewer = $this->viewer($request);
        if ($viewer instanceof \Illuminate\Http\JsonResponse) {
            return $viewer;
        }
        if ($upload->trashed() || ! $upload->isApproved() || ! $upload->visibleToRole($viewer->role)) {
            return response()->json(['message' => "You don't have access to this file right now."], 403);
        }

        ContentView::record($viewer, 'file', $upload->original_name, $upload->page_key, $upload->id, $upload->site_id);

        return response()->json(['url' => $this->grants->url($upload, $viewer)]);
    }

    private function viewer(Request $request): SiteViewer|\Illuminate\Http\JsonResponse
    {
        $viewer = $this->viewers->from($request);
        if (! $viewer) {
            return response()->json(['message' => 'Sign in to read the site.'], 401);
        }

        return $viewer;
    }

    private function resources(): Site
    {
        return Site::where('slug', 'resources')->firstOrFail();
    }

    /** @return \Illuminate\Support\Collection<int, Upload> */
    private function uploads(int $siteId)
    {
        return Upload::with('permissions')->where('site_id', $siteId)->where('approval_status', Upload::APPROVAL_APPROVED)->orderByDesc('id')->get();
    }

    private function title(string $html, string $fallback): string
    {
        if (preg_match('/<h[12][^>]*>(.*?)<\/h[12]>/is', $html, $m)) {
            $text = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES));
            if ($text !== '') {
                return $text;
            }
        }

        return $fallback;
    }
}
