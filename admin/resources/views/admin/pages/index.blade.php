@extends('layouts.admin')

@section('title', 'Pages | Resources Admin')
@section('page-title', 'Site pages')

@section('content')
    <div class="card">
        <div class="card-head"><h2>Site pages</h2><span class="sub">{{ $site->name }}: the site's page content, imported from its code. Pick a page to see and edit its parts.</span></div>
        <div class="card-body" style="padding-top:6px">
            @if ($pages->isEmpty())
                <div class="empty-state" style="padding:28px 6px;color:#5e6278">
                    <h3 style="margin:0 0 6px;color:#181c32">Pages not imported yet</h3>
                    <p style="margin:0">{{ $site->name }} is set up in the admin, but its pages haven't been brought in yet. Each site's pages are imported once from the site's code (<code>php artisan resources:import-pages &lt;folder&gt; --site={{ $site->slug }}</code>). After that they show up here to edit.</p>
                </div>
            @else
                <table class="tbl">
                    <thead>
                        <tr><th>Page</th><th class="num">Parts</th><th>Last imported</th><th class="num"></th></tr>
                        <tr class="filters"><td><input data-col="0" placeholder="Page"></td><td></td><td></td><td></td></tr>
                    </thead>
                    <tbody>
                    @foreach ($pages as $page)
                        <tr class="row">
                            <td class="primary"><a href="{{ route('admin.pages.show', $page) }}">{{ ucfirst($page->slug) }}</a></td>
                            <td class="num">{{ $page->blocks_count }}</td>
                            <td>{{ optional($page->imported_at)->format('M j, Y g:i A') }}</td>
                            <td class="acts"><a class="act" href="{{ route('admin.pages.show', $page) }}" title="Open"><svg viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@endsection
