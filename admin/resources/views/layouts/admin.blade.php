@php
    $u = auth()->user();
    $is = fn (...$names) => request()->routeIs(...$names);
    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'pages' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
        'uploads' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/>',
        'roles' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'map' => '<path d="M4 19V5l6-2 6 2 4-2v14l-4 2-6-2-6 2z"/><path d="M10 3v14M16 5v14"/>',
        'requests' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>',
        'sites' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'signin' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    ];
    $siteNow ??= \App\Support\CurrentSite::get();
    $showSwitcher = $u->canEditPages() || $u->canUpload();
    $nav = [];
    $nav['General'][] = ['Dashboard', route('dashboard'), 'dashboard', $is('dashboard')];
    if ($u->canEditPages()) {
        $nav['Content'][] = ['Site pages', route('admin.pages'), 'pages', $is('admin.pages*')];
    }
    if ($u->canUpload()) {
        $nav['Content'][] = ['Upload files', route('admin.uploads'), 'uploads', $is('admin.uploads*')];
    }
    if ($u->isHQAdmin()) {
        $nav['Settings'][] = ['Sites', route('admin.sites'), 'sites', $is('admin.sites*')];
        $nav['Settings'][] = ['User roles', route('admin.roles'), 'roles', $is('admin.roles')];
        $nav['Settings'][] = ['Mashpia roles', route('admin.mashpia-roles'), 'map', $is('admin.mashpia-roles*')];
        $nav['Settings'][] = ['Admin requests', route('admin.admin-requests'), 'requests', $is('admin.admin-requests*')];
        $nav['Settings'][] = ['Sign-in settings', route('admin.login-settings'), 'signin', $is('admin.login-settings')];
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Resources Admin')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        :root { --navy: #1e1e2d; --navy-2: #1b1b28; --side: 72px; --side-open: 250px; --bg: #f2f3f8; --ink: #3f4254; --muted: #b5b5c3; --blue: #3699ff; --line: #ebedf3; }
        html, body { margin: 0; }
        body { font-family: Poppins, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); font-size: 13px; }
        a { color: var(--blue); text-decoration: none; }

        /* sidebar: icons only, opens over the page on hover */
        .side { position: fixed; z-index: 50; top: 0; left: 0; bottom: 0; width: var(--side); background: var(--navy); display: flex; flex-direction: column; overflow: hidden; transition: width .2s ease, box-shadow .2s ease; }
        .side:hover, .side:focus-within { width: var(--side-open); box-shadow: 6px 0 24px rgba(0,0,0,.25); }
        .brand { height: 64px; flex: 0 0 64px; display: flex; align-items: center; gap: 12px; padding: 0 15px; background: var(--navy-2); white-space: nowrap; }
        .brand .logo { width: 42px; height: 42px; flex: 0 0 42px; border-radius: 10px; background: url('{{ asset('site-preview/assets/f90a774f9a601cdd.svg') }}') center / 34px no-repeat; }
        .brand span { color: #fff; font-weight: 600; font-size: 14px; opacity: 0; transition: opacity .15s; }
        .side nav { flex: 1; padding: 12px 0; overflow-y: auto; overflow-x: hidden; }
        .side h6 { margin: 18px 0 6px; padding: 0 26px; height: 14px; font-size: 10.5px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: #4c4e6f; white-space: nowrap; }
        .side h6::before { content: ''; display: block; width: 20px; height: 1px; background: #33334a; margin-top: 7px; }
        .side .item { display: flex; align-items: center; gap: 16px; height: 44px; padding: 0 25px; color: #a2a3b7; white-space: nowrap; font-size: 13px; font-weight: 500; border: 0; background: none; width: 100%; cursor: pointer; font-family: inherit; text-align: left; }
        .side .item svg { width: 22px; height: 22px; flex: 0 0 22px; fill: none; stroke: #494b74; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .side .item span { opacity: 0; transition: opacity .15s; }
        .side .item:hover { background: #1b1b28; color: #fff; }
        .side .item:hover svg { stroke: var(--blue); }
        .side .item.on { background: #1b1b28; color: #fff; }
        .side .item.on svg { stroke: var(--blue); }
        .side:hover .brand span, .side:hover .item span, .side:focus-within .brand span, .side:focus-within .item span { opacity: 1; }
        .side:hover h6::before, .side:focus-within h6::before { display: none; }
        .side h6 b { font-weight: inherit; display: none; }
        .side:hover h6 b, .side:focus-within h6 b { display: inline; }
        .side form { margin: 0; padding: 8px 0 14px; border-top: 1px solid #2b2b40; }

        /* top bar */
        .main { margin-left: var(--side); min-height: 100vh; display: flex; flex-direction: column; }
        .top { position: sticky; top: 0; z-index: 40; height: 64px; background: #fff; display: flex; align-items: center; gap: 24px; padding: 0 28px; box-shadow: 0 0 20px rgba(82,63,105,.05); }
        .top h1 { margin: 0; font-size: 17px; font-weight: 600; color: #181c32; white-space: nowrap; min-width: 160px; }
        .top .search { flex: 1; display: flex; justify-content: center; }
        .top .search form { position: relative; width: 100%; max-width: 420px; margin: 0; }
        .top .search input { width: 100%; height: 40px; border: 0; border-radius: 8px; background: #f3f6f9; padding: 0 14px 0 40px; font: inherit; color: var(--ink); outline: none; }
        .top .search svg { position: absolute; left: 13px; top: 11px; width: 18px; height: 18px; fill: none; stroke: #b5b5c3; stroke-width: 2; }
        .top .spacer { flex: 1; }
        .top .me { display: flex; align-items: center; gap: 10px; white-space: nowrap; color: #7e8299; font-weight: 500; }
        .top .me b { color: #3f4254; font-weight: 600; }
        .top .me-wrap { position: relative; }
        .top a.me { text-decoration: none; padding: 5px 6px 5px 12px; border-radius: 24px; transition: background .15s; }
        .top a.me:hover, .top a.me:focus, .top a.me.on, .top .me-wrap:focus-within a.me { background: #f3f6f9; }
        .top a.me:hover b { color: var(--blue); }
        .top .me-menu { display: none; position: absolute; right: 0; top: 100%; padding-top: 6px; min-width: 170px; z-index: 50; }
        .top .me-wrap:hover .me-menu, .top .me-wrap:focus-within .me-menu { display: block; }
        .top .me-menu a, .top .me-menu button { display: block; width: 100%; text-align: left; padding: 10px 16px; background: #fff; border: 0; font: inherit; font-weight: 500; color: #3f4254; cursor: pointer; text-decoration: none; }
        .top .me-menu a { border-radius: 8px 8px 0 0; box-shadow: 0 6px 20px rgba(82,63,105,.12); }
        .top .me-menu form { margin: 0; }
        .top .me-menu button { border-radius: 0 0 8px 8px; box-shadow: 0 10px 20px rgba(82,63,105,.12); }
        .top .me-menu a:hover, .top .me-menu button:hover { background: #f3f6f9; color: var(--blue); }
        .site-sw { position: relative; }
        .site-sw .site-btn { display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 12px; border: 1px solid #e4e6ef; border-radius: 8px; background: #fff; font: inherit; color: #3f4254; cursor: pointer; white-space: nowrap; max-width: 260px; }
        .site-sw .site-btn:hover, .site-sw.open .site-btn { border-color: var(--blue); background: #f5f9ff; }
        .site-sw .site-btn span { color: #b5b5c3; font-size: 11.5px; font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
        .site-sw .site-btn b { overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
        .site-sw .site-btn svg { width: 14px; height: 14px; fill: none; stroke: #7e8299; stroke-width: 2; flex: 0 0 auto; }
        .site-pop { position: absolute; left: 0; top: calc(100% + 6px); width: 340px; background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(82,63,105,.18); padding: 10px; z-index: 60; }
        .site-pop input { width: 100%; height: 38px; border: 1px solid #e4e6ef; border-radius: 6px; padding: 0 12px; font: inherit; }
        .site-list { max-height: 320px; overflow-y: auto; margin-top: 8px; }
        .site-list button { all: unset; box-sizing: border-box; display: block; width: 100%; padding: 8px 10px; border-radius: 6px; cursor: pointer; }
        .site-list button:hover, .site-list button.hi { background: #f3f6f9; }
        .site-list b { display: block; color: #181c32; font-weight: 600; font-size: 13px; }
        .site-list small { color: #7e8299; font-size: 11.5px; }
        .site-list .none { color: #b5b5c3; padding: 8px 10px; }
        .site-pop .manage { display: block; border-top: 1px solid var(--line); margin-top: 8px; padding: 9px 10px 2px; color: var(--blue); font-weight: 600; font-size: 12.5px; }
        .top .avatar { width: 38px; height: 38px; border-radius: 50%; background: #c9f7f5; color: #1bc5bd; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px; text-transform: uppercase; }

        /* page content */
        .body { padding: 26px 28px 40px; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 0 30px rgba(82,63,105,.05); margin-bottom: 24px; }
        .card-head { display: flex; align-items: center; gap: 16px; min-height: 64px; padding: 0 26px; border-bottom: 1px solid var(--line); flex-wrap: wrap; }
        .card-head h2 { margin: 0; font-size: 15px; font-weight: 600; color: #181c32; }
        .card-head .sub { color: var(--muted); font-size: 12px; font-weight: 500; }
        .card-head .right { margin-left: auto; display: flex; gap: 8px; align-items: center; }
        .card-body { padding: 22px 26px; }
        .tabs { display: flex; gap: 26px; align-self: stretch; overflow-x: auto; }
        .tabs a, .tabs button { display: flex; align-items: center; color: #7e8299; font-weight: 500; font-size: 13px; border: 0; background: none; border-bottom: 2px solid transparent; padding: 0; cursor: pointer; font-family: inherit; white-space: nowrap; }
        .tabs a:hover, .tabs button:hover { color: var(--blue); }
        .tabs .on { color: var(--blue); border-bottom-color: var(--blue); }

        table.tbl { width: 100%; border-collapse: collapse; }
        table.tbl th { text-align: left; font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: #b5b5c3; padding: 12px 10px; border-bottom: 1px solid var(--line); white-space: nowrap; }
        table.tbl td { padding: 12px 10px; border-bottom: 1px solid var(--line); vertical-align: middle; color: #3f4254; }
        table.tbl tr.filters td { padding: 8px 10px; background: #fff; }
        table.tbl tr.filters input, table.tbl tr.filters select { width: 100%; height: 32px; border: 1px solid #e4e6ef; border-radius: 6px; padding: 0 9px; font: inherit; font-size: 12px; background: #fff; }
        table.tbl tbody tr.row:nth-child(even) td { background: #f9fafc; }
        table.tbl td.primary, table.tbl td.primary a { color: var(--blue); font-weight: 600; }
        table.tbl td.num { text-align: right; white-space: nowrap; }
        table.tbl th.num { text-align: right; }
        table.tbl td.acts { white-space: nowrap; text-align: right; }
        .pill { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 11.5px; font-weight: 600; background: #c9f7f5; color: #1bc5bd; }
        .pill.blue { background: #e1f0ff; color: var(--blue); }
        .pill.orange { background: #fff4de; color: #ffa800; }
        .pill.grey { background: #f3f6f9; color: #7e8299; }
        .pill.purple { background: #eee5ff; color: #8950fc; }
        .pill.red { background: #ffe2e5; color: #f64e60; }
        .act { display: inline-flex; width: 32px; height: 32px; border-radius: 6px; align-items: center; justify-content: center; background: #e1f0ff; color: var(--blue); border: 0; cursor: pointer; vertical-align: middle; }
        .act svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .act.green { background: #c9f7f5; color: #1bc5bd; }
        .act.purple { background: #eee5ff; color: #8950fc; }
        .act:hover { filter: brightness(.95); }

        .btn { display: inline-flex; align-items: center; gap: 6px; height: 38px; padding: 0 18px; border-radius: 6px; border: 0; background: var(--blue); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #187de4; }
        .btn.light { background: #e1f0ff; color: var(--blue); }
        .btn:disabled { background: #b5d7ff; cursor: default; }
        label { display: block; font-weight: 500; font-size: 13px; margin-bottom: 6px; color: #3f4254; }
        .field { margin-bottom: 16px; }
        .input, input[type=text], input[type=email], select { height: 40px; border: 1px solid #e4e6ef; border-radius: 6px; padding: 0 12px; font: inherit; color: var(--ink); background: #fff; }
        input[type=file] { width: 100%; padding: 10px; border: 1px dashed #b5b5c3; border-radius: 6px; font: inherit; }
        .ok { background: #c9f7f5; color: #0b8a83; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; }
        .error { background: #ffe2e5; color: #d1213d; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-weight: 500; }
        .muted { color: #b5b5c3; }
        code { background: #f3f6f9; padding: 2px 6px; border-radius: 4px; }
        pre { background: #f3f6f9; padding: 14px; border-radius: 8px; overflow-x: auto; font-size: 12px; }
        .empty { color: #b5b5c3; text-align: center; padding: 28px !important; }
    </style>
    @stack('head')
</head>
<body>
<aside class="side" aria-label="Main menu">
    <div class="brand"><div class="logo" role="img" aria-label="Tzivos Hashem"></div><span>Resources Admin</span></div>
    <nav>
        @foreach ($nav as $heading => $items)
            <h6><b>{{ $heading }}</b></h6>
            @foreach ($items as [$label, $url, $icon, $on])
                <a class="item @if ($on) on @endif" href="{{ $url }}" title="{{ $label }}"><svg viewBox="0 0 24 24">{!! $icons[$icon] !!}</svg><span>{{ $label }}</span></a>
            @endforeach
        @endforeach
    </nav>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="item" title="Sign out"><svg viewBox="0 0 24 24">{!! $icons['logout'] !!}</svg><span>Sign out</span></button>
    </form>
</aside>
<div class="main">
    <header class="top">
        <h1>@yield('page-title', 'Resources Admin')</h1>
        @if ($showSwitcher)
            <div class="site-sw" id="siteSw">
                <button type="button" class="site-btn" aria-haspopup="listbox" aria-expanded="false" title="Switch site"><span>Site</span><b id="siteName">{{ $siteNow->name }}</b><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></button>
                <div class="site-pop" hidden>
                    <input type="search" placeholder="Find a site by name or address…" aria-label="Find a site" autocomplete="off">
                    <div class="site-list" role="listbox"></div>
                    @if ($u->isHQAdmin())<a class="manage" href="{{ route('admin.sites') }}">Manage sites</a>@endif
                </div>
                <form method="POST" action="{{ route('admin.sites.switch') }}" hidden>@csrf<input type="hidden" name="site"></form>
            </div>
        @endif
        @if ($u->canEditPages())
            <div class="search">
                <form method="GET" action="{{ route('admin.pages.search') }}" role="search">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                    <input type="search" name="q" value="{{ request()->routeIs('admin.pages.search') ? request('q') : '' }}" placeholder="Search site pages…" aria-label="Search site pages">
                </form>
            </div>
        @else
            <div class="spacer"></div>
        @endif
        <div class="me-wrap">
            <a class="me @if (request()->routeIs('account')) on @endif" href="{{ route('account') }}" title="My dashboard">Hi, <b>{{ $u->name }}</b><span class="avatar">{{ mb_substr($u->name ?: $u->email, 0, 1) }}</span></a>
            <div class="me-menu">
                <a href="{{ route('account') }}">My dashboard</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
            </div>
        </div>
    </header>
    <main class="body">
        @yield('content')
    </main>
</div>
<script>
// Filter rows of any table that has a filter row: each box matches the text in its column
document.querySelectorAll('table.tbl').forEach(function (t) {
    var boxes = t.querySelectorAll('tr.filters [data-col]');
    if (!boxes.length) return;
    function run() {
        t.querySelectorAll('tbody tr.row').forEach(function (r) {
            var show = true;
            boxes.forEach(function (b) {
                var v = b.value.trim().toLowerCase();
                var cell = (r.cells[+b.dataset.col].textContent || '').toLowerCase();
                if (v && (b.dataset.exact ? cell.trim() !== v : cell.indexOf(v) < 0)) show = false;
            });
            r.hidden = !show;
        });
    }
    boxes.forEach(function (b) { b.addEventListener('input', run); b.addEventListener('change', run); });
});
</script>
@if ($showSwitcher)
<script>
// Site switcher: asks the server for matching sites as you type (only a few at a time)
(function () {
    var box = document.getElementById('siteSw'); if (!box) return;
    var btn = box.querySelector('.site-btn'), pop = box.querySelector('.site-pop'), input = pop.querySelector('input'), list = pop.querySelector('.site-list'), form = box.querySelector('form');
    var timer = null, seq = 0, items = [], hi = 0;
    function open(on) { pop.hidden = !on; box.classList.toggle('open', on); btn.setAttribute('aria-expanded', on); if (on) { input.value = ''; load(); input.focus(); } }
    function pick(slug) { form.querySelector('[name=site]').value = slug; form.submit(); }
    function paint() { list.querySelectorAll('button').forEach(function (b, i) { b.classList.toggle('hi', i === hi); }); }
    function load() {
        var my = ++seq;
        fetch(@json(route('admin.sites.search')) + '?q=' + encodeURIComponent(input.value), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); }).then(function (rows) {
                if (my !== seq) return;
                items = rows; hi = 0; list.innerHTML = '';
                if (!rows.length) { list.innerHTML = '<div class="none">No sites match.</div>'; return; }
                rows.forEach(function (s) {
                    var b = document.createElement('button'); b.type = 'button'; b.setAttribute('role', 'option'); b.dataset.slug = s.slug;
                    var n = document.createElement('b'); n.textContent = s.name + (s.current ? '  ✓' : '');
                    var m = document.createElement('small'); m.textContent = (s.address ? s.address + ' · ' : '') + s.status;
                    b.appendChild(n); b.appendChild(m);
                    b.addEventListener('click', function () { pick(s.slug); });
                    list.appendChild(b);
                });
                paint();
            });
    }
    btn.addEventListener('click', function () { open(pop.hidden); });
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(load, 150); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { open(false); btn.focus(); }
        else if (e.key === 'ArrowDown') { e.preventDefault(); hi = Math.min(items.length - 1, hi + 1); paint(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); hi = Math.max(0, hi - 1); paint(); }
        else if (e.key === 'Enter') { e.preventDefault(); if (items[hi]) pick(items[hi].slug); }
    });
    document.addEventListener('click', function (e) { if (!box.contains(e.target)) open(false); });
})();
</script>
@endif
@stack('scripts')
</body>
</html>
