@extends('layouts.admin')

@section('title', $upload->original_name . ' | Uploads | Resources Admin')
@section('page-title', 'Upload files')

@section('content')
    <div style="color:#b5b5c3;font-weight:500;margin-bottom:14px"><a href="{{ route('admin.uploads') }}" style="color:#7e8299">Upload files</a> › {{ $upload->original_name }}</div>
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head"><h2>{{ $upload->original_name }}</h2><span class="sub">{{ $upload->humanSize() }} · <a href="{{ $upload->publicUrl() }}" target="_blank">{{ $upload->publicPath() }}</a></span></div>
        <div class="card-body">
            @if (! $upload->page_url && $upload->destination)
                <div class="help" style="background:#f3f6f9;border-radius:8px;padding:12px 16px;margin-bottom:14px;color:#5e6278">This file was uploaded before pages could be chosen. Its old location was <b>{{ $upload->destination }}</b>, and that address still works.</div>
            @endif
            <form method="POST" action="{{ route('admin.uploads.update', $upload) }}" style="max-width:900px">
                @csrf
                @method('PUT')
                @include('admin.uploads._fields', ['upload' => $upload])
                <button type="submit" class="btn">Save</button>
                <a href="{{ route('admin.uploads') }}" class="btn light" style="text-decoration:none">Cancel</a>
            </form>
        </div>
    </div>
@endsection
