@extends('layouts.admin')

@section('title', 'Files | Resources Admin')
@section('page-title', 'Files')

@push('head')
    <style>
        @include('admin.files._access_css')
        .help { background: #f3f6f9; border-radius: 8px; padding: 12px 16px; margin-bottom: 18px; color: #5e6278; }
        .help p { margin: 4px 0; }
        .frow { display: grid; grid-template-columns: minmax(220px, 300px) 1fr; gap: 14px; padding: 14px 0; border-top: 1px solid var(--line); }
        .frow:first-child { border-top: 0; }
        .fname { font-weight: 600; color: #181c32; }
        .fsub { color: #7e8299; font-size: 12px; margin-top: 3px; }
        details.weeks { grid-column: 1 / -1; margin: 0 0 0 18px; }
        details.weeks summary { cursor: pointer; font-weight: 600; color: var(--blue); }
        details.weeks .frow { grid-template-columns: minmax(200px, 282px) 1fr; }
        .found td { vertical-align: top; }
        .act.blue-text { background: #e1f0ff; color: var(--blue); width: auto; padding: 0 10px; font: inherit; font-size: 12px; font-weight: 600; border: 0; cursor: pointer; height: 28px; border-radius: 6px; }
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
        <p>All levels ticked and no dates means everyone, at any time.</p>
    </div>

    <div class="card">
        <div class="card-head"><h2>Whole Hachayol</h2><div class="right"><span class="sub">{{ count($issues) }} issue(s)</span></div></div>
        <div class="card-body" style="padding-top:0">
            @forelse ($issues as $issue)
                @php $rule = $rules['issue:'.$issue['num']] ?? null; @endphp
                <div class="frow" id="issue-{{ $issue['num'] }}">
                    <div>
                        <div class="fname">{{ $issue['label'] }}</div>
                        <div class="fsub">
                            @if ($rule)<span class="pill orange">Limited</span>@else<span class="pill">Everyone</span>@endif
                            @if ($issue['bar']) · PDF: {{ rawurldecode($issue['bar']['href']) }} @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.files.update') }}" style="margin:0">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="scope" value="issue">
                        <input type="hidden" name="key" value="{{ $issue['num'] }}">
                        @include('admin.files._access', ['rule' => $rule, 'levels' => $levels, 'download' => $issue['bar'] !== null])
                    </form>
                    @if ($issue['parts'])
                        <details class="weeks">
                            <summary>Features in this Hachayol ({{ count($issue['parts']) }})</summary>
                            @foreach ($issue['parts'] as $part)
                                @php $prule = $rules['part:'.$part['key']] ?? null; @endphp
                                <div class="frow" id="part-{{ $part['key'] }}">
                                    <div>
                                        <div class="fname">{{ $features[$part['feature']]['name'] }}</div>
                                        <div class="fsub">{{ $part['title'] }}</div>
                                        <div class="fsub">
                                            @if ($prule)<span class="pill orange">Own setting</span>@else<span class="pill grey">Uses the broader settings</span>@endif
                                            · <a href="{{ route('admin.pages.block.files', [$part['block']->page, $part['block']]) }}">Single files</a>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('admin.files.update') }}" style="margin:0">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="scope" value="part">
                                        <input type="hidden" name="key" value="{{ $part['key'] }}">
                                        @include('admin.files._access', ['rule' => $prule, 'levels' => $levels, 'download' => false])
                                    </form>
                                    @if ($prule)
                                        <form method="POST" action="{{ route('admin.files.clear') }}" style="margin:0;grid-column:2">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="key" value="{{ $part['key'] }}">
                                            <button class="act blue-text" type="submit">Clear this setting</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </details>
                    @endif
                </div>
            @empty
                <p class="empty">No Hachayol found on this site.</p>
            @endforelse
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-head"><h2>Whole feature (all weeks)</h2></div>
        <div class="card-body" style="padding-top:0">
            @forelse ($features as $key => $feature)
                @php $rule = $rules['feature:'.$key] ?? null; @endphp
                <div class="frow" id="feature-{{ $key }}">
                    <div>
                        <div class="fname">{{ $feature['name'] }}</div>
                        <div class="fsub">
                            @if ($rule)<span class="pill orange">Limited</span>@else<span class="pill">Everyone</span>@endif
                            · {{ $feature['weeks'] }} week(s)
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.files.update') }}" style="margin:0">
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

    <div class="card" style="margin-top:18px" id="single">
        <div class="card-head"><h2>Single files</h2></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.files') }}#single" style="display:flex;gap:8px;max-width:520px">
                <input type="text" name="q" value="{{ $q }}" placeholder="File name or picture" style="flex:1">
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
