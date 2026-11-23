@extends('layouts.admin')

@section('title', 'Stats | Resources Admin')
@section('page-title', 'Stats')

@section('content')
    <div class="card">
        <div class="card-head">
            <h2>Opens</h2>
            <span class="sub">{{ $total }} in this range</span>
            <div class="right">
                <a class="btn light" href="{{ route('admin.stats.csv', request()->only(['from', 'to'])) }}">Download CSV</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.stats') }}" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
                <label>From
                    <input class="input" type="date" name="from" value="{{ $from }}">
                </label>
                <label>To
                    <input class="input" type="date" name="to" value="{{ $to }}">
                </label>
                <button class="btn" type="submit">Show</button>
            </form>
            <p class="muted" style="margin:12px 0 0">Times are New York time. This list is who opened a page or a file, and when. It does not keep names, addresses, or anything about their computer.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Most opened pages</h2></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead><tr><th>Page</th><th class="num">Opens</th></tr></thead>
                <tbody>
                    @forelse ($pages as $row)
                        <tr class="row"><td>{{ $row->label }}</td><td class="num">{{ $row->opens }}</td></tr>
                    @empty
                        <tr><td class="empty" colspan="2">No pages opened in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Most opened files</h2></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead><tr><th>File</th><th class="num">Opens</th></tr></thead>
                <tbody>
                    @forelse ($files as $row)
                        <tr class="row"><td>{{ $row->label }}</td><td class="num">{{ $row->opens }}</td></tr>
                    @empty
                        <tr><td class="empty" colspan="2">No files opened in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>People</h2></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead><tr><th>Who</th><th>Level</th><th class="num">Opens</th><th>Last open</th></tr></thead>
                <tbody>
                    @forelse ($people as $row)
                        <tr class="row">
                            <td>{{ $row->email }}</td>
                            <td>{{ $row->role }}</td>
                            <td class="num">{{ $row->opens }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->last_at)->timezone(config('admin.timezone'))->format('M j, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td class="empty" colspan="4">Nobody opened a page or a file in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
