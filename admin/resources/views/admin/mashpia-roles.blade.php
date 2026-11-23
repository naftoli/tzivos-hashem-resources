@extends('layouts.admin')

@section('title', 'Mashpia roles | Resources Admin')
@section('page-title', 'Mashpia roles')

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Which Mashpia role becomes which admin level</h2><span class="sub">{{ $maps->count() }} on the list</span></div>
        <div class="card-body">
            <p style="margin:0 0 14px;color:#7e8299">When someone signs in with Mashpia, their admin level is taken from this list. Names are matched without worrying about capital letters or extra spaces. No role on this list can be set to HQ admin — that level is only granted by an HQ admin, or by approving a request. People whose Mashpia role is marked “May request HQ admin” see a button to ask.</p>
            <p style="margin:0 0 14px;color:#7e8299"><b>Not matched yet:</b> there is no known Mashpia role name for the News site level. If Mashpia sends a role that is not on this list, the person gets News site (the lowest level) and it is written in the log.</p>
            <table class="tbl">
                <thead>
                    <tr><th>Mashpia role</th><th>Admin level</th><th>May request HQ admin</th><th class="num">Actions</th></tr>
                </thead>
                <tbody>
                @foreach ($maps as $map)
                    <tr class="row">
                        <td colspan="4" style="padding:8px 0">
                            <form method="POST" action="{{ route('admin.mashpia-roles.update', $map) }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0">
                                @csrf
                                @method('PUT')
                                <input name="mashpia_role" value="{{ $map->mashpia_role }}" required style="height:32px;min-width:180px">
                                <select name="admin_role" style="height:32px">
                                    @foreach ($levels as $role => $label)
                                        <option value="{{ $role }}" @selected($map->admin_role === $role)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label style="display:flex;align-items:center;gap:6px;white-space:nowrap">
                                    <input type="checkbox" name="can_request_admin" value="1" @checked($map->can_request_admin)> May request HQ admin
                                </label>
                                <button type="submit" class="btn">Save</button>
                            </form>
                            <form method="POST" action="{{ route('admin.mashpia-roles.destroy', $map) }}" style="display:inline;margin:0" onsubmit="return confirm('Remove this Mashpia role from the list?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn light">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                    <tr class="row">
                        <td colspan="4">
                            <form method="POST" action="{{ route('admin.mashpia-roles.store') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0 0">
                                @csrf
                                <input name="mashpia_role" placeholder="Mashpia role name" required style="height:32px;min-width:180px">
                                <select name="admin_role" style="height:32px">
                                    @foreach ($levels as $role => $label)
                                        <option value="{{ $role }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <label style="display:flex;align-items:center;gap:6px;white-space:nowrap">
                                    <input type="checkbox" name="can_request_admin" value="1"> May request HQ admin
                                </label>
                                <button type="submit" class="btn">Add</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
