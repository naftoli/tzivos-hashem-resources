@extends('layouts.auth')

@section('title', "You don't have access | Resources Admin")

@section('content')
    <h1>You don't have access to the admin site</h1>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Sign out</button>
    </form>
@endsection
