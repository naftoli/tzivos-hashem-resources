<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentView;
use App\Models\PageBlock;
use App\Models\PageFileRule;
use App\Models\Site;
use App\Models\Upload;
use App\Services\FileGrant;
use App\Services\PageFileLinker;
use App\Services\SiteViewerResolver;
use App\Support\FileScopes;
use App\Support\PageFiles;
use App\Support\SiteViewer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        $scopes = FileScopes::load($site->id);
        if (! $block || $scopes->partIsRemoved($block->block_key)) {   // removed on the Files page: gone from the site
            return response()->json(['message' => 'No page with that name.'], 404);
        }

        $uploads = $this->uploads($site->id);
        $html = PageFiles::apply($block->html, $block->fileRules()->get(), $viewer->role, $scopes, $block->block_key);
        $html = $this->links->rewrite($html, $uploads, $viewer->role);
        $html = PageFiles::viewLinks($html, rtrim((string) config('admin.site_api'), '?'));
        // a weekly feature this person may not see (Files dashboard): its uploads are not added either
        $part = $scopes->forPart($block->block_key);
        if (! $part || $part->visibleToRole($viewer->role)) {
            $html = $this->links->appendForPage($html, $uploads, $viewer->role, $block);
        }
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

        $scopes = FileScopes::load($site->id);
        foreach (PageBlock::query()->where('site_id', $site->id)->orderBy('position')->get(['block_key', 'html']) as $block) {
            if ($scopes->partIsRemoved($block->block_key)) {
                continue;
            }
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

        $download = $request->boolean('download');
        if (! $this->mayOpen($upload, $viewer->role, $download)) {
            return response()->json(['message' => $download ? "You don't have permission to download this file." : "You don't have access to this file right now."], 403);
        }

        ContentView::record($viewer, 'file', $upload->original_name, $upload->page_key, $upload->id, $upload->site_id);

        $view = ! $download && $request->boolean('view');
        if (! $download && ! $view && ! $this->mayOpen($upload, $viewer->role, true) && $this->isBarFile($upload)) {
            // a Hachayol PDF this person may only view: only through the site's reader, not as a file the browser could save
            return response()->json(['message' => 'Open it with View PDF on the site.'], 403);
        }

        if ($view) {
            // the site's PDF reader: the file itself goes to siteApi.php, never an address the browser could keep
            if (! Storage::disk('public')->exists($upload->stored_path)) {
                return response()->json(['message' => 'The file could not be opened.'], 404);
            }

            return response()->file(Storage::disk('public')->path($upload->stored_path), self::viewHeaders($upload->mime_type ?: 'application/pdf'));
        }

        return response()->json(['url' => $this->grants->url($upload, $viewer, 120, $download)]);
    }

    /**
     * May this person view the Hachayol bar PDF at this address (a file on the site, like pdfs/666.pdf)?
     * siteApi.php asks before it sends that file to the site's reader.
     */
    public function bar(Request $request)
    {
        $viewer = $this->viewer($request);
        if ($viewer instanceof \Illuminate\Http\JsonResponse) {
            return $viewer;
        }
        $href = (string) $request->query('href', '');
        if (! PageFiles::isSitePath($href)) {
            return response()->json(['message' => 'Not a file on the site.'], 404);
        }

        $site = $this->resources();
        $scopes = FileScopes::load($site->id);
        $found = false;
        $blocks = PageBlock::where('site_id', $site->id)->where('html', 'like', '%class="hachayolbar"%')->get(['id', 'html']);
        foreach ($blocks as $block) {
            foreach (PageFiles::bars($block->html) as $bar) {
                if (html_entity_decode($bar['href'], ENT_QUOTES) !== $href) {
                    continue;
                }
                $found = true;
                if ($bar['issue'] !== null && $scopes->issueIsOff($bar['issue'])) {   // switched off, or removed on the Files page
                    return response()->json(['message' => "You don't have access to this file right now."], 403);
                }
                $own = PageFileRule::where('page_block_id', $block->id)->where('ref', 'link:'.$bar['href'])->first();
                $rule = $own ?? ($bar['issue'] !== null ? $scopes->forIssue($bar['issue']) : null);
                if ($rule && ! $rule->visibleToRole($viewer->role)) {
                    return response()->json(['message' => "You don't have access to this file right now."], 403);
                }
            }
        }
        if (! $found) {
            return response()->json(['message' => 'No Hachayol with that file.'], 404);
        }
        ContentView::record($viewer, 'file', basename($href), null, null, $site->id);

        return response()->json(['ok' => true]);
    }

    /** Shown in the page, never saved: open inline, keep no copy */
    public static function viewHeaders(string $type): array
    {
        return [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * The same settings the page uses (Site pages › Files, and the Files dashboard for Hachayol bars),
     * checked again when the file itself is asked for, so a hidden link or a missing Download button can't be worked around.
     */
    private function mayOpen(Upload $upload, string $role, bool $download): bool
    {
        $uploads = $this->uploads($upload->site_id);
        $check = fn ($rule) => $download ? $rule->canDownload($role) : $rule->visibleToRole($role);

        $rules = PageFileRule::query()
            ->where('ref', 'like', 'link:%')
            ->whereHas('pageBlock', fn ($q) => $q->where('site_id', $upload->site_id))
            ->get();
        $own = [];
        foreach ($rules as $rule) {
            $own[$rule->page_block_id.'|'.$rule->ref] = true;
            $hit = $this->links->uploadFor(substr($rule->ref, 5), $uploads);
            if ($hit && $hit->id === $upload->id && ! $check($rule)) {
                return false;
            }
        }

        $scopes = FileScopes::load($upload->site_id);
        $part = $upload->page_key ? $scopes->forPart($upload->page_key) : null;
        if ($part && ! $check($part)) {
            return false;
        }
        $barBlocks = PageBlock::where('site_id', $upload->site_id)->where('html', 'like', '%class="hachayolbar"%')->get(['id', 'html']);
        foreach ($barBlocks as $block) {
            foreach (PageFiles::bars($block->html) as $bar) {
                if ($bar['issue'] === null) {
                    continue;
                }
                if ($scopes->issueIsOff($bar['issue'])) {   // Visibility settings: off wins over single-file settings
                    $hit = $this->links->uploadFor($bar['href'], $uploads);
                    if ($hit && $hit->id === $upload->id) {
                        return false;
                    }
                    continue;
                }
                if (isset($own[$block->id.'|link:'.$bar['href']])) {
                    continue;
                }
                $rule = $scopes->forIssue($bar['issue']);
                if (! $rule) {
                    continue;
                }
                $hit = $this->links->uploadFor($bar['href'], $uploads);
                if ($hit && $hit->id === $upload->id && ! $check($rule)) {
                    return false;
                }
            }
        }

        return true;
    }

    /** Is this upload the PDF of a Hachayol bar? */
    private function isBarFile(Upload $upload): bool
    {
        $uploads = $this->uploads($upload->site_id);
        $blocks = PageBlock::where('site_id', $upload->site_id)->where('html', 'like', '%class="hachayolbar"%')->get(['id', 'html']);
        foreach ($blocks as $block) {
            foreach (PageFiles::bars($block->html) as $bar) {
                $hit = $this->links->uploadFor($bar['href'], $uploads);
                if ($hit && $hit->id === $upload->id) {
                    return true;
                }
            }
        }

        return false;
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
