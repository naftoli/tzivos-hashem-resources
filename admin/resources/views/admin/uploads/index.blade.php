@extends('layouts.admin')

@push('head')
    <style>
        .file-drop.is-dragover input[type=file] { border-color: var(--blue); background: #e1f0ff; }
        .file-drop-hint { color: #7e8299; font-size: 12px; margin: 8px 0 0; }
        .file-drop-names { margin: 8px 0 0; padding: 0; list-style: none; color: #3f4254; font-size: 12px; }
        .file-drop-names li { margin: 2px 0; display: flex; align-items: center; gap: 8px; word-break: break-all; }
        .file-drop-remove { border: 0; background: transparent; color: #d1213d; cursor: pointer; font: inherit; font-weight: 600; padding: 0 4px; line-height: 1; }
        .file-drop-remove:hover { color: #a10d24; }
        .act.red { background: #ffe2e5; color: #d1213d; width: auto; padding: 0 10px; font: inherit; font-size: 12px; font-weight: 600; }
        .card-head .up-modes { margin-left: auto; height: 64px; }
        .act.blue-text { background: #e1f0ff; color: var(--blue); width: auto; padding: 0 10px; font: inherit; font-size: 12px; font-weight: 600; }
    </style>
@endpush

@section('title', 'Uploads | Resources Admin')
@section('page-title', 'Upload files')

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    @php $hyMode = $canHachayol && request('mode') === 'hachayol'; @endphp
    <div class="card">
        <div class="card-head">
            <h2>Upload</h2>
            @if ($canHachayol)
                <div class="tabs up-modes" role="tablist">
                    <button type="button" role="tab" data-mode="files" class="{{ $hyMode ? '' : 'on' }}">Upload files</button>
                    <button type="button" role="tab" data-mode="hachayol" class="{{ $hyMode ? 'on' : '' }}">Upload Full Hachayols</button>
                </div>
            @endif
        </div>
        <div class="card-body" data-panel="files" @if ($hyMode) hidden @endif>
            <form method="POST" action="{{ route('admin.uploads.store') }}" enctype="multipart/form-data" style="max-width:900px">
                @csrf
                @include('admin.uploads._fields', ['upload' => null])
                <div class="field">
                    <label for="files">Files</label>
                    <div class="file-drop">
                        <input id="files" type="file" name="files[]" multiple required>
                        <div class="file-drop-hint">or drag files here, or paste</div>
                        <ul class="file-drop-names" hidden></ul>
                    </div>
                </div>
                <button type="submit" class="btn">Upload</button>
            </form>
        </div>
        @if ($canHachayol)
            <div class="card-body" data-panel="hachayol" @unless ($hyMode) hidden @endunless>
                @include('admin.uploads._hachayol')
            </div>
        @endif
    </div>

    @if (isset($pendingUploads) && $pendingUploads->count())
    <div class="card">
        <div class="card-head"><h2>Waiting for approval</h2><span class="sub">{{ $pendingUploads->count() }} total</span></div>
        <div class="card-body" style="padding-top:6px">
            <p style="margin:0 0 12px;color:#7e8299">These files were uploaded by a teacher. They will not show on the site until you approve them.</p>
            <table class="tbl">
                <thead>
                    <tr><th>File</th><th>Type</th><th>Page on the site</th><th>Uploaded by</th><th class="num">Actions</th></tr>
                </thead>
                <tbody>
                @foreach ($pendingUploads as $upload)
                    <tr class="row">
                        <td class="primary" style="word-break:break-all">{{ $upload->original_name }} <span class="pill orange">Waiting for approval</span></td>
                        <td><span class="pill {{ $upload->file_type ? 'purple' : 'grey' }}">{{ $upload->typeLabel() }}</span></td>
                        <td style="word-break:break-all">{{ $upload->pageLabel() ?? $upload->page_key }}</td>
                        <td>{{ $upload->uploader?->email ?? 'Unknown' }}</td>
                        <td class="acts" style="white-space:nowrap">
                            <a class="act green" href="{{ $upload->publicUrl() }}" target="_blank" title="Open {{ $upload->publicPath() }}"><svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14L21 3"/></svg></a>
                            @if ($upload->canBeApprovedBy(auth()->user()))
                                <form method="POST" action="{{ route('admin.uploads.approve', $upload) }}" style="display:inline;margin:0">
                                    @csrf
                                    <button type="submit" class="act text" title="Approve {{ $upload->original_name }}">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('admin.uploads.reject', $upload) }}" style="display:inline;margin:0">
                                    @csrf
                                    <button type="submit" class="act text red" title="Reject {{ $upload->original_name }}">Reject</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="card">
        <div class="card-head"><h2>Uploaded files</h2><span class="sub">{{ $uploads->count() }} total</span></div>
        <div class="card-body" style="padding-top:6px">
            <table class="tbl">
                <thead>
                    <tr><th>File</th><th>Type</th><th class="num">Size</th><th>Page on the site</th><th>Who can see it</th><th>Uploaded by</th><th class="num">Actions</th></tr>
                    <tr class="filters">
                        <td><input data-col="0" placeholder="File"></td>
                        <td><select data-col="1" data-exact="1"><option value="">All types</option>@foreach (\App\Models\Upload::TYPES as $tLabel)<option>{{ $tLabel }}</option>@endforeach<option>Not set</option></select></td><td></td>
                        <td><input data-col="3" placeholder="Page"></td>
                        <td><input data-col="4" placeholder="Level"></td>
                        <td><input data-col="5" placeholder="Uploaded by"></td><td></td>
                    </tr>
                </thead>
                <tbody>
                @forelse ($uploads as $upload)
                    <tr class="row">
                        <td class="primary" style="word-break:break-all">{{ $upload->original_name }}@if ($upload->isPending()) <span class="pill orange">Waiting for approval</span>@elseif ($upload->isRejected()) <span class="pill grey">Rejected</span>@endif</td>
                        <td><span class="pill {{ $upload->file_type ? 'purple' : 'grey' }}">{{ $upload->typeLabel() }}</span></td>
                        <td class="num">{{ $upload->humanSize() }}</td>
                        <td style="word-break:break-all">
                            @if ($upload->page_url)
                                {{ $upload->pageLabel() ?? $upload->page_key }}<br><a href="{{ str_starts_with($upload->page_url, 'http') ? $upload->page_url : 'https://' . $upload->page_url }}" target="_blank" rel="noopener" style="font-size:11.5px">{{ $upload->page_url }}</a>
                            @else
                                <span class="pill grey">No page chosen</span><br><span style="font-size:11.5px;color:#b5b5c3">Old location: {{ $upload->destination }}</span>
                            @endif
                        </td>
                        <td>
                            @foreach (\App\Models\User::ROLES as $role => $label)
                                @php $p = $upload->permissions->firstWhere('role', $role); @endphp
                                @if ($p)
                                    <div style="margin:2px 0"><span class="pill {{ $p->isOpen() ? '' : 'orange' }}">{{ $label }}</span> <span style="font-size:11.5px">{{ $p->summary() }}</span></div>
                                @endif
                            @endforeach
                        </td>
                        <td>{{ $upload->uploader?->email ?? 'Unknown' }}</td>
                        <td class="acts" style="white-space:nowrap">
                            <a class="act green" href="{{ $upload->publicUrl() }}" target="_blank" title="Open {{ $upload->publicPath() }}"><svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14L21 3"/></svg></a>
                            @if (auth()->user()->isHQ() || $upload->uploaded_by === auth()->id())
                                <a class="act" href="{{ route('admin.uploads.edit', $upload) }}" title="Change page or who can see it"><svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
                            @endif
                            @if ($upload->canBeDeletedBy(auth()->user()))
                                <form method="POST" action="{{ route('admin.uploads.destroy', $upload) }}" style="display:inline;margin:0" onsubmit="return confirm('Delete this file? You can restore it later from the Deleted section.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="act text red" title="Delete {{ $upload->original_name }}">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No files uploaded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="hint" style="color:#b5b5c3;font-size:12px;margin-top:10px">Green level = can see it now. Orange = not yet, or no longer (outside its dates). Times are New York time.</div>
        </div>
    </div>

    @if ($deletedUploads->count())
    <div class="card">
        <div class="card-head"><h2>Deleted</h2><span class="sub">{{ $deletedUploads->count() }} total</span></div>
        <div class="card-body" style="padding-top:6px">
            <p style="margin:0 0 12px;color:#7e8299">These files are hidden and can't be opened until someone restores them.</p>
            <table class="tbl">
                <thead>
                    <tr><th>File</th><th>Type</th><th>Page on the site</th><th>Deleted by</th><th class="num">Actions</th></tr>
                </thead>
                <tbody>
                @foreach ($deletedUploads as $upload)
                    <tr class="row">
                        <td class="primary" style="word-break:break-all">{{ $upload->original_name }}</td>
                        <td><span class="pill {{ $upload->file_type ? 'purple' : 'grey' }}">{{ $upload->typeLabel() }}</span></td>
                        <td style="word-break:break-all">{{ $upload->pageLabel() ?? $upload->page_key ?? '—' }}</td>
                        <td>{{ $upload->deletedBy?->email ?? 'Unknown' }}@if ($upload->deleted_by_admin) <span class="pill grey">Admin</span>@endif</td>
                        <td class="acts" style="white-space:nowrap">
                            @if ($upload->canBeRestoredBy(auth()->user()))
                                <form method="POST" action="{{ route('admin.uploads.restore', $upload) }}" style="display:inline;margin:0">
                                    @csrf
                                    <button type="submit" class="act text blue-text" title="Restore {{ $upload->original_name }}">Restore</button>
                                </form>
                            @else
                                <span style="font-size:12px;color:#7e8299">Only an admin can restore this</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    function fileKey(f) { return f.name + '\0' + f.size + '\0' + f.lastModified; }

    function setFiles(input, incoming, append) {
        var dt = new DataTransfer();
        var seen = {};
        function add(f) {
            var k = fileKey(f);
            if (seen[k]) return;
            seen[k] = true;
            dt.items.add(f);
        }
        if (append) Array.prototype.forEach.call(input.files, add);
        Array.prototype.forEach.call(incoming, add);
        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function isFileDrag(e) {
        var types = e.dataTransfer && e.dataTransfer.types;
        if (!types) return false;
        if (typeof types.contains === 'function') return types.contains('Files');
        return Array.prototype.indexOf.call(types, 'Files') !== -1;
    }

    function removeFile(input, index) {
        var dt = new DataTransfer();
        Array.prototype.forEach.call(input.files, function (f, i) {
            if (i !== index) dt.items.add(f);
        });
        input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function renderNames(box, input) {
        var list = box.querySelector('.file-drop-names');
        if (!list) return;
        list.innerHTML = '';
        if (!input.files.length) { list.hidden = true; return; }
        Array.prototype.forEach.call(input.files, function (f, i) {
            var li = document.createElement('li');
            var name = document.createElement('span');
            name.textContent = f.name;
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'file-drop-remove';
            rm.setAttribute('aria-label', 'Remove ' + f.name);
            rm.textContent = '✕';
            rm.addEventListener('click', function () { removeFile(input, i); });
            li.appendChild(name);
            li.appendChild(rm);
            list.appendChild(li);
        });
        list.hidden = false;
    }

    function enhance(input) {
        var box = input.closest ? input.closest('.file-drop') : null;
        if (!box) {
            box = document.createElement('div');
            box.className = 'file-drop';
            input.parentNode.insertBefore(box, input);
            box.appendChild(input);
        }
        if (!box.querySelector('.file-drop-hint')) {
            var hint = document.createElement('div');
            hint.className = 'file-drop-hint';
            hint.textContent = 'or drag files here, or paste';
            box.appendChild(hint);
        }
        if (!box.querySelector('.file-drop-names')) {
            var names = document.createElement('ul');
            names.className = 'file-drop-names';
            names.hidden = true;
            box.appendChild(names);
        }
        var depth = 0;
        box.addEventListener('dragenter', function (e) {
            if (!isFileDrag(e)) return;
            e.preventDefault();
            e.stopPropagation();
            depth++;
            box.classList.add('is-dragover');
        });
        box.addEventListener('dragover', function (e) {
            if (!isFileDrag(e)) return;
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'copy';
            box.classList.add('is-dragover');
        });
        box.addEventListener('dragleave', function (e) {
            e.preventDefault();
            depth = Math.max(0, depth - 1);
            if (!depth) box.classList.remove('is-dragover');
        });
        box.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            depth = 0;
            box.classList.remove('is-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                setFiles(input, e.dataTransfer.files, true);
            }
        });
        input.addEventListener('change', function () { renderNames(box, input); });
        renderNames(box, input);
    }

    Array.prototype.forEach.call(document.querySelectorAll('input[type=file]:not([data-hy])'), enhance);

    document.addEventListener('dragover', function (e) { if (isFileDrag(e)) e.preventDefault(); });
    document.addEventListener('drop', function (e) { if (isFileDrag(e)) e.preventDefault(); });

    document.addEventListener('paste', function (e) {
        var files = e.clipboardData && e.clipboardData.files;
        if (!files || !files.length) return;
        var input = document.querySelector('input[type=file]:not([data-hy])');
        if (!input || input.closest('[hidden]')) return;
        e.preventDefault();
        setFiles(input, files, true);
    });
})();
</script>
@endpush
