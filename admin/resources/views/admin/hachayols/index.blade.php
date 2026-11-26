@extends('layouts.admin')

@section('title', 'Upload Full Hachayols | Resources Admin')
@section('page-title', 'Upload Full Hachayols')

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Upload Full Hachayols</h2></div>
        <div class="card-body">
            @include('admin.hachayols._picker')
        </div>
    </div>
@endsection
