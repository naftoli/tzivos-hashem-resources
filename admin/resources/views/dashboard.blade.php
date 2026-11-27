@extends('layouts.admin')

@section('title', 'Dashboard | Resources Admin')
@section('page-title', 'Dashboard')

@section('content')
    @php
        $u = auth()->user();
        $mapper = app(\App\Services\MashpiaRoleMapper::class);
        $canAsk = $mapper->canRequestAdmin($u);
        $pendingMine = $canAsk ? $mapper->pendingRequest($u) : null;
        $pendingCount = $u->isHQAdmin() ? \App\Models\AdminAccessRequest::where('status', 'pending')->count() : 0;
    @endphp
    @push('head')
        <style>
            .tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 20px; }
            .tile { display: block; background: #fff; border-radius: 10px; padding: 22px; box-shadow: 0 0 30px rgba(82,63,105,.05); color: var(--ink); }
            .tile:hover { box-shadow: 0 0 30px rgba(82,63,105,.14); }
            .tile .ic { width: 46px; height: 46px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
            .tile .ic svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
            .tile h3 { margin: 0 0 4px; font-size: 15px; font-weight: 600; color: #181c32; }
            .tile p { margin: 0; color: #b5b5c3; font-weight: 500; }
        </style>
    @endpush

    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Welcome {{ $u->roleName() }}</h2></div>
        <div class="card-body">Signed in as {{ $u->email }}. Role: <span class="pill">{{ $u->roleName() }}</span></div>
    </div>

    @if ($canAsk)
        <div class="card">
            <div class="card-head"><h2>HQ admin access</h2></div>
            <div class="card-body">
                @if ($pendingMine)
                    <p style="margin:0">Your request was sent. An HQ admin will review it.</p>
                @else
                    <p style="margin:0 0 12px">Your Mashpia role is {{ $u->mashpia_role ?: 'Headquarters' }}. On this site that starts you as HQ staff. An existing HQ admin can make you an HQ admin, or you can ask here.</p>
                    <form method="POST" action="{{ route('account.request-admin') }}" style="margin:0">
                        @csrf
                        <button type="submit" class="btn">Request admin access</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="tiles">
        @if ($u->canEditPages())
            <a class="tile" href="{{ route('admin.pages') }}">
                <div class="ic" style="background:#e1f0ff;color:#3699ff"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/></svg></div>
                <h3>Site pages</h3>
                <p>{{ $siteNow->name }}: {{ \App\Models\Page::where('site_id', $siteNow->id)->count() }} pages · {{ \App\Models\PageBlock::where('site_id', $siteNow->id)->count() }} parts</p>
            </a>
        @endif
        @if ($u->canUpload())
            <a class="tile" href="{{ route('admin.uploads') }}">
                <div class="ic" style="background:#c9f7f5;color:#1bc5bd"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg></div>
                <h3>Upload files</h3>
                <p>{{ $siteNow->name }}: {{ \App\Models\Upload::where('site_id', $siteNow->id)->count() }} files uploaded</p>
            </a>
        @endif
        @if ($u->isHQ())
            <a class="tile" href="{{ route('admin.hachayols') }}">
                <div class="ic" style="background:#ffe2e5;color:#f64e60"><svg viewBox="0 0 24 24"><path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H11v17H5.5A1.5 1.5 0 0 1 4 18.5z"/><path d="M20 4.5A1.5 1.5 0 0 0 18.5 3H13v17h5.5a1.5 1.5 0 0 0 1.5-1.5z"/><path d="M7 7h1.5M7 10.5h1.5M15.5 7H17"/></svg></div>
                <h3>Upload Full Hachayols</h3>
                <p>PDFs or a whole folder</p>
            </a>
        @endif
        @if ($u->isHQAdmin())
            <a class="tile" href="{{ route('admin.files') }}">
                <div class="ic" style="background:#e1f0ff;color:#3699ff"><svg viewBox="0 0 24 24"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
                <h3>Files</h3>
                <p>Who can see what, and when</p>
            </a>
        @endif
        @if ($u->isHQAdmin())
            <a class="tile" href="{{ route('admin.roles') }}">
                <div class="ic" style="background:#eee5ff;color:#8950fc"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <h3>User roles</h3>
                <p>{{ \App\Models\User::count() }} users</p>
            </a>
            <a class="tile" href="{{ route('admin.admin-requests') }}">
                <div class="ic" style="background:#fff4de;color:#ffa800"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg></div>
                <h3>Admin requests</h3>
                <p>{{ $pendingCount }} waiting</p>
            </a>
            <a class="tile" href="{{ route('admin.login-settings') }}">
                <div class="ic" style="background:#fff4de;color:#ffa800"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
                <h3>Sign-in settings</h3>
                <p>Mode: {{ app(\App\Services\MashpiaAuthService::class)->mode() }}</p>
            </a>
        @endif
    </div>
@endsection
