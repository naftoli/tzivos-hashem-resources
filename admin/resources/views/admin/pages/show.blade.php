@extends('layouts.admin')

@section('title', $page->slug . ' | Pages | Resources Admin')
@section('page-title', 'Site pages')

@section('content')
    @php $allPages = \App\Models\Page::where('site_id', $page->site_id)->orderBy('position')->get(['slug']); $total = $page->blocks()->count(); @endphp
    <div class="card">
        <div class="card-head">
            <nav class="tabs">
                @foreach ($allPages as $p)
                    <a href="{{ route('admin.pages.show', $p) }}" class="@if ($p->slug === $page->slug) on @endif">{{ $p->slug }}</a>
                @endforeach
            </nav>
            <div class="right"><span class="sub">{{ $total }} part(s)</span><a class="btn light" href="{{ route('api.pages.show', $page) }}" target="_blank">View as JSON</a></div>
        </div>
        <div class="card-body" style="padding-top:6px">
            @if ($q !== '')
                <p>Showing parts matching “{{ $q }}”. <a href="{{ route('admin.pages.show', $page) }}">Show all</a></p>
            @endif
            <table class="tbl">
                <thead>
                    <tr><th>Part</th><th>Heading</th><th class="num">Size</th><th>Status</th><th class="num"></th></tr>
                    <tr class="filters">
                        <td><input data-col="0" placeholder="Part"></td>
                        <td><input data-col="1" placeholder="Heading"></td>
                        <td></td>
                        <td><select data-col="3"><option value="">All</option><option value="edited">edited</option><option value="original">original</option></select></td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                @forelse ($blocks as $block)
                    <tr class="row">
                        <td class="primary"><a href="{{ route('admin.pages.block', [$page, $block]) }}">{{ $block->block_key }}</a></td>
                        <td>{{ $block->heading() }}</td>
                        <td class="num">{{ number_format(mb_strlen($block->html, 'UTF-8')) }} chars</td>
                        <td>@if ($block->edited_at)<span class="pill orange">edited</span>@else<span class="pill">original</span>@endif</td>
                        <td class="acts"><a class="act" href="{{ route('admin.pages.block.files', [$page, $block]) }}" title="Files"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg></a><a class="act" href="{{ route('admin.pages.block', [$page, $block]) }}" title="Edit"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Nothing found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
