@extends('layouts.admin')

@section('title', 'Change password | Resources Admin')
@section('page-title', 'Change password')

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif
    <div class="card">
        <div class="card-head"><h2>Change password</h2></div>
        <div class="card-body">
            <p style="margin:0 0 14px;color:#7e8299">You need the password you use to sign in on this site. If you only sign in with Mashpia and don't have one, you can't change it here.</p>
            <form method="POST" action="{{ route('account.password.update') }}" style="max-width:360px;margin:0;display:flex;flex-direction:column;gap:14px">
                @csrf
                <label>Current password
                    <input class="input" type="password" name="current_password" required autocomplete="current-password" style="width:100%">
                </label>
                <label>New password
                    <input class="input" type="password" name="password" required minlength="8" autocomplete="new-password" style="width:100%">
                </label>
                <label>Confirm new password
                    <input class="input" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" style="width:100%">
                </label>
                <div>
                    <button type="submit" class="btn">Change password</button>
                </div>
            </form>
        </div>
    </div>
@endsection
