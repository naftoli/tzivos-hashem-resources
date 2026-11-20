@extends('layouts.admin')

@section('title', 'Sites | Resources Admin')
@section('page-title', 'Sites')

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Add site</h2><span class="sub">The site shows up in the switcher at the top right away. Its pages are imported once afterwards.</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.sites.store') }}" style="display:grid;grid-template-columns:1fr 1.4fr auto;gap:16px;align-items:end;margin:0">
                @csrf
                <div class="field" style="margin:0"><label for="s-name">Name</label><input type="text" id="s-name" name="name" value="{{ old('name') }}" required style="width:100%"></div>
                <div class="field" style="margin:0"><label for="s-addr">Web address(es), one per line</label><textarea id="s-addr" name="addresses" rows="1" required placeholder="example.com" style="width:100%;min-height:40px;border:1px solid #e4e6ef;border-radius:6px;padding:9px 12px;font:inherit">{{ old('addresses') }}</textarea></div>
                <div><button type="submit" class="btn">Add site</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Sites</h2><span class="sub">{{ $sites->total() }} shown of {{ $total }} total</span></div>
        <div class="card-body" style="padding-top:6px">
            <form method="GET" action="{{ route('admin.sites') }}" id="siteFilter" style="margin:0">
            <table class="tbl">
                <thead>
                    <tr><th>Name</th><th>Web address(es)</th><th>Status</th><th class="num">Pages</th><th>Added</th><th class="num">Actions</th></tr>
                    <tr class="filters">
                        <td colspan="2"><input type="search" name="q" value="{{ request('q') }}" placeholder="Name or address" style="width:100%"></td>
                        <td><select name="status" onchange="this.form.submit()">
                            <option value="">Active</option>
                            @foreach (\App\Models\Site::STATUSES as $k => $label)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach
                            <option value="archived" @selected(request('status') === 'archived')>Archived</option>
                            <option value="all" @selected(request('status') === 'all' || request()->boolean('all'))>All</option>
                        </select></td>
                        <td></td><td></td>
                        <td class="num"><button type="submit" class="btn light" style="height:32px">Filter</button></td>
                    </tr>
                </thead>
                <tbody>
                @forelse ($sites as $site)
                    <tr class="row">
                        <td class="primary">{{ $site->name }}@if ($site->id === ($siteNow->id ?? null)) <span class="pill blue">Working on</span>@endif<br><span style="font-size:11.5px;color:#b5b5c3">{{ $site->slug }}</span></td>
                        <td style="word-break:break-all">{!! nl2br(e($site->addresses)) !!}</td>
                        <td><span class="pill {{ $site->archived_at ? 'grey' : ($site->status === 'imported' ? '' : 'orange') }}">{{ $site->statusLabel() }}</span></td>
                        <td class="num">{{ $site->pages_count }}</td>
                        <td>{{ $site->created_at?->copy()->timezone(config('admin.timezone'))->format('M j, Y') }}</td>
                        <td class="acts" style="white-space:nowrap">
                            @unless ($site->archived_at)
                                <button type="submit" form="switch-{{ $site->id }}" class="act green" title="Work on this site"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></button>
                            @endunless
                            <a class="act" href="{{ route('admin.sites.edit', $site) }}" title="Edit"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No sites match.</td></tr>
                @endforelse
                </tbody>
            </table>
            </form>
            @foreach ($sites as $site)
                <form method="POST" action="{{ route('admin.sites.switch') }}" id="switch-{{ $site->id }}" hidden>@csrf<input type="hidden" name="site" value="{{ $site->slug }}"></form>
            @endforeach
            @if ($sites->hasPages())
                <div class="pager" style="display:flex;gap:8px;align-items:center;justify-content:flex-end;margin-top:14px;color:#7e8299">
                    @if ($sites->onFirstPage())<span class="btn light" style="opacity:.5">‹ Previous</span>@else<a class="btn light" href="{{ $sites->previousPageUrl() }}">‹ Previous</a>@endif
                    <span>Page {{ $sites->currentPage() }} of {{ $sites->lastPage() }}</span>
                    @if ($sites->hasMorePages())<a class="btn light" href="{{ $sites->nextPageUrl() }}">Next ›</a>@else<span class="btn light" style="opacity:.5">Next ›</span>@endif
                </div>
            @endif
        </div>
    </div>
@endsection
