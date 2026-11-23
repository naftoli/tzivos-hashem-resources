@extends('layouts.admin')

@section('title', $site->name . ' | Sites | Resources Admin')
@section('page-title', 'Sites')

@section('content')
    <div class="crumbs" style="color:#b5b5c3;font-weight:500;margin-bottom:14px"><a href="{{ route('admin.sites') }}" style="color:#7e8299">Sites</a> › {{ $site->name }}</div>
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif
    <div class="card">
        <div class="card-head"><h2>{{ $site->name }}</h2><span class="sub"><span class="pill {{ $site->archived_at ? 'grey' : ($site->status === 'imported' ? '' : 'orange') }}">{{ $site->statusLabel() }}</span> · {{ $site->pages_count }} pages · {{ $site->uploads_count }} uploads · short name <b>{{ $site->slug }}</b></span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.sites.update', $site) }}" style="max-width:640px;margin:0">
                @csrf
                @method('PUT')
                <div class="field"><label for="e-name">Name</label><input type="text" id="e-name" name="name" value="{{ old('name', $site->name) }}" required style="width:100%"></div>
                <div class="field"><label for="e-addr">Web address(es), one per line</label><textarea id="e-addr" name="addresses" rows="4" required style="width:100%;border:1px solid #e4e6ef;border-radius:6px;padding:9px 12px;font:inherit">{{ old('addresses', $site->addresses) }}</textarea>
                    <div style="font-size:12px;color:#7e8299;margin-top:4px">Pasted page addresses on the upload page must start with one of these.</div></div>
                <div class="field"><label for="e-url">Address for page links (optional)</label><input type="text" id="e-url" name="site_url" value="{{ old('site_url', $site->site_url) }}" placeholder="{{ $site->baseUrl() }}" style="width:100%"></div>
                <div class="field"><label for="e-repo">Where its code lives (optional)</label><input type="text" id="e-repo" name="repo" value="{{ old('repo', $site->repo) }}" style="width:100%"></div>
                <div class="field"><label for="e-notes">Notes (optional)</label><textarea id="e-notes" name="notes" rows="3" style="width:100%;border:1px solid #e4e6ef;border-radius:6px;padding:9px 12px;font:inherit">{{ old('notes', $site->notes) }}</textarea></div>
                @if ($site->slug !== 'resources')
                    <div class="field"><label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="hidden" name="archive" value="0"><input type="checkbox" name="archive" value="1" @checked($site->archived_at)> Archived (hidden from the switcher; nothing is deleted)</label></div>
                @endif
                <button type="submit" class="btn">Save</button>
            </form>
        </div>
    </div>
@endsection
