<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CurrentSite;
use App\Support\Hachayol\HachayolParser;
use App\Support\Hachayol\HachayolSite;
use App\Support\Hachayol\PdfTools;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Upload Full Hachayols (its own button in the sidebar): one or more whole Hachayol PDFs (or a folder of them).
 * Each PDF is read (issue, date, Parsha, the "Peek inside" contents list) and split into its features,
 * shown in a preview where everything can be changed, and only then put on the site.
 * The PDFs wait in storage/app/hachayol-import/<batch>/ until they are published or discarded.
 */
class HachayolImportController extends Controller
{
    /** The Upload Full Hachayols page: choose PDFs or a folder */
    public function index()
    {
        return view('admin.hachayols.index', [
            'hachayolTools' => ['text' => PdfTools::hasText(), 'pictures' => PdfTools::hasPictures()],
        ]);
    }

    /** Read one PDF (the page sends each file on its own, so big magazines don't hit the upload size limit together) */
    public function analyze(Request $request)
    {
        $request->validate([
            'batch' => ['required', 'string', 'regex:/^[A-Za-z0-9]{16}$/'],
            'pdf' => ['required', 'file', 'max:409600'],   // 400 MB
        ], ['pdf.required' => 'Choose a Hachayol PDF.', 'pdf.max' => 'That PDF is bigger than 400 MB.']);
        $file = $request->file('pdf');
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') {
            return response()->json(['ok' => false, 'name' => $name, 'message' => 'Not a PDF.'], 422);
        }
        if (! PdfTools::hasText()) {
            return response()->json(['ok' => false, 'name' => $name, 'message' => PdfTools::MISSING_TEXT], 422);
        }

        $dir = $this->dir($request, $request->input('batch'), true);
        $id = Str::lower(Str::random(10));
        $pdf = $dir.'/'.$id.'.pdf';
        $file->move($dir, $id.'.pdf');
        try {
            $a = HachayolParser::file($pdf, $name);
        } catch (\Throwable $e) {
            File::delete($pdf);

            return response()->json(['ok' => false, 'name' => $name, 'message' => $e->getMessage()], 422);
        }

        $site = new HachayolSite(CurrentSite::id());
        $slot = $site->slotFor($a['parsha_hebrew'], $a['issue']);
        foreach ($a['sections'] as &$s) {
            $parts = $s['feature'] ? $site->featureParts($s['feature']) : null;
            $s['mission'] = $parts['mission'] ?? '';
            $s['recordings'] = $s['feature'] === 'niggun' ? $site->recordings($s['title']) : [];
            $s['text'] = HachayolSite::bodyText($s['body']);
            unset($s['body']);
        }
        unset($s);

        // small pictures of each section's first page, for the preview (when the picture tool is there)
        $thumbs = false;
        if (PdfTools::hasPictures()) {
            try {
                $pages = array_unique(array_merge([1], array_column($a['sections'], 'start')));
                foreach ($pages as $p) {
                    $made = PdfTools::pictures($pdf, $p, $p, $dir.'/'.$id.'-thumbs', 220);
                }
                $thumbs = true;
            } catch (\Throwable) {
                $thumbs = false;
            }
        }

        file_put_contents($dir.'/'.$id.'.json', json_encode([
            'id' => $id, 'name' => $name, 'size' => filesize($pdf), 'slot' => $slot, 'thumbs' => $thumbs, 'analysis' => $a,
        ], JSON_UNESCAPED_UNICODE));

