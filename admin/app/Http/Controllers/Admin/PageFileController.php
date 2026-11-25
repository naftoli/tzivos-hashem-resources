<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageFileRule;
use App\Models\Upload;
use App\Support\AccessForm;
use App\Support\PageFiles;
use Illuminate\Http\Request;

/** Site pages › a part › Files: remove (and restore) the part's own files, and choose who can see them. */
class PageFileController extends Controller
{
    public function index(Page $page, PageBlock $block)
    {
        abort_unless($block->page_id === $page->id, 404);
        $rules = $block->fileRules()->with('editor')->get()->keyBy('ref');
        $pictures = $this->pictureMap();
        $files = array_map(function ($f) use ($rules, $pictures) {
            $f['rule'] = $rules[$f['ref']] ?? null;
            $f['thumb'] = $f['kind'] === 'image' && isset($pictures[$f['path']]) ? 'https://tzivoshashem.com'.$pictures[$f['path']] : null;

            return $f;
        }, PageFiles::list($block->html));
        $uploads = Upload::where('site_id', $block->site_id)
            ->where(fn ($q) => $q->where('page_block_id', $block->id)->orWhere('page_key', $block->block_key))
            ->orderBy('id')->get();

        return view('admin.pages.files', [
            'page' => $page,
            'block' => $block,
            'files' => $files,
            'uploads' => $uploads,
            'levels' => AccessForm::levels(),
        ]);
    }

    /** Save who can see (and download, and when) one file */
    public function update(Request $request, Page $page, PageBlock $block)
    {
        $ref = $this->ref($request, $page, $block);
        $rule = $this->rule($block, $ref);
        $rule->fill(AccessForm::read($request, $this->hasDownload($block, $ref)));
        $rule->updated_by = $request->user()->id;
        $rule->save();   // kept even with everything ticked, so it can open up what a broader setting limits

        return back()->with('status', 'Saved '.$this->name($block, $ref).'.');
    }

    /** Back to the broader settings (Files dashboard) for this file. A removed file stays removed. */
    public function clear(Request $request, Page $page, PageBlock $block)
    {
        $ref = $this->ref($request, $page, $block);
        $rule = $this->rule($block, $ref);
        if ($rule->exists) {
            $rule->fill(['roles' => null, 'download_roles' => null, 'starts_at' => null, 'ends_at' => null])->save();
            $this->dropIfPlain($rule);
        }

        return back()->with('status', 'Cleared the setting for '.$this->name($block, $ref).'.');
    }

    public function remove(Request $request, Page $page, PageBlock $block)
    {
        $ref = $this->ref($request, $page, $block);
        $this->rule($block, $ref)->fill(['removed' => true, 'updated_by' => $request->user()->id])->save();

        return back()->with('status', 'Removed '.$this->name($block, $ref).' from the site. You can restore it here.');
    }

    public function restore(Request $request, Page $page, PageBlock $block)
    {
        $ref = $this->ref($request, $page, $block);
        $rule = $this->rule($block, $ref);
        $rule->fill(['removed' => false, 'updated_by' => $request->user()->id])->save();
        $this->dropIfPlain($rule);

        return back()->with('status', 'Restored '.$this->name($block, $ref).'.');
    }

    private function ref(Request $request, Page $page, PageBlock $block): string
    {
        abort_unless($block->page_id === $page->id, 404);
        $ref = (string) $request->input('ref');
        abort_unless(in_array($ref, array_column(PageFiles::list($block->html), 'ref'), true), 404);

        return $ref;
    }

    private function rule(PageBlock $block, string $ref): PageFileRule
    {
        return PageFileRule::firstOrNew(['page_block_id' => $block->id, 'ref' => $ref]);
    }

    /** Back to normal (on the site, everyone): no setting left to keep */
    private function dropIfPlain(PageFileRule $rule): void
    {
        if ($rule->exists && $rule->isPlain()) {
            $rule->delete();
        }
    }

    private function hasDownload(PageBlock $block, string $ref): bool
    {
        foreach (PageFiles::list($block->html) as $f) {
            if ($f['ref'] === $ref) {
                return ! empty($f['download']);
            }
        }

        return false;
    }

    private function name(PageBlock $block, string $ref): string
    {
        foreach (PageFiles::list($block->html) as $f) {
            if ($f['ref'] === $ref) {
                return $f['kind'] === 'image' ? 'the picture' : basename($f['path']);
            }
        }

        return 'the file';
    }

    /** @return array<string, string> picture id => /resources/assets/img/... */
    private function pictureMap(): array
    {
        $file = public_path('site-preview/IMG.json');

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : [];
    }
}
