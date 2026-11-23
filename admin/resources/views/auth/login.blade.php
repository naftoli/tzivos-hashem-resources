@extends('layouts.auth')

@section('title', 'Sign in | Resources Admin')

@section('content')
    <h1>Resources Admin</h1>

    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @if (! empty($mashpiaButton))
        {{-- Real mode: sign in through Mashpia (the admin never sees Mashpia passwords) --}}
        @if (! empty($mashpiaMissing))
            <div class="error">Sign-in with Mashpia is not set up yet.</div>
        @else
            <form method="GET" action="{{ route('login.mashpia') }}">
                <button type="submit">Sign in with Mashpia</button>
            </form>
        @endif
        <p class="or" style="margin:18px 0 8px;text-align:center;color:#7e8299">Or sign in with your email and password</p>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
        </div>
        <div class="row">
            <label style="font-weight:400;margin:0"><input type="checkbox" name="remember"> Remember me</label>
            <a href="{{ route('password.request') }}">Forgot password?</a>
        </div>
        <button type="submit">Sign In</button>
    </form>


    <div class="footer"><a href="{{ url('/') }}">Back to home</a></div>
@endsection