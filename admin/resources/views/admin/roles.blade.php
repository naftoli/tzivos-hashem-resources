@extends('layouts.admin')

@section('title', 'Roles | Resources Admin')
@section('page-title', 'User roles')

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Users</h2><span class="sub">{{ $users->count() }} total</span></div>
        <div class="card-body" style="padding-top:6px">
            <p style="margin:0 0 12px;color:#7e8299">A change you make here is set by hand, so signing in with Mashpia will not overwrite it. “Use Mashpia role” turns automatic updates back on. Nobody becomes HQ admin from Mashpia login; you can grant or remove HQ admin here (there must always be at least one).</p>
            <table class="tbl">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Mashpia role</th><th class="num">Change role</th></tr>
                    <tr class="filters">
                        <td><input data-col="0" placeholder="Name"></td>
                        <td><input data-col="1" placeholder="Email"></td>
                        <td><select data-col="2"><option value="">All</option>@foreach ($roles as $role => $label)<option>{{ $label }}</option>@endforeach</select></td>
                        <td><input data-col="3" placeholder="Mashpia"></td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                @foreach ($users as $user)
                    <tr class="row">
                        <td class="primary">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="pill {{ ['hq_admin' => 'blue', 'hq_staff' => 'purple', 'base' => 'grey', 'news' => 'orange'][$user->role] ?? '' }}">{{ $user->roleName() }}</span>
                            @if ($user->isOwner()) <span class="pill red">Owner</span>
                            @elseif ($user->role_locked) <span class="pill orange">set by hand</span>
                            @else <span class="pill">automatic</span>
                            @endif
                        </td>
                        <td>{{ $user->mashpia_role ?: '—' }}</td>
                        <td class="acts">
                            @php $locked = $user->isOwner() || $user->is(auth()->user()); @endphp
                            <form method="POST" action="{{ route('admin.roles.update', $user) }}" style="display:inline-flex;gap:6px;margin:0;flex-wrap:wrap;justify-content:flex-end">
                                @csrf
                                @method('PUT')
                                <select name="role" style="height:32px" @disabled($locked) title="{{ $user->isOwner() ? 'Owner account: always HQ admin' : ($locked ? 'You can\'t change your own role' : 'Change role') }}">
                                    @foreach ($roles as $role => $label)
                                        <option value="{{ $role }}" @selected($user->role === $role)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="act" title="Save role" @disabled($locked)><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg></button>
                            </form>
                            @if (! $locked && $user->mashpia_role)
                                <form method="POST" action="{{ route('admin.roles.reset', $user) }}" style="display:inline;margin:0">
                                    @csrf
                                    <button type="submit" class="act" title="Use Mashpia role (automatic)">Use Mashpia role</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
