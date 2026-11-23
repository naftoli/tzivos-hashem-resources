@extends('layouts.admin')

@section('title', 'Admin requests | Resources Admin')
@section('page-title', 'Admin requests')

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Waiting for a decision</h2><span class="sub">{{ $pending->count() }} pending</span></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Mashpia role</th><th>Asked</th><th class="num">Decision</th></tr>
                </thead>
                <tbody>
                @forelse ($pending as $req)
                    <tr class="row">
                        <td class="primary">{{ $req->user->name }}</td>
                        <td>{{ $req->user->email }}</td>
                        <td>{{ $req->user->mashpia_role ?: '—' }}</td>
                        <td>{{ $req->created_at?->timezone(config('admin.timezone'))->format('M j, Y g:i A') }}</td>
                        <td class="acts">
                            <form method="POST" action="{{ route('admin.admin-requests.approve', $req) }}" style="display:inline;margin:0">
                                @csrf
                                <button type="submit" class="btn">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.admin-requests.decline', $req) }}" style="display:inline;margin:0">
                                @csrf
                                <button type="submit" class="btn light">Decline</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No requests waiting.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Recently reviewed</h2></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Decision</th><th>By</th><th>When</th></tr>
                </thead>
                <tbody>
                @forelse ($recent as $req)
                    <tr class="row">
                        <td class="primary">{{ $req->user->name }}</td>
                        <td>{{ $req->user->email }}</td>
                        <td><span class="pill {{ $req->status === 'approved' ? 'blue' : 'orange' }}">{{ $req->status === 'approved' ? 'Approved' : 'Declined' }}</span></td>
                        <td>{{ $req->decider->email ?? '—' }}</td>
                        <td>{{ $req->decided_at?->timezone(config('admin.timezone'))->format('M j, Y g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">None yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
