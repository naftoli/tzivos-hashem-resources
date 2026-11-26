@extends('layouts.admin')

@section('title', 'Files | Resources Admin')
@section('page-title', 'Files')

@push('head')
    <style>
        @include('admin.files._access_css')
        @include('admin.files._switches_css')
        .help { background: #fff; border-left: 4px solid var(--blue); border-radius: 10px; padding: 16px 22px; margin-bottom: 24px; color: #5e6278; line-height: 1.6; box-shadow: 0 0 30px rgba(82,63,105,.05); }
        .help p { margin: 4px 0; }
        .help b { color: #181c32; }
        .card-head .htitle { display: flex; flex-direction: column; gap: 2px; padding: 14px 0; }
        .flist { display: flex; flex-direction: column; gap: 14px; }
        .fitem { border: 1px solid var(--line); border-radius: 10px; background: #fff; transition: box-shadow .15s, border-color .15s; }
        .fitem:hover { border-color: #dfe3ee; box-shadow: 0 4px 18px rgba(82,63,105,.06); }
        .fitem-head { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding: 14px 18px; border-bottom: 1px solid var(--line); background: #fafbfd; border-radius: 10px 10px 0 0; }
        .fitem-head .fname { font-size: 14px; }
        .fitem-head .fmeta { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-left: auto; color: #7e8299; font-size: 12px; }
        .fitem-head .fmeta code { background: #f3f6f9; border-radius: 5px; padding: 2px 7px; font-size: 11.5px; color: #5e6278; word-break: break-all; }
        .fitem-body { padding: 16px 18px; }
        .fname { font-weight: 600; color: #181c32; }
        .fsub { color: #7e8299; font-size: 12px; margin-top: 3px; }
        details.weeks { border-top: 1px solid var(--line); }
        details.weeks > summary { list-style: none; display: flex; align-items: center; gap: 8px; padding: 12px 18px; cursor: pointer; font-weight: 600; color: var(--blue); user-select: none; }
        details.weeks > summary::-webkit-details-marker { display: none; }
        details.weeks > summary::before { content: '›'; display: inline-block; font-size: 18px; line-height: 1; transition: transform .15s; }
        details.weeks[open] > summary::before { transform: rotate(90deg); }
        details.weeks > summary:hover { background: #f8faff; }
        details.weeks[open] > summary { border-bottom: 1px solid var(--line); }
        .fparts { display: flex; flex-direction: column; gap: 12px; padding: 14px 18px 18px; background: #fafbfd; border-radius: 0 0 10px 10px; }
        .fparts .fitem-head { background: #fff; }
        .fclear { padding: 0 18px 14px; }
        .found td { vertical-align: top; }
        .act.blue-text { background: #e1f0ff; color: var(--blue); width: auto; padding: 0 12px; font: inherit; font-size: 12px; font-weight: 600; border: 0; cursor: pointer; height: 30px; border-radius: 6px; }
        .act.blue-text:hover { background: #cfe6ff; }
        .fsearch { display: flex; gap: 8px; max-width: 520px; }
        .fsearch input { flex: 1; height: 38px; padding: 0 12px; border: 1px solid #e4e6ef; border-radius: 6px; font: inherit; }
        .fsearch input:focus { outline: 0; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(54,153,255,.15); }
        @media (max-width: 760px) {
            .fitem-head .fmeta { margin-left: 0; }
        }
    </style>
@endpush

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="help">
        <p>Set who can see each Hachayol, each feature, and each single file on the site, and when. This only changes what the site shows. No file is deleted from the server.</p>
        <p><b>Which setting wins:</b> a setting on a single file wins over everything. Next comes a feature in one week. Otherwise the whole Hachayol and the whole feature both count: a person must be allowed by both. HQ admin always sees everything.</p>
        <p><b>On / off:</b> Full and each feature also have a switch. Turning a switch off hides it on the site for everyone, without unticking who can see it. Full off hides every Hachayol; a feature off hides that feature. Choose weeks sets which weeks it shows.</p>
        <p>All levels ticked and no dates means everyone, at any time.</p>
    </div>

    @include('admin.files._switches')

    <div class="card">
        <div class="card-head"><h2>Whole Hachayol</h2><div class="right"><span class="sub">{{ count($issues) }} issue(s)</span></div></div>
        <div class="card-body">
            <div class="flist">
            @forelse ($issues as $issue)
                @php $rule = $rules['issue:'.$issue['num']] ?? null; @endphp
                <div class="fitem" id="issue-{{ $issue['num'] }}">
                    <div class="fitem-head">
                        <span class="fname">{{ $issue['label'] }}</span>
                        @if ($rule)<span class="pill orange">Limited</span>@else<span class="pill">Everyone</span>@endif
                        @if ($issue['bar'])<span class="fmeta">PDF: <code>{{ rawurldecode($issue['bar']['href']) }}</code></span>@endif
                    </div>
                    <form method="POST" action="{{ route('admin.files.update') }}" class="fitem-body" style="margin:0">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="scope" value="issue">
                        <input type="hidden" name="key" value="{{ $issue['num'] }}">
                        @include('admin.files._access', ['rule' => $rule, 'levels' => $levels, 'download' => $issue['bar'] !== null])
                    </form>
                    @if ($issue['parts'])
                        <details class="weeks">
                            <summary>Features in this Hachayol ({{ count($issue['parts']) }})</summary>
                            <div class="fparts">
                            @foreach ($issue['parts'] as $part)
                                @php $prule = $rules['part:'.$part['key']] ?? null; @endphp
                                <div class="fitem" id="part-{{ $part['key'] }}">
                                    <div class="fitem-head">
                                        <div>
                                            <div class="fname">{{ $features[$part['feature']]['name'] }}</div>
                                            <div class="fsub">{{ $part['title'] }}</div>
                                        </div>
                                        <span class="fmeta">
                                            @if ($prule)<span class="pill orange">Own setting</span>@else<span class="pill grey">Uses the broader settings</span>@endif
                                            <a href="{{ route('admin.pages.block.files', [$part['block']->page, $part['block']]) }}">Single files</a>
                                        </span>
                                    </div>
                                    <form method="POST" action="{{ route('admin.files.update') }}" class="fitem-body" style="margin:0">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="scope" value="part">
                                        <input type="hidden" name="key" value="{{ $part['key'] }}">
                                        @include('admin.files._access', ['rule' => $prule, 'levels' => $levels, 'download' => false])
                                    </form>
                                    @if ($prule)
                                        <form method="POST" action="{{ route('admin.files.clear') }}" class="fclear" style="margin:0">
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
    </div>

    <div class="card">
        <div class="card-head"><h2>Whole feature (all weeks)</h2></div>
        <div class="card-body">
            <div class="flist">
            @forelse ($features as $key => $feature)
                @php $rule = $rules['feature:'.$key] ?? null; @endphp
                <div class="fitem" id="feature-{{ $key }}">
                    <div class="fitem-head">
                        <span class="fname">{{ $feature['name'] }}</span>
                        @if ($rule)<span class="pill orange">Limited</span>@else<span class="pill">Everyone</span>@endif
                        <span class="fmeta">{{ $feature['weeks'] }} week(s)</span>
                    </div>
                    <form method="POST" action="{{ route('admin.files.update') }}" class="fitem-body" style="margin:0">
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

    <div class="card" id="single">
        <div class="card-head"><h2>Single files</h2></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.files') }}#single" class="fsearch">
                <input type="text" name="q" value="{{ $q }}" placeholder="File name or picture">
                <button class="btn light" type="submit">Find</button>
            </form>
            <p class="fsub">Every part of the site also has its own list: Site pages › a part › Files.</p>
            @if ($q !== '')
                <table class="tbl found" style="margin-top:10px">
                    <thead><tr><th>File</th><th>Part</th><th class="num"></th></tr></thead>
                    <tbody>
                    @forelse ($found as $row)
                        <tr class="row">
                            <td><div class="fname">{{ $row['file']['label'] !== '' ? $row['file']['label'] : basename($row['file']['path']) }}</div><div class="fsub">{{ $row['file']['kind'] === 'image' ? 'Picture' : $row['file']['path'] }}</div></td>
                            <td>{{ $row['block']->page->slug }} / {{ $row['block']->block_key }}</td>
                            <td class="acts"><a class="btn light" href="{{ route('admin.pages.block.files', [$row['block']->page, $row['block']]) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="empty">Nothing found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
