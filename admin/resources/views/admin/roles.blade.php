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
        <div class="card-head"><h2>Create account</h2></div>
        <div class="card-body">
            <p style="margin:0 0 14px;color:#7e8299">They can sign in with this email and password, even without Mashpia. If they later use Mashpia with the same email, the level you pick here will not be changed.</p>
            <form method="POST" action="{{ route('admin.roles.store') }}" style="display:flex;gap:10px 14px;align-items:flex-end;flex-wrap:wrap;margin:0">
                @csrf
                <label style="display:flex;flex-direction:column;gap:4px">First name
                    <input name="first_name" value="{{ old('first_name') }}" required style="height:32px;min-width:140px">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px">Last name
                    <input name="last_name" value="{{ old('last_name') }}" required style="height:32px;min-width:140px">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px">Email
                    <input name="email" type="email" value="{{ old('email') }}" required style="height:32px;min-width:200px">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px">Password
                    <input name="password" type="password" required minlength="8" autocomplete="new-password" style="height:32px;min-width:140px">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px">Confirm password
                    <input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" style="height:32px;min-width:140px">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px">School or organization
                    <input name="organization" value="{{ old('organization') }}" required style="height:32px;min-width:180px">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px">Role in that place
                    <input name="organization_role" value="{{ old('organization_role') }}" required style="height:32px;min-width:160px">
                </label>
                <label style="display:flex;flex-direction:column;gap:4px">Role on site
                    <select name="role" style="height:32px">
                        @foreach ($roles as $role => $label)
                            <option value="{{ $role }}" @selected(old('role') === $role)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn">Create account</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Users</h2><span class="sub">{{ $users->count() }} total</span></div>
        <div class="card-body" style="padding-top:6px">
            <p style="margin:0 0 12px;color:#7e8299">A change you make here is set by hand, so signing in with Mashpia will not overwrite it. “Use Mashpia role” turns automatic updates back on. Nobody becomes HQ admin from Mashpia login; you can grant or remove HQ admin here (there must always be at least one). An Owner cannot be lowered or removed by any other admin; only an Owner can grant or remove the Owner flag.</p>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>First name</th>
                        <th>Last name</th>
                        <th>Email</th>
                        <th>School or organization</th>
                        <th>Role in that place</th>
                        <th>Role on site</th>
                        <th>Mashpia role</th>
                        <th class="num">Change role</th>
                    </tr>
                    <tr class="filters">
                        <td><input data-col="0" placeholder="First"></td>
                        <td><input data-col="1" placeholder="Last"></td>
                        <td><input data-col="2" placeholder="Email"></td>
                        <td><input data-col="3" placeholder="School"></td>
                        <td><input data-col="4" placeholder="Role there"></td>
                        <td><select data-col="5"><option value="">All</option>@foreach ($roles as $role => $label)<option>{{ $label }}</option>@endforeach</select></td>
                        <td><input data-col="6" placeholder="Mashpia"></td>
                        <td></td>
                    </tr>
                </thead>
                <tbody>
                @foreach ($users as $user)
                    <tr class="row">
                        <td class="primary">{{ $user->first_name ?: $user->name }}</td>
                        <td>{{ $user->last_name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->organization ?: '—' }}</td>
                        <td>{{ $user->organization_role ?: '—' }}</td>
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
                            @if (auth()->user()->isOwner() && ! $user->is(auth()->user()))
                                @if ($user->isOwner())
                                    <form method="POST" action="{{ route('admin.roles.owner', $user) }}" style="display:inline;margin:0">
                                        @csrf
                                        <input type="hidden" name="owner" value="0">
                                        <button type="submit" class="act" title="Remove Owner">Remove Owner</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.roles.owner', $user) }}" style="display:inline;margin:0">
                                        @csrf
                                        <input type="hidden" name="owner" value="1">
                                        <button type="submit" class="act" title="Make Owner">Make Owner</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection