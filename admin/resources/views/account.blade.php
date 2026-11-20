@extends('layouts.admin')

@section('title', 'My dashboard | Resources Admin')
@section('page-title', 'My dashboard')

@section('content')
    @php $tags = ['hq_admin' => 'blue', 'hq_staff' => 'purple', 'base' => 'grey', 'news' => 'orange']; $tz = config('admin.timezone'); @endphp
    <div class="card">
        <div class="card-head"><h2>{{ $user->name }}</h2></div>
        <div class="card-body">
            <table class="tbl" style="max-width:640px">
                <tbody>
                    <tr><th style="width:160px">Name</th><td>{{ $user->name }}</td></tr>
                    <tr><th>Email</th><td>{{ $user->email }}</td></tr>
                    <tr><th>Level</th><td><span class="pill {{ $tags[$user->role] ?? '' }}">{{ $user->roleName() }}</span>@if ($user->isOwner()) <span class="pill red">Owner</span>@endif</td></tr>
                    <tr><th>Account created</th><td>{{ $user->created_at?->copy()->timezone($tz)->format('M j, Y g:i A') }}</td></tr>
                </tbody>
            </table>
            <form method="POST" action="{{ route('logout') }}" style="margin-top:16px">
                @csrf
                <button type="submit" class="btn light">Sign out</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>My uploads</h2><span class="sub">{{ $uploads->count() }} total</span></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead>
                    <tr><th>File</th><th>Type</th><th class="num">Size</th><th>Page on the site</th><th>Who can see it</th><th class="num">Actions</th></tr>
                    <tr class="filters"><td><input data-col="0" placeholder="File"></td><td><select data-col="1" data-exact="1"><option value="">All types</option>@foreach (\App\Models\Upload::TYPES as $tLabel)<option>{{ $tLabel }}</option>@endforeach<option>Not set</option></select></td><td></td><td><input data-col="3" placeholder="Page"></td><td><input data-col="4" placeholder="Level"></td><td></td></tr>
                </thead>
                <tbody>
                @forelse ($uploads as $upload)
                    <tr class="row">
                        <td class="primary" style="word-break:break-all">{{ $upload->original_name }}</td>
                        <td><span class="pill {{ $upload->file_type ? 'purple' : 'grey' }}">{{ $upload->typeLabel() }}</span></td>
                        <td class="num">{{ $upload->humanSize() }}</td>
                        <td style="word-break:break-all">
                            <span class="pill grey">{{ $upload->site->name ?? '' }}</span>
                            @if ($upload->page_url)
                                {{ $upload->pageLabel() ?? $upload->page_key }}<br><a href="{{ str_starts_with($upload->page_url, 'http') ? $upload->page_url : 'https://' . $upload->page_url }}" target="_blank" rel="noopener" style="font-size:11.5px">{{ $upload->page_url }}</a>
                            @else
                                <span class="pill grey">No page chosen</span><br><span style="font-size:11.5px;color:#b5b5c3">Old location: {{ $upload->destination }}</span>
                            @endif
                        </td>
                        <td>
                            @foreach (\App\Models\User::ROLES as $role => $label)
                                @php $p = $upload->permissions->firstWhere('role', $role); @endphp
                                @if ($p)
                                    <div style="margin:2px 0"><span class="pill {{ $p->isOpen() ? '' : 'orange' }}">{{ $label }}</span> <span style="font-size:11.5px">{{ $p->summary() }}</span></div>
                                @endif
                            @endforeach
                        </td>
                        <td class="acts" style="white-space:nowrap">
                            <a class="act green" href="{{ $upload->publicUrl() }}" target="_blank" title="Open {{ $upload->publicPath() }}"><svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14L21 3"/></svg></a>
                            @if ($user->canUpload() && $upload->site_id === ($siteNow->id ?? null))
                                <a class="act" href="{{ route('admin.uploads.edit', $upload) }}" title="Change page or who can see it"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No files uploaded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
