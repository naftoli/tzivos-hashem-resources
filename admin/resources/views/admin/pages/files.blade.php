@extends('layouts.admin')

@section('title', 'Files | ' . $block->block_key . ' | ' . ucfirst($page->slug) . ' | Resources Admin')
@section('page-title', 'Site pages')

@push('head')
    <style>
        .crumbs { color: #b5b5c3; font-weight: 500; margin-bottom: 14px; }
        .crumbs a { color: #7e8299; }
        .meta { color: #b5b5c3; font-size: 12px; font-weight: 500; }
        .fthumb { width: 72px; height: 72px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); display: block; }
        .fname { font-weight: 600; color: #181c32; }
        .fpath { color: #7e8299; font-size: 12px; word-break: break-all; }
        @include('admin.files._access_css')
        tr.removed td { opacity: .55; }
        tr.removed td.acts, tr.removed td.st { opacity: 1; }
        .act.red { background: #ffe2e5; color: #d1213d; width: auto; padding: 0 10px; font: inherit; font-size: 12px; font-weight: 600; border: 0; cursor: pointer; }
        .act.blue-text { background: #e1f0ff; color: var(--blue); width: auto; padding: 0 10px; font: inherit; font-size: 12px; font-weight: 600; border: 0; cursor: pointer; }
    </style>
@endpush

@section('content')
    <div class="crumbs"><a href="{{ route('admin.pages') }}">Site pages</a> › <a href="{{ route('admin.pages.show', $page) }}">{{ ucfirst($page->slug) }}</a> › <a href="{{ route('admin.pages.block', [$page, $block]) }}">{{ $block->block_key }}</a> › Files</div>

    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head">
            <div>
                <h2>Files on {{ ucfirst($page->slug) }} / {{ $block->block_key }}</h2>
                <div class="meta">{{ $block->heading() }}</div>
            </div>
            <div class="right"><a class="btn light" href="{{ route('admin.pages.block', [$page, $block]) }}">Back to the page</a></div>
        </div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead><tr><th></th><th>File</th><th>Who can see it, and when</th><th>Status</th><th class="num"></th></tr></thead>
                <tbody>
                @forelse ($files as $f)
                    @php $rule = $f['rule']; $removed = $rule && $rule->removed; @endphp
                    <tr class="row @if ($removed) removed @endif">
                        <td style="width:80px">
                            @if ($f['thumb'])<img class="fthumb" src="{{ $f['thumb'] }}" alt="" loading="lazy">@endif
                        </td>
                        <td>
                            <div class="fname">{{ $f['label'] !== '' ? $f['label'] : basename($f['path']) }}</div>
                            <div class="fpath">{{ $f['kind'] === 'image' ? 'Picture' : $f['path'] }}</div>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('admin.pages.block.files.update', [$page, $block]) }}" >
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="ref" value="{{ $f['ref'] }}">
                                @include('admin.files._access', ['rule' => $rule, 'levels' => $levels, 'download' => ! empty($f['download'])])
                            </form>
                        </td>
                        <td class="st">
                            @if ($removed)<span class="pill red">Removed</span>@else<span class="pill">On the site</span>@endif
                            @if ($rule && ! $removed)
                                <div class="fpath" style="margin-top:4px">Own setting</div>
                                <form method="POST" action="{{ route('admin.pages.block.files.clear', [$page, $block]) }}" style="margin:4px 0 0">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="ref" value="{{ $f['ref'] }}">
                                    <button class="act blue-text" type="submit">Clear this setting</button>
                                </form>
                            @endif
                        </td>
                        <td class="acts">
                            @if ($removed)
                                <form method="POST" action="{{ route('admin.pages.block.files.restore', [$page, $block]) }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="ref" value="{{ $f['ref'] }}">
                                    <button class="act blue-text" type="submit">Restore</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.pages.block.files.remove', [$page, $block]) }}" style="margin:0">
                                    @csrf
                                    <input type="hidden" name="ref" value="{{ $f['ref'] }}">
                                    <button class="act red" type="submit">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">This part has no files.</td></tr>
                @endforelse
                </tbody>
            </table>

            @if ($uploads->isNotEmpty())
                <h3 style="margin:26px 0 8px">Uploaded files for this part</h3>
                <table class="tbl">
                    <thead><tr><th>File</th><th class="num"></th></tr></thead>
                    <tbody>
                    @foreach ($uploads as $u)
                        <tr class="row">
                            <td class="primary">{{ $u->original_name }}</td>
                            <td class="acts"><a class="act blue-text" href="{{ route('admin.uploads.edit', $u) }}">Edit</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
