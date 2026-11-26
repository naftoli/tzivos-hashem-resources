@extends('layouts.admin')

@section('title', 'Search | Resources Admin')
@section('page-title', 'Search site pages')

@section('content')
    <div class="card">
        <div class="card-head"><h2>Results for “{{ $q }}”</h2><span class="sub">{{ count($results) }}{{ $capped ? '+' : '' }} part(s)</span></div>
        <div class="card-body" style="padding-top:6px">
            @if (mb_strlen($q) < 2)
                <p class="muted">Type at least 2 letters in the search box above.</p>
            @else
                <table class="tbl">
                    <thead><tr><th>Page</th><th>Part</th><th>Heading</th><th>Where it appears</th><th class="num"></th></tr></thead>
                    <tbody>
                    @forelse ($results as $res)
                        <tr class="row">
                            <td><span class="pill blue">{{ ucfirst($res['page']->slug) }}</span></td>
                            <td class="primary"><a href="{{ route('admin.pages.block', [$res['page'], $res['id']]) }}">{{ $res['key'] }}</a></td>
                            <td>{{ $res['heading'] }}</td>
                            <td class="muted" dir="auto">{{ $res['snippet'] }}</td>
                            <td class="acts"><a class="act" href="{{ route('admin.pages.block', [$res['page'], $res['id']]) }}" title="Edit"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">No parts contain that.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if ($capped)<p class="muted">Showing the first {{ count($results) }}. Type more words to narrow it down.</p>@endif
            @endif
        </div>
    </div>
@endsection
