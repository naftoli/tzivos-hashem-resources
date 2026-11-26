@extends('layouts.admin')

@section('title', 'Files | Resources Admin')
@section('page-title', 'Files')

@push('head')
    <style>
        :root { --fx-navy: #151828; --fx-ink: #181c32; --fx-accent: #6366f1; --fx-accent-soft: #eef0ff; --fx-on: #14b8a6; --fx-on-soft: #e6f8f5; --fx-on-ink: #0f766e; --fx-shadow: 0 1px 2px rgba(21,24,40,.04), 0 6px 20px rgba(21,24,40,.05); }
        @include('admin.files._access_css')
        @include('admin.files._switches_css')

        /* hero */
        .fx-hero { position: relative; overflow: hidden; border-radius: 16px; padding: 26px 28px 22px; margin-bottom: 18px; color: #fff; background: radial-gradient(circle at 85% -20%, rgba(99,102,241,.55) 0%, rgba(99,102,241,0) 55%), radial-gradient(circle at 105% 120%, rgba(20,184,166,.35) 0%, rgba(20,184,166,0) 50%), linear-gradient(135deg, #151828 0%, #1f2340 60%, #262b52 100%); box-shadow: 0 10px 30px rgba(21,24,40,.18); }
        .fx-hero-top { display: flex; align-items: center; gap: 16px; }
        .fx-hero-icon { display: flex; align-items: center; justify-content: center; width: 52px; height: 52px; border-radius: 14px; background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.16); flex-shrink: 0; }
        .fx-hero-icon svg { width: 26px; height: 26px; }
        .fx-hero h1 { margin: 0; font-size: 22px; font-weight: 600; letter-spacing: .01em; }
        .fx-hero-lead { margin: 4px 0 0; color: #c3c7e6; font-size: 13px; line-height: 1.55; max-width: 860px; }
        .fx-help { margin-top: 16px; border-top: 1px solid rgba(255,255,255,.12); padding-top: 12px; }
        .fx-help > summary { list-style: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 12.5px; color: #e0e2ff; padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,.08); user-select: none; transition: background .15s; }
        .fx-help > summary::-webkit-details-marker { display: none; }
        .fx-help > summary:hover { background: rgba(255,255,255,.14); }
        .fx-help > summary svg { width: 15px; height: 15px; transition: transform .2s; }
        .fx-help[open] > summary svg { transform: rotate(180deg); }
        .fx-help-body { margin-top: 12px; display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .fx-help-body p { margin: 0; padding: 12px 14px; border-radius: 10px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.08); color: #d4d7f0; line-height: 1.55; font-size: 12.5px; }
        .fx-help-body b { color: #fff; }

        /* tabs */
        .fx-tabs { display: inline-flex; gap: 4px; padding: 5px; margin-bottom: 18px; border-radius: 14px; background: #fff; box-shadow: var(--fx-shadow); border: 1px solid #eceef5; max-width: 100%; overflow-x: auto; }
        .fx-tab { display: inline-flex; align-items: center; gap: 10px; height: 42px; padding: 0 18px; border: 0; border-radius: 10px; background: transparent; color: #5e6278; font: inherit; font-weight: 600; font-size: 13.5px; cursor: pointer; white-space: nowrap; transition: background .15s, color .15s; }
        .fx-tab svg { width: 17px; height: 17px; }
        .fx-tab:hover { color: var(--fx-ink); background: #f4f5fa; }
        .fx-tab[aria-selected="true"] { background: var(--fx-navy); color: #fff; box-shadow: 0 4px 12px rgba(21,24,40,.2); }
        .fx-badge { display: inline-flex; align-items: center; height: 20px; padding: 0 8px; border-radius: 999px; background: #eef0f6; color: #5e6278; font-size: 11px; font-weight: 600; }
        .fx-tab[aria-selected="true"] .fx-badge { background: rgba(255,255,255,.16); color: #fff; }
        .fx-panel[hidden] { display: none; }

        .fx-section-head { display: flex; align-items: center; gap: 12px; margin: 0 0 14px; }
        .fx-section-head h2 { margin: 0; font-size: 16px; font-weight: 600; color: var(--fx-ink); }
        .fx-section-head .fx-badge { margin-left: 2px; }
        .fx-section { margin-top: 30px; }

        /* status pill with dot */
        .fx-pill { display: inline-flex; align-items: center; gap: 6px; height: 22px; padding: 0 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
        .fx-pill::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
        .fx-pill.everyone { background: var(--fx-on-soft); color: var(--fx-on-ink); }
        .fx-pill.limited { background: #fff4de; color: #b26b00; }
        .fx-pill.muted { background: #f1f2f6; color: #7e8299; }
        .fx-pill.removed { background: #fdecec; color: #c62828; }

        /* remove / restore */
        .fx-rm-form { display: inline-flex; margin: 0; }
        .fx-rm { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 8px; border: 1px solid #f3c1c1; background: #fff; color: #d14343; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; transition: background .15s, border-color .15s, color .15s; }
        .fx-rm svg { width: 14px; height: 14px; }
        .fx-rm:hover { background: #fdecec; border-color: #e5484d; color: #b42318; }
        .fx-rm.restore { border-color: #b7e4dd; color: var(--fx-on-ink); }
        .fx-rm.restore:hover { background: var(--fx-on-soft); border-color: var(--fx-on); color: var(--fx-on-ink); }
        .fx-item.is-removed { border-color: #f3c1c1; }
        .fx-item.is-removed .fx-item-body { opacity: .55; }

        /* issue / feature cards */
        .fx-list { display: flex; flex-direction: column; gap: 16px; }
        .fx-grid2 { display: grid; grid-template-columns: repeat(auto-fill, minmax(460px, 1fr)); gap: 16px; }
        .fx-item { background: #fff; border-radius: 14px; border: 1px solid #eceef5; box-shadow: var(--fx-shadow); transition: box-shadow .15s, border-color .15s; }
        .fx-item:hover { box-shadow: 0 10px 28px rgba(21,24,40,.08); border-color: #e0e3f0; }
        .fx-item-head { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 16px 20px; border-bottom: 1px solid #f0f1f6; }
        .fx-num { display: inline-flex; align-items: center; justify-content: center; min-width: 54px; height: 40px; padding: 0 10px; border-radius: 10px; background: var(--fx-navy); color: #fff; font-weight: 700; font-size: 14px; letter-spacing: .02em; flex-shrink: 0; }
        .fx-title { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .fx-title .fname { font-size: 15px; font-weight: 600; color: var(--fx-ink); }
        .fx-title .fsub { font-size: 12px; color: #7e8299; }
        .fx-meta { margin-left: auto; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .fx-chip { display: inline-flex; align-items: center; gap: 6px; height: 26px; padding: 0 10px; border-radius: 8px; background: #f4f5fa; border: 1px solid #eceef5; color: #5e6278; font-size: 12px; font-weight: 500; max-width: 100%; }
        .fx-chip svg { width: 14px; height: 14px; color: #e5484d; flex-shrink: 0; }
        .fx-chip code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fx-item-body { padding: 18px 20px 20px; margin: 0; }
        .fx-link { display: inline-flex; align-items: center; gap: 5px; font-weight: 600; font-size: 12.5px; color: var(--fx-accent); }
        .fx-link:hover { text-decoration: underline; }

        /* features in this Hachayol: drawer */
        details.weeks { border-top: 1px solid #f0f1f6; }
        details.weeks > summary { list-style: none; display: flex; align-items: center; gap: 10px; padding: 14px 20px; cursor: pointer; font-weight: 600; color: var(--fx-ink); user-select: none; border-radius: 0 0 14px 14px; transition: background .15s; }
        details.weeks > summary::-webkit-details-marker { display: none; }
        details.weeks > summary:hover { background: #f8f9fc; }
        details.weeks > summary .fx-caret { display: inline-flex; width: 24px; height: 24px; align-items: center; justify-content: center; border-radius: 7px; background: var(--fx-accent-soft); color: var(--fx-accent); transition: transform .2s; }
        details.weeks > summary .fx-caret svg { width: 14px; height: 14px; }
        details.weeks[open] > summary { border-radius: 0; border-bottom: 1px solid #f0f1f6; }
        details.weeks[open] > summary .fx-caret { transform: rotate(90deg); }
        .fparts { display: flex; flex-direction: column; gap: 12px; padding: 16px 20px 20px; background: linear-gradient(180deg, #f8f9fc, #f4f5fa); border-radius: 0 0 14px 14px; }
        .fparts .fx-item { box-shadow: none; border-color: #e7e9f2; }
        .fparts .fx-item-head { padding: 14px 18px; }
        .fparts .fx-item-body { padding: 14px 18px 18px; }
        .fparts .acc-group { background: #fff; }
        .fclear { padding: 0 18px 16px; margin: 0; }
        .act.blue-text { background: var(--fx-accent-soft); color: var(--fx-accent); width: auto; padding: 0 14px; font: inherit; font-size: 12px; font-weight: 600; border: 0; cursor: pointer; height: 32px; border-radius: 8px; }
        .act.blue-text:hover { background: #e0e3ff; }

        /* single files */
        .fx-search { display: flex; gap: 10px; max-width: 560px; }
        .fx-search .fx-input { flex: 1; display: flex; align-items: center; gap: 10px; height: 42px; padding: 0 14px; border-radius: 10px; border: 1px solid #e1e4ee; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .fx-search .fx-input:focus-within { border-color: var(--fx-accent); box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .fx-search .fx-input svg { width: 17px; height: 17px; color: #a1a5b7; flex-shrink: 0; }
        .fx-search input { flex: 1; border: 0; outline: 0; font: inherit; background: transparent; min-width: 0; }
        .fx-search .btn { height: 42px; border-radius: 10px; padding: 0 20px; background: var(--fx-navy); }
        .fx-search .btn:hover { background: #262b52; }
        .found { margin-top: 14px; }
        .found td { vertical-align: top; }
        .fname { font-weight: 600; color: var(--fx-ink); }
        .fx-acts { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .fsub { color: #7e8299; font-size: 12px; margin-top: 3px; }
        @media (max-width: 1100px) { .fx-grid2 { grid-template-columns: 1fr; } }
        @media (max-width: 700px) { .fx-meta { margin-left: 0; } .fx-hero { padding: 20px; } }
    </style>
@endpush

@php
    $svgDoc = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>';
    $svgCaret = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
@endphp

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <section class="fx-hero">
        <div class="fx-hero-top">
            <div class="fx-hero-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="2.2"/><path d="M7.5 13s1.8-3.2 4.5-3.2 4.5 3.2 4.5 3.2-1.8 3.2-4.5 3.2-4.5-3.2-4.5-3.2z"/></svg></div>
            <div>
                <h1>Files</h1>
                <p class="fx-hero-lead">Set who can see each Hachayol, each feature, and each single file on the site, and when. This only changes what the site shows. Remove takes a box off the site (Restore brings it back). No file is deleted from the server.</p>
            </div>
        </div>
        <details class="fx-help">
            <summary>How visibility rules work <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg></summary>
            <div class="fx-help-body">
                <p><b>Which setting wins:</b> a setting on a single file wins over everything. Next comes a feature in one week. Otherwise the whole Hachayol and the whole feature both count: a person must be allowed by both. HQ admin always sees everything.</p>
                <p><b>On / off:</b> Full and each feature also have a switch. Turning a switch off hides it on the site for everyone, without unticking who can see it. Full off hides every Hachayol and turns every feature off with it (Full on turns them all back on); a feature off hides that feature. Sections that are only in some weeks (like Tanya Baal Peh) have their own switch too. Choose weeks sets which weeks it shows.</p>
                <p>All levels ticked and no dates means everyone, at any time.</p>
            </div>
        </details>
    </section>

    <div class="fx-tabs" role="tablist">
        <button type="button" class="fx-tab" role="tab" data-tab="switches" aria-selected="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="10" rx="5"/><circle cx="16" cy="12" r="3"/></svg>
            Full and features <span class="fx-badge" id="swOnCount">{{ 1 + count($switches['features']) }}</span>
        </button>
        <button type="button" class="fx-tab" role="tab" data-tab="issues" aria-selected="false">
            {!! $svgDoc !!}
            Whole Hachayol <span class="fx-badge">{{ count($issues) }} issue(s)</span>
        </button>
    </div>

    <div class="fx-panel" data-panel="switches" role="tabpanel">
        @include('admin.files._switches')

        <div class="fx-section">
            <div class="fx-section-head"><h2>Whole feature (all weeks)</h2></div>
            <div class="fx-grid2">
            @forelse ($features as $key => $feature)
                @php $rule = $rules['feature:'.$key] ?? null; $gone = (bool) $rule?->removed; @endphp
                <div class="fx-item {{ $gone ? 'is-removed' : '' }}" id="feature-{{ $key }}">
                    <div class="fx-item-head">
                        <span class="sw-ico">{!! $svgDoc !!}</span>
                        <div class="fx-title"><span class="fname">{{ $feature['name'] }}</span><span class="fsub">{{ $feature['weeks'] }} week(s)</span></div>
                        <div class="fx-meta">
                            @if ($gone)<span class="fx-pill removed">Removed from the site</span>@elseif ($rule)<span class="fx-pill limited">Limited</span>@else<span class="fx-pill everyone">Everyone</span>@endif
                            @include('admin.files._remove', ['action' => route('admin.files.remove'), 'restore' => route('admin.files.restore'), 'removed' => $gone, 'name' => $feature['name'].' (all weeks)', 'fields' => ['scope' => 'feature', 'key' => $key]])
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.files.update') }}" class="fx-item-body">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="scope" value="feature">
                        <input type="hidden" name="key" value="{{ $key }}">
                        @include('admin.files._access', ['rule' => $rule, 'levels' => $levels, 'download' => false])
                    </form>
                </div>
            @empty
                <p class="empty">No features found on this site.</p>
            @endforelse
            </div>
        </div>
    </div>

    <div class="fx-panel" data-panel="issues" role="tabpanel" hidden>
        <div class="fx-section-head"><h2>Whole Hachayol</h2><span class="fx-badge">{{ count($issues) }} issue(s)</span></div>
        <div class="fx-list">
        @forelse ($issues as $issue)
            @php $rule = $rules['issue:'.$issue['num']] ?? null; $gone = (bool) $rule?->removed; @endphp
            <div class="fx-item {{ $gone ? 'is-removed' : '' }}" id="issue-{{ $issue['num'] }}">
                <div class="fx-item-head">
                    <span class="fx-num">#{{ $issue['num'] }}</span>
                    <div class="fx-title"><span class="fname">{{ $issue['label'] }}</span></div>
                    <div class="fx-meta">
                        @if ($gone)<span class="fx-pill removed">Removed from the site</span>@elseif ($rule)<span class="fx-pill limited">Limited</span>@else<span class="fx-pill everyone">Everyone</span>@endif
                        @if ($issue['bar'])<span class="fx-chip">{!! $svgDoc !!}<code>{{ rawurldecode($issue['bar']['href']) }}</code></span>@endif
                        @include('admin.files._remove', ['action' => route('admin.files.remove'), 'restore' => route('admin.files.restore'), 'removed' => $gone, 'name' => $issue['label'], 'fields' => ['scope' => 'issue', 'key' => $issue['num']]])
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.files.update') }}" class="fx-item-body">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="scope" value="issue">
                    <input type="hidden" name="key" value="{{ $issue['num'] }}">
                    @include('admin.files._access', ['rule' => $rule, 'levels' => $levels, 'download' => $issue['bar'] !== null])
                </form>
                @if ($issue['parts'])
                    <details class="weeks">
                        <summary><span class="fx-caret">{!! $svgCaret !!}</span> Features in this Hachayol <span class="fx-badge">{{ count($issue['parts']) }}</span></summary>
                        <div class="fparts">
                        @foreach ($issue['parts'] as $part)
                            @php $prule = $rules['part:'.$part['key']] ?? null; $pgone = (bool) $prule?->removed; $pname = $features[$part['feature']]['name'].' · '.$issue['label']; @endphp
                            <div class="fx-item {{ $pgone ? 'is-removed' : '' }}" id="part-{{ $part['key'] }}">
                                <div class="fx-item-head">
                                    <span class="sw-ico">{!! $svgDoc !!}</span>
                                    <div class="fx-title">
                                        <span class="fname">{{ $features[$part['feature']]['name'] }}</span>
                                        <span class="fsub">{{ $part['title'] }}</span>
                                    </div>
                                    <div class="fx-meta">
                                        @if ($pgone)<span class="fx-pill removed">Removed from the site</span>@elseif ($prule)<span class="fx-pill limited">Own setting</span>@else<span class="fx-pill muted">Uses the broader settings</span>@endif
                                        <a class="fx-link" href="{{ route('admin.pages.block.files', [$part['block']->page, $part['block']]) }}">Single files →</a>
                                        @include('admin.files._remove', ['action' => route('admin.files.remove'), 'restore' => route('admin.files.restore'), 'removed' => $pgone, 'name' => $pname, 'fields' => ['scope' => 'part', 'key' => $part['key']]])
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.files.update') }}" class="fx-item-body">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="scope" value="part">
                                    <input type="hidden" name="key" value="{{ $part['key'] }}">
                                    @include('admin.files._access', ['rule' => $prule, 'levels' => $levels, 'download' => false])
                                </form>
                                @if ($prule)
                                    <form method="POST" action="{{ route('admin.files.clear') }}" class="fclear">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="key" value="{{ $part['key'] }}">
                                        <button class="act blue-text" type="submit">Clear this setting</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                        </div>
                    </details>
                @endif
            </div>
        @empty
            <p class="empty">No Hachayol found on this site.</p>
        @endforelse
        </div>
    </div>

    <div class="fx-section" id="single">
        <div class="fx-item">
            <div class="fx-item-head">
                <span class="sw-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg></span>
                <div class="fx-title"><span class="fname">Single files</span><span class="fsub">Every part of the site also has its own list: Site pages › a part › Files.</span></div>
            </div>
            <div class="fx-item-body">
                <form method="GET" action="{{ route('admin.files') }}#single" class="fx-search">
                    <label class="fx-input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="text" name="q" value="{{ $q }}" placeholder="File name or picture"></label>
                    <button class="btn" type="submit">Find</button>
                </form>
                @if ($q !== '')
                    <table class="tbl found">
                        <thead><tr><th>File</th><th>Part</th><th class="num"></th></tr></thead>
                        <tbody>
                        @forelse ($found as $row)
                            <tr class="row">
                                <td><div class="fname">{{ $row['file']['label'] !== '' ? $row['file']['label'] : basename($row['file']['path']) }}</div><div class="fsub">{{ $row['file']['kind'] === 'image' ? 'Picture' : $row['file']['path'] }}</div></td>
                                <td>{{ $row['block']->page->slug }} / {{ $row['block']->block_key }}</td>
                                <td class="acts"><div class="fx-acts">
                                    @if ($row['removed'])<span class="fx-pill removed">Removed</span>@endif
                                    <a class="btn light" href="{{ route('admin.pages.block.files', [$row['block']->page, $row['block']]) }}">Open</a>
                                    @include('admin.files._remove', ['action' => route('admin.pages.block.files.remove', [$row['block']->page, $row['block']]), 'restore' => route('admin.pages.block.files.restore', [$row['block']->page, $row['block']]), 'removed' => $row['removed'], 'name' => $row['file']['label'] !== '' ? $row['file']['label'] : basename($row['file']['path']), 'fields' => ['ref' => $row['file']['ref']]])
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Nothing found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <script>
    (function () {
        // Remove: ask first, so nobody removes by accident
        document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

        // Tabs: Full and features / Whole Hachayol. Stays on the same tab after Save (and on a link to something inside a tab).
        const tabs = document.querySelectorAll('.fx-tab');
        const panels = document.querySelectorAll('.fx-panel');
        function pick(name) {
            tabs.forEach(t => t.setAttribute('aria-selected', t.dataset.tab === name ? 'true' : 'false'));
            panels.forEach(p => p.hidden = p.dataset.panel !== name);
            try { localStorage.setItem('filesTab', name); } catch (e) {}
        }
        tabs.forEach(t => t.addEventListener('click', () => pick(t.dataset.tab)));
        panels.forEach(p => p.querySelectorAll('form').forEach(f => f.addEventListener('submit', () => { try { localStorage.setItem('filesTab', p.dataset.panel); } catch (e) {} })));
        let start = null;
        const target = location.hash.length > 1 ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
        if (target) {
            const panel = target.closest('.fx-panel');
            if (panel) start = panel.dataset.panel;
            const drawer = target.closest('details');
            if (drawer) drawer.open = true;
        }
        if (!start) { try { start = localStorage.getItem('filesTab'); } catch (e) {} }
        pick(start === 'issues' ? 'issues' : 'switches');
        if (target) target.scrollIntoView({ block: 'start' });
    })();
    </script>
@endsection