        return response()->json(['ok' => true, 'name' => $name, 'issue' => $a['issue'], 'sections' => count($a['sections']),
            'warnings' => $a['warnings'], 'preview' => route('admin.hachayols.preview', $request->input('batch'))]);
    }

    /** The preview: every PDF read in this batch, each split into its sections, all editable */
    public function preview(Request $request, string $batch)
    {
        $dir = $this->dir($request, $batch);
        $site = new HachayolSite(CurrentSite::id());
        $slots = $site->slots();
        $items = [];
        foreach ($this->items($dir) as $item) {
            $item['parts'] = [];
            foreach (array_keys(HachayolParser::FEATURES) as $f) {
                $item['parts'][$f] = $site->featureParts($f);
            }
            $items[] = $item;
        }
        if ($items === []) {
            return redirect()->route('admin.hachayols')->with('status', 'Nothing is waiting to be published.');
        }

        return view('admin.hachayols.preview', [
            'batch' => $batch,
            'items' => $items,
            'slots' => $slots,
            'features' => HachayolParser::FEATURES,
            'canPicture' => PdfTools::hasPictures(),
        ]);
    }

    /** The small picture of a page, for the preview */
    public function thumb(Request $request, string $batch, string $id, int $page)
    {
        abort_unless(preg_match('/^[a-z0-9]{10}$/', $id), 404);
        $file = $this->dir($request, $batch).'/'.$id.'-thumbs/p'.$page.'.jpg';
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function publish(Request $request, string $batch)
    {
        $dir = $this->dir($request, $batch);
        if (! PdfTools::hasPictures()) {
            return back()->withErrors(['pdf' => PdfTools::MISSING_PICTURES]);
        }
        $site = new HachayolSite(CurrentSite::id());
        $done = [];
        $errors = [];
        $form = (array) $request->input('issues', []);
        foreach ($this->items($dir) as $item) {
            $in = (array) ($form[$item['id']] ?? []);
            if (empty($in['on'])) {
                continue;
            }
            $pages = (int) $item['analysis']['pages'];
            $sections = [];
            foreach ($item['analysis']['sections'] as $i => $s) {
                $f = (array) ($in['sections'][$i] ?? []);
                $feature = (string) ($f['feature'] ?? '');
                if ($feature !== '' && ! isset(HachayolParser::FEATURES[$feature])) {
                    $feature = '';
                }
                $start = min(max(1, (int) ($f['start'] ?? $s['start'])), $pages);
                $end = min(max($start, (int) ($f['end'] ?? $s['end'])), $pages);
                $title = trim((string) ($f['title'] ?? $s['title']));
                // recordings: only the ones the site already has for this niggun
                $chips = [];
                if ($feature === 'niggun') {
                    $picked = (array) ($f['recordings'] ?? []);
                    foreach ($site->recordings($title) as $r) {
                        if (in_array($r['href'], $picked, true)) {
                            $chips[] = $r['html'];
                        }
                    }
                }
                $sections[] = [
                    'on' => ! empty($f['on']), 'feature' => $feature, 'label' => (string) ($s['label'] ?? ''), 'title' => $title,
                    'start' => $start, 'end' => $end, 'mission' => (string) ($f['mission'] ?? $s['mission'] ?? ''),
                    'body' => (string) ($f['text'] ?? $s['text'] ?? ''), 'recordings' => $chips,
                ];
            }
            $issue = ['issue' => trim((string) ($in['issue'] ?? '')), 'slot' => (string) ($in['slot'] ?? ''), 'parsha_name' => (string) ($in['parsha_name'] ?? ''),
                'date' => (string) ($in['date'] ?? ''), 'sections' => $sections];
            try {
                $done[] = $site->publish($issue, $dir.'/'.$item['id'].'.pdf', $request->user());
                $site = new HachayolSite(CurrentSite::id());   // the next issue sees this one
                File::delete([$dir.'/'.$item['id'].'.pdf', $dir.'/'.$item['id'].'.json']);
                File::deleteDirectory($dir.'/'.$item['id'].'-thumbs');
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($done === [] && $errors === []) {
            return back()->withErrors(['pdf' => 'Tick at least one Hachayol to publish.']);
        }
        $msg = $done ? 'Published '.implode('; ', $done).'.' : '';
        if ($errors) {
            return redirect()->route('admin.hachayols.preview', $batch)->with('status', $msg)->withErrors(['pdf' => implode(' ', $errors)]);
        }
        File::deleteDirectory($dir);

        return redirect()->route('admin.hachayols')->with('status', $msg);
    }

    public function discard(Request $request, string $batch)
    {
        File::deleteDirectory($this->dir($request, $batch));

        return redirect()->route('admin.hachayols')->with('status', 'Discarded. Nothing was added to the site.');
    }

    /** @return list<array> the PDFs read in this batch, oldest first */
    private function items(string $dir): array
    {
        $items = [];
        foreach (glob($dir.'/*.json') ?: [] as $f) {
            $item = json_decode((string) file_get_contents($f), true);
            if (is_array($item) && is_file($dir.'/'.$item['id'].'.pdf')) {
                $item['mtime'] = filemtime($f);
                $items[] = $item;
            }
        }
        usort($items, fn ($a, $b) => [(int) ($a['analysis']['issue'] ?? 0), $a['mtime']] <=> [(int) ($b['analysis']['issue'] ?? 0), $b['mtime']]);

        return $items;
    }

    /** This person's batch folder (a batch belongs to whoever started it) */
    private function dir(Request $request, string $batch, bool $create = false): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{16}$/', $batch), 404);
        $dir = storage_path('app/hachayol-import/'.$request->user()->id.'-'.$batch);
        if ($create && ! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        abort_unless(is_dir($dir), 404);

        return $dir;
    }
}
