<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageBlock;
use App\Support\HtmlLayout;
use App\Support\HtmlMove;
use App\Support\HtmlText;
use App\Support\CurrentSite;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        $site = CurrentSite::get();
        return view('admin.pages.index', [
            'site' => $site,
            'pages' => Page::where('site_id', $site->id)->orderBy('position')->withCount('blocks')->get(),
        ]);
    }

    public function show(Request $request, Page $page)
    {
        $q = trim((string) $request->query('q', ''));
        $blocks = $page->blocks()->get();
        if ($q !== '') {
            $blocks = $blocks->filter(fn (PageBlock $b) => stripos($b->block_key, $q) !== false
                || stripos((string) $b->heading(), $q) !== false)->values();
        }
        return view('admin.pages.show', compact('page', 'blocks', 'q'));
    }

    /** Search the words of every part (and part names/headings). */
    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $results = [];
        $capped = false;
        if (mb_strlen($q) >= 2) {
            $like = '%' . $q . '%'; // wildcards typed by the user only widen the first pass; the word check below is exact
            $siteId = CurrentSite::id();
            $pages = Page::where('site_id', $siteId)->get()->keyBy('id');
            $order = $pages->pluck('position', 'id');
            $ids = PageBlock::where('site_id', $siteId)->where(fn ($w) => $w->where('block_key', 'like', $like)->orWhere('html', 'like', $like))
                ->get(['id', 'page_id', 'position'])
                ->sortBy(fn ($r) => [$order[$r->page_id] ?? 0, $r->position])
                ->pluck('id');
            foreach ($ids->chunk(25) as $chunk) {
                foreach (PageBlock::whereIn('id', $chunk)->get()->sortBy(fn ($b) => $chunk->search($b->id)) as $b) {
                    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<(template|script|style)\b.*?<\/\1>/is', ' ', $b->html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    $pos = mb_stripos($text, $q);
                    if ($pos === false && mb_stripos($b->block_key, $q) === false) {
                        continue; // only matched inside the code, not the words
                    }
                    $snippet = $pos === false ? '' : (($pos > 60 ? '…' : '') . mb_substr($text, max(0, $pos - 60), mb_strlen($q) + 120) . '…');
                    $results[] = ['page' => $pages[$b->page_id], 'id' => $b->id, 'key' => $b->block_key, 'heading' => $b->heading(), 'snippet' => $snippet];
                    unset($text);
                    if (count($results) >= 200) { $capped = true; break 2; }
                }
            }
        }
        return view('admin.pages.search', compact('q', 'results', 'capped'));
    }

    public function edit(Page $page, PageBlock $block)
    {
        abort_unless($block->page_id === $page->id, 404);
        $segments = HtmlText::segments($block->html);
        $groups = HtmlLayout::groups($block->html);
        $els = HtmlMove::elements($block->html);
        return view('admin.pages.edit', [
            'page' => $page,
            'block' => $block,
            'marked' => HtmlText::marked($block->html, $segments, $groups, $els),
            'elInfo' => HtmlMove::info($els),
            'groupSizes' => array_map(fn ($g) => count($g['items']), $groups),
            'texts' => array_column($segments, 'text'),
            'base' => hash('sha256', $block->html),
        ]);
    }

    /** Advanced: save the whole block's code as typed. */
    public function update(Request $request, Page $page, PageBlock $block)
    {
        abort_unless($block->page_id === $page->id, 404);
        $request->validate(['html' => ['present', 'nullable', 'string']]);

        // Browsers send textarea line breaks as \r\n; the site files use \n
        $html = str_replace("\r\n", "\n", (string) $request->input('html', ''));

        return $this->store($request, $page, $block, $html, true);
    }

    /** Friendly editor: change only the edited text pieces, moved items and nudged elements (all saved together). */
    public function updateText(Request $request, Page $page, PageBlock $block)
    {
        abort_unless($block->page_id === $page->id, 404);
        $request->validate(['changes' => ['required', 'string'], 'order' => ['nullable', 'string'], 'nudges' => ['nullable', 'string'], 'base' => ['required', 'string']]);

        if (! hash_equals(hash('sha256', $block->html), (string) $request->input('base'))) {
            return back()->withErrors(['changes' => 'This part was changed by someone else since you opened it. Nothing was saved. Reload the page and make your change again.']);
        }
        $changes = json_decode((string) $request->input('changes'), true);
        $orders = json_decode((string) $request->input('order', '{}'), true) ?? [];
        $nudges = json_decode((string) $request->input('nudges', '{}'), true) ?? [];
        if (! is_array($changes) || ! is_array($orders) || ! is_array($nudges)) {
            return back()->withErrors(['changes' => 'Nothing was saved (the changes could not be read).']);
        }
        try {
            $html = HtmlText::apply($block->html, $changes, $orders, $nudges);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['changes' => 'Nothing was saved: ' . $e->getMessage()]);
        }

        return $this->store($request, $page, $block, $html, false);
    }

    private function store(Request $request, Page $page, PageBlock $block, string $html, bool $code)
    {
        if ($html !== $block->html) {
            $block->update(['html' => $html, 'edited_at' => now(), 'edited_by' => $request->user()->id]);
            $msg = 'Saved.';
        } else {
            $msg = 'No changes to save.';
        }
        return redirect()->route('admin.pages.block', [$page, $block] + ($code ? ['code' => 1] : []))->with('status', $msg);
    }
}
