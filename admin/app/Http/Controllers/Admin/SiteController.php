<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Support\CurrentSite;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteController extends Controller
{
    /** Sites screen (HQ admin): one page of sites at a time, filtered on the server */
    public function index(Request $request)
    {
        $q = Site::query()->matching($request->query('q'))->withCount('pages');
        $status = (string) $request->query('status', '');
        if ($status === 'archived') $q->whereNotNull('archived_at');
        elseif (isset(Site::STATUSES[$status])) $q->whereNull('archived_at')->where('status', $status);
        elseif ($status !== 'all' && ! $request->boolean('all')) $q->whereNull('archived_at');
        return view('admin.sites.index', [
            'sites' => $q->orderBy('name')->orderBy('id')->paginate(25)->withQueryString(),
            'total' => Site::count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $site = Site::create($data + ['slug' => Site::uniqueSlug($data['name']), 'status' => 'not_imported']);
        return redirect()->route('admin.sites')->with('status', "Added {$site->name}. Its pages still need to be imported once (--site={$site->slug}).");
    }

    public function edit(Site $site)
    {
        return view('admin.sites.edit', ['site' => $site->loadCount(['pages', 'uploads'])]);
    }

    public function update(Request $request, Site $site)
    {
        $data = $this->validated($request);
        if ($request->has('archive')) {
            if ($request->boolean('archive') && $site->slug === 'resources') {
                throw ValidationException::withMessages(['archive' => 'The Resources site can\'t be archived.']);
            }
            $data['archived_at'] = $request->boolean('archive') ? ($site->archived_at ?? now()) : null;
        }
        $site->update($data);
        CurrentSite::forget();
        return redirect()->route('admin.sites')->with('status', "Saved {$site->name}.");
    }

    /** Switcher: matching sites, a few at a time (never the whole list) */
    public function search(Request $request)
    {
        $sites = Site::active()->matching($request->query('q'))->orderBy('name')->limit(15)->get(['id', 'name', 'slug', 'status', 'addresses']);
        return response()->json($sites->map(fn ($s) => [
            'slug' => $s->slug, 'name' => $s->name, 'status' => $s->statusLabel(),
            'address' => strtok((string) $s->addresses, "\n") ?: '', 'current' => $s->id === CurrentSite::id(),
        ]));
    }

    /** Switch the site being worked on (remembered in the session) */
    public function switch(Request $request)
    {
        $site = Site::active()->where('slug', (string) $request->input('site'))->firstOrFail();
        CurrentSite::set($site);
        $u = $request->user();
        $to = $u->canEditPages() ? route('admin.pages') : ($u->canUpload() ? route('admin.uploads') : route('dashboard'));
        return redirect($to)->with('status', "Now working on {$site->name}.");
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'addresses' => ['required', 'string', 'max:4000'],
            'site_url' => ['nullable', 'string', 'max:500'],
            'repo' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ], ['addresses.required' => 'Type the site\'s web address (one per line if it has more than one).']);
        $addresses = Site::cleanAddresses((string) $request->input('addresses'));
        if ($addresses === null) {
            throw ValidationException::withMessages(['addresses' => 'One of the web addresses doesn\'t look right. Type them like example.com or example.com/some/path, one per line.']);
        }
        $url = trim((string) $request->input('site_url'));
        if ($url !== '' && ! preg_match('#^https?://[^\s/]+#i', $url)) {
            throw ValidationException::withMessages(['site_url' => 'The page-link address should start with https://']);
        }
        return [
            'name' => trim($request->input('name')),
            'addresses' => $addresses,
            'site_url' => $url ?: null,
            'repo' => trim((string) $request->input('repo')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
        ];
    }
}
