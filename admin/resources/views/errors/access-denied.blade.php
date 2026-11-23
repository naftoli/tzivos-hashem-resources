@extends(auth()->check() ? 'layouts.admin' : 'layouts.auth')

@section('title', 'Access denied | Resources Admin')
@section('page-title', 'Access denied')

@section('content')
    @auth
        <div class="card">
            <div class="card-head"><h2>Access denied</h2></div>
            <div class="card-body">
                <div class="error" style="margin-bottom:14px">@isset($message) {{ $message }} @else Only {{ implode(' and ', array_map([\App\Models\User::class, 'roleLabel'], $roles ?? ['hq_admin'])) }} can open this page. @endisset</div>
                <a href="{{ route('dashboard') }}">Back to dashboard</a>
            </div>
        </div>
    @else
        <h1>Access denied</h1>
        <div class="error">@isset($message) {{ $message }} @else Only {{ implode(' and ', array_map([\App\Models\User::class, 'roleLabel'], $roles ?? ['hq_admin'])) }} can open this page. @endisset</div>
        <div class="footer"><a href="{{ route('dashboard') }}">Back to dashboard</a></div>
    @endauth
@endsection
