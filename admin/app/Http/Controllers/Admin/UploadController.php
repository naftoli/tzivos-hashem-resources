<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageBlock;
use App\Models\Upload;
use App\Models\UploadPermission;
use App\Models\User;
use App\Support\CurrentSite;
use App\Support\HebrewDate;
use App\Support\SiteUrl;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function index()
    {
        return view('admin.uploads.index', [
            'site' => CurrentSite::get(),
            'uploads' => Upload::with(['uploader', 'permissions', 'pageBlock.page'])->where('site_id', CurrentSite::id())->latest()->get(),
            'perms' => $this->formDefaults(null),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'page_url' => ['required', 'string', 'max:1000'],
            'file_type' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(Upload::TYPES))],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:102400'], // 100 MB each
        ], ['page_url.required' => 'Paste the address of the page on the site.', 'file_type.required' => 'Choose the file type.', 'file_type.in' => 'Choose the file type.']);

        $page = $this->page($request);
        $rows = $this->permissionRows($request);
        $saved = 0;

        DB::transaction(function () use ($request, $page, $rows, &$saved) {
            foreach ($request->file('files') as $file) {
                $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                $stored = $file->storeAs('uploads/' . $page['key'], Str::random(8) . '-' . $name, 'public');
                $upload = Upload::create([
                    'site_id' => $page['block']->site_id,
                    'original_name' => $name,
                    'stored_path' => $stored,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                    'page_url' => $page['url'],
                    'page_key' => $page['key'],
                    'page_block_id' => $this->pickedBlock($request, $page),
                    'uploaded_by' => $request->user()->id,
                    'file_type' => $request->input('file_type'),
                ]);
                $upload->permissions()->createMany($rows);
                $saved++;
            }
        });

        return redirect()->route('admin.uploads')
            ->with('status', "Uploaded {$saved} file(s) for " . SiteUrl::label($page['block']) . '.');
    }

    public function edit(Request $request, Upload $upload)
    {
        $this->authorizeEdit($request, $upload);
        return view('admin.uploads.edit', [
            'upload' => $upload->load(['permissions', 'pageBlock.page']),
            'perms' => $this->formDefaults($upload),
        ]);
    }

    public function update(Request $request, Upload $upload)
    {
        $this->authorizeEdit($request, $upload);
        // Older uploads have no type yet: they may stay "Not set" until someone picks one
        $request->validate([
            'page_url' => ['required', 'string', 'max:1000'],
            'file_type' => [$upload->file_type === null ? 'nullable' : 'required', 'string', \Illuminate\Validation\Rule::in(array_keys(Upload::TYPES))],
        ], ['page_url.required' => 'Paste the address of the page on the site.', 'file_type.required' => 'Choose the file type.', 'file_type.in' => 'Choose the file type.']);
        $page = $this->page($request);
        $rows = $this->permissionRows($request);

        DB::transaction(function () use ($request, $upload, $page, $rows) {
            $upload->update(['page_url' => $page['url'], 'page_key' => $page['key'], 'page_block_id' => $this->pickedBlock($request, $page), 'file_type' => $request->input('file_type') ?: null]);
            $upload->permissions()->delete();
            $upload->permissions()->createMany($rows);
        });

        return redirect()->route('admin.uploads')->with('status', "Saved {$upload->original_name}.");
    }

    /** Check a pasted address (shown under the field while typing) */
    public function checkUrl(Request $request)
    {
        $r = SiteUrl::parse($request->query('url'));
        return is_string($r)
            ? response()->json(['ok' => false, 'message' => $r])
            : response()->json(['ok' => true, 'label' => SiteUrl::label($r['block']), 'key' => $r['key']]);
    }

    /** One part of the site, for the "Open site" picker */
    public function sitePart(string $key)
    {
        $b = PageBlock::with('page')->where('site_id', CurrentSite::id())->where('block_key', $key)->firstOrFail();
        return response()->json(['key' => $b->block_key, 'id' => $b->id, 'label' => SiteUrl::label($b), 'url' => SiteUrl::forKey($b->block_key), 'html' => $b->html]);
    }

    /** List of all parts for the picker's "go to" menu */
    public function siteParts()
    {
        return response()->json(PageBlock::with('page')->where('site_id', CurrentSite::id())->orderBy('page_id')->orderBy('position')->get()
            ->map(fn ($b) => ['key' => $b->block_key, 'page' => $b->page->slug, 'label' => $b->heading() ?: $b->block_key]));
    }

    /** Hebrew date -> secular date, for the preview under a Hebrew date */
    public function hebrew(Request $request)
    {
        $d = HebrewDate::toSecular((int) $request->query('d'), (int) $request->query('m'), (int) $request->query('y'));
        return response()->json($d ? ['ok' => true, 'date' => $d, 'label' => Carbon::parse($d)->format('D, M j, Y')]
            : ['ok' => false, 'message' => "That Hebrew date doesn't exist in that year."]);
    }

    private function authorizeEdit(Request $request, Upload $upload): void
    {
        // Only from inside the upload's own site (switch site at the top to reach it)
        abort_unless($upload->site_id === CurrentSite::id(), 404);
        $u = $request->user();
        if (! ($u->isHQ() || $upload->uploaded_by === $u->id)) {
            abort(response()->view('errors.access-denied', ['roles' => ['hq_admin', 'hq_staff']], 403));
        }
    }

    private function page(Request $request): array
    {
        $r = SiteUrl::parse($request->input('page_url'));
        if (is_string($r)) {
            throw ValidationException::withMessages(['page_url' => $r]);
        }
        return $r;
    }

    /** The part picked in "Open site", if it is the page in the address; otherwise the page itself */
    private function pickedBlock(Request $request, array $page): int
    {
        $id = (int) $request->input('page_block_id');
        return ($id && $id === $page['block']->id) ? $id : $page['block']->id;
    }

    /** Turn the form's "Who can see it" section into permission rows (one per level that can see it) */
    private function permissionRows(Request $request): array
    {
        $tz = config('admin.timezone');
        $in = (array) $request->input('perm', []);
        $rows = [['role' => 'hq_admin', 'mode' => 'always']]; // HQ admin can always see every file
        foreach (array_keys(User::ROLES) as $role) {
            if ($role === 'hq_admin' || empty($in[$role]['on'])) {
                continue;
            }
            $label = User::roleLabel($role);
            $mode = $in[$role]['mode'] ?? 'always';
            if (! isset(UploadPermission::MODES[$mode])) {
                throw ValidationException::withMessages(['perm' => "{$label}: choose when they can see it."]);
            }
            $row = ['role' => $role, 'mode' => $mode];
            foreach (['start' => 'starts_at', 'end' => 'ends_at'] as $which => $col) {
                if (! in_array($mode, [$which, 'limited'], true)) {
                    continue;
                }
                $f = (array) ($in[$role][$which] ?? []);
                $what = $which === 'start' ? 'start' : 'end';
                $hebrew = ($f['kind'] ?? 'secular') === 'hebrew';
                if ($hebrew) {
                    $date = HebrewDate::toSecular((int) ($f['hd'] ?? 0), (int) ($f['hm'] ?? 0), (int) ($f['hy'] ?? 0));
                    if (! $date) {
                        throw ValidationException::withMessages(['perm' => "{$label}: the Hebrew {$what} date doesn't exist. Check the day, month and year."]);
                    }
                    $row[$which . '_hebrew'] = HebrewDate::label((int) $f['hd'], (int) $f['hm'], (int) $f['hy']);
                } else {
                    $date = $f['date'] ?? '';
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
                        throw ValidationException::withMessages(['perm' => "{$label}: choose the {$what} date."]);
                    }
                }
                $time = $f['time'] ?? '';
                if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                    throw ValidationException::withMessages(['perm' => "{$label}: choose the {$what} time."]);
                }
                $row[$col] = Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", $tz)->utc();
                $row[$which . '_entered_as'] = $hebrew ? 'hebrew' : 'secular';
            }
            if ($mode === 'limited' && $row['ends_at']->lte($row['starts_at'])) {
                throw ValidationException::withMessages(['perm' => "{$label}: the end has to be after the start."]);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /** Values for the form: from the old input, the upload being edited, or blank */
    private function formDefaults(?Upload $upload): array
    {
        $tz = config('admin.timezone');
        $out = [];
        foreach (array_keys(User::ROLES) as $role) {
            $p = $upload?->permissions->firstWhere('role', $role);
            $v = ['on' => $role === 'hq_admin' || (bool) $p, 'mode' => $p->mode ?? 'always'];
            foreach (['start' => 'starts_at', 'end' => 'ends_at'] as $which => $col) {
                $dt = $p?->{$col}?->copy()->timezone($tz);
                $heb = $dt ? HebrewDate::fromSecular($dt->toDateString()) : [null, null, null];
                $v[$which] = [
                    'kind' => $p?->{$which . '_entered_as'} ?? 'secular',
                    'date' => $dt?->toDateString() ?? '',
                    'time' => $dt?->format('H:i') ?? ($which === 'start' ? '00:00' : '23:59'),
                    'hd' => $heb[0], 'hm' => $heb[1], 'hy' => $heb[2],
                ];
            }
            $out[$role] = $v;
        }
        return $out;
    }
}
