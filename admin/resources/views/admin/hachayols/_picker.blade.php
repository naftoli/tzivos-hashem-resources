{{-- Upload Full Hachayols: whole Hachayol PDFs or a folder of them. Each is read and split; the preview comes next. --}}
<style>
    .hy { max-width: 980px; }
    .hy-lead { margin: 0 0 18px; color: #5e6278; font-size: 13px; line-height: 1.6; max-width: 760px; }
    .hy-drop { position: relative; display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 34px 24px 30px; border: 2px dashed #c9d7ea; border-radius: 14px; background: linear-gradient(180deg, #f8fbff, #f3f7fd); text-align: center; transition: border-color .15s, background .15s; }
    .hy-drop.is-over { border-color: var(--blue); background: #e8f3ff; }
    .hy-drop-icon { display: flex; align-items: center; justify-content: center; width: 58px; height: 58px; border-radius: 16px; background: #fff; color: var(--blue); box-shadow: 0 6px 18px rgba(54,153,255,.18); }
    .hy-drop-icon svg { width: 28px; height: 28px; }
    .hy-drop h3 { margin: 4px 0 0; font-size: 16px; font-weight: 600; color: #181c32; }
    .hy-drop p { margin: 0; color: #7e8299; font-size: 12.5px; }
    .hy-pick { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 6px; }
    .hy-pick label { cursor: pointer; }
    .hy-pick input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .btn svg { width: 16px; height: 16px; }
    .hy-queue { margin: 20px 0 0; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
    .hy [hidden] { display: none !important; }
    .hy .act.blue-text { width: auto; display: inline-flex; align-items: center; height: 28px; border: 0; border-radius: 6px; background: #e1f0ff; color: var(--blue); padding: 0 10px; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; }
    .hy .file-drop-remove { border: 0; background: transparent; color: #d1213d; cursor: pointer; font: inherit; font-weight: 600; padding: 0 4px; line-height: 1; }
    .hy .file-drop-remove:hover { color: #a10d24; }
    .hy-queue-head { display: flex; align-items: center; gap: 10px; padding: 12px 16px; background: #fafbfd; border-bottom: 1px solid var(--line); font-weight: 600; color: #181c32; font-size: 13px; }
    .hy-queue-head .sub { color: var(--muted); font-weight: 500; font-size: 12px; }
    .hy-queue-head .right { margin-left: auto; display: flex; gap: 8px; }
    .hy-row { display: grid; grid-template-columns: 34px 1fr auto auto; align-items: center; gap: 12px; padding: 11px 16px; border-bottom: 1px solid #f1f2f6; font-size: 13px; }
    .hy-row:last-child { border-bottom: 0; }
    .hy-row .pdf { display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px; background: #ffe2e5; color: #d1213d; font-size: 10px; font-weight: 700; letter-spacing: .03em; }
    .hy-row .nm { min-width: 0; }
    .hy-row .nm b { display: block; font-weight: 600; color: #181c32; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .hy-row .nm span { color: #a1a5b7; font-size: 11.5px; }
    .hy-row .msg { font-size: 12px; color: #7e8299; text-align: right; max-width: 360px; }
    .hy-row .msg.bad { color: #d1213d; }
    .hy-bar { height: 4px; border-radius: 4px; background: #eef1f6; margin-top: 6px; overflow: hidden; }
    .hy-bar span { display: block; height: 100%; width: 0; background: var(--blue); transition: width .2s; }
    .hy-actions { display: flex; align-items: center; gap: 12px; margin-top: 18px; }
    .hy-actions .note { color: #7e8299; font-size: 12px; }
    .hy-warn { margin: 0 0 16px; padding: 12px 14px; border-radius: 10px; background: #fff4de; color: #8a5a00; font-size: 12.5px; line-height: 1.5; }
    .hy-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 22px 0 0; }
    .hy-step { display: flex; gap: 10px; padding: 12px 14px; border-radius: 10px; background: #fafbfd; border: 1px solid #f0f1f6; font-size: 12px; color: #5e6278; line-height: 1.45; }
    .hy-step b { display: block; color: #181c32; font-size: 12.5px; margin-bottom: 2px; }
    .hy-step .n { flex-shrink: 0; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #e1f0ff; color: var(--blue); font-weight: 700; font-size: 12px; }
    @media (max-width: 760px) { .hy-steps { grid-template-columns: 1fr; } .hy-row { grid-template-columns: 34px 1fr auto; } .hy-row .msg { grid-column: 2 / -1; text-align: left; } }
</style>

<div class="hy" id="hy" data-analyze="{{ route('admin.hachayols.analyze') }}" data-token="{{ csrf_token() }}" data-batch="{{ \Illuminate\Support\Str::random(16) }}">
    <p class="hy-lead">Add whole Hachayol magazines. Each PDF is read and put in its week, split into its features, with the text and page pictures for each one. You check everything in a preview before anything goes on the site.</p>

    @if ($hachayolTools && ! $hachayolTools['text'])
        <div class="hy-warn">{{ \App\Support\Hachayol\PdfTools::MISSING_TEXT }}</div>
    @elseif ($hachayolTools && ! $hachayolTools['pictures'])
        <div class="hy-warn">{{ \App\Support\Hachayol\PdfTools::MISSING_PICTURES }}</div>
    @endif

    <div class="hy-drop" id="hyDrop">
        <span class="hy-drop-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 18v-6M9.5 14.5L12 12l2.5 2.5"/></svg></span>
        <h3>Drop Hachayol PDFs or a folder here</h3>
        <p>One magazine, a few, or a whole folder. Only PDF files are used.</p>
        <div class="hy-pick">
            <label class="btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/></svg>Choose PDFs<input type="file" id="hyFiles" data-hy accept=".pdf,application/pdf" multiple></label>
            <label class="btn light"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>Choose a folder<input type="file" id="hyFolder" data-hy webkitdirectory directory multiple></label>
        </div>
    </div>

    <div class="hy-queue" id="hyQueue" hidden>
        <div class="hy-queue-head">Ready to read <span class="sub" id="hyCount"></span><div class="right"><button type="button" class="act blue-text" id="hyClear">Clear list</button></div></div>
        <div id="hyRows"></div>
    </div>

    <div class="hy-actions">
        <button type="button" class="btn" id="hyGo" disabled>Read and split</button>
        <a class="btn light" id="hyPreview" href="#" hidden>Open preview →</a>
        <span class="note" id="hyNote"></span>
    </div>

    <div class="hy-steps">
        <div class="hy-step"><span class="n">1</span><div><b>Upload</b>PDFs or a folder. Each file is sent on its own, so big magazines are fine.</div></div>
        <div class="hy-step"><span class="n">2</span><div><b>Preview</b>Issue, Parsha, date and every feature: title, text, pages, Mission and niggun recordings.</div></div>
        <div class="hy-step"><span class="n">3</span><div><b>Publish</b>The PDF, page pictures, feature pages and the week's cards go on the site.</div></div>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('hy');
    if (!root) return;
    var token = root.dataset.token;
    var queue = [];
    var busy = false;
    var rows = document.getElementById('hyRows');

    function size(n) { return n > 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
    function isPdf(f) { return /\.pdf$/i.test(f.name); }

    function add(files) {
        var skipped = 0;
        Array.prototype.forEach.call(files, function (f) {
            if (!isPdf(f)) { skipped++; return; }
            var path = f.webkitRelativePath || f.relPath || f.name;
            if (queue.some(function (q) { return q.path === path && q.file.size === f.size; })) return;
            queue.push({ file: f, path: path, state: 'wait', msg: 'Waiting' });
        });
        queue.sort(function (a, b) { return a.path.localeCompare(b.path, undefined, { numeric: true }); });
        document.getElementById('hyNote').textContent = skipped ? skipped + ' file(s) that are not PDFs were left out.' : '';
        draw();
    }

    function draw() {
        document.getElementById('hyQueue').hidden = !queue.length;
        document.getElementById('hyCount').textContent = queue.length + ' PDF(s)';
        document.getElementById('hyGo').disabled = busy || !queue.some(function (q) { return q.state === 'wait'; });
        rows.innerHTML = '';
        queue.forEach(function (q, i) {
            var row = document.createElement('div');
            row.className = 'hy-row';
            var badge = document.createElement('span'); badge.className = 'pdf'; badge.textContent = 'PDF';
            var nm = document.createElement('div'); nm.className = 'nm';
            var b = document.createElement('b'); b.textContent = q.path;
            var s = document.createElement('span'); s.textContent = size(q.file.size);
            nm.append(b, s);
            if (q.state === 'send') { var bar = document.createElement('div'); bar.className = 'hy-bar'; var fill = document.createElement('span'); fill.style.width = (q.pct || 0) + '%'; bar.appendChild(fill); nm.appendChild(bar); }
            var pill = document.createElement('span');
            pill.className = 'pill ' + ({ wait: 'grey', send: 'blue', read: 'orange', ok: '', bad: 'red' })[q.state];
            pill.textContent = ({ wait: 'Waiting', send: 'Uploading', read: 'Reading', ok: 'Ready', bad: 'Not read' })[q.state];
            var msg = document.createElement('div'); msg.className = 'msg' + (q.state === 'bad' ? ' bad' : ''); msg.textContent = q.state === 'wait' ? '' : q.msg;
            row.append(badge, nm, msg, pill);
            if (q.state === 'wait' && !busy) {
                var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'file-drop-remove'; rm.textContent = '✕'; rm.setAttribute('aria-label', 'Remove ' + q.path);
                rm.addEventListener('click', function () { queue.splice(i, 1); draw(); });
                row.appendChild(rm);
                row.style.gridTemplateColumns = '34px 1fr auto auto 20px';
            }
            rows.appendChild(row);
        });
    }

    function send(q) {
        return new Promise(function (done) {
            var fd = new FormData();
            fd.append('_token', token);
            fd.append('batch', root.dataset.batch);
            fd.append('pdf', q.file, q.file.name);
            var x = new XMLHttpRequest();
            x.open('POST', root.dataset.analyze);
            x.setRequestHeader('Accept', 'application/json');
            x.upload.onprogress = function (e) { if (e.lengthComputable) { q.pct = Math.round(e.loaded / e.total * 100); if (q.pct >= 100) { q.state = 'read'; q.msg = 'Reading the pages…'; } draw(); } };
            x.onload = function () {
                var r = {};
                try { r = JSON.parse(x.responseText); } catch (e) {}
                if (x.status === 200 && r.ok) {
                    q.state = 'ok';
                    q.msg = (r.issue ? 'Hachayol #' + r.issue : 'No issue number') + ' · ' + r.sections + ' section(s)' + (r.warnings && r.warnings.length ? ' · ' + r.warnings.length + ' thing(s) to check' : '');
                    document.getElementById('hyPreview').href = r.preview;
                } else {
                    q.state = 'bad';
                    q.msg = r.message || (r.errors ? Object.values(r.errors)[0][0] : (x.status === 413 ? 'The file is too big for this server.' : 'Could not read it (' + x.status + ').'));
                }
                done();
            };
            x.onerror = function () { q.state = 'bad'; q.msg = 'The upload stopped. Check the connection and try again.'; done(); };
            q.state = 'send'; q.msg = 'Uploading…'; q.pct = 0; draw();
            x.send(fd);
        });
    }

    document.getElementById('hyGo').addEventListener('click', async function () {
        busy = true; draw();
        for (var i = 0; i < queue.length; i++) {
            if (queue[i].state === 'wait') await send(queue[i]);
        }
        busy = false; draw();
        var ok = queue.filter(function (q) { return q.state === 'ok'; }).length;
        var bad = queue.filter(function (q) { return q.state === 'bad'; }).length;
        var link = document.getElementById('hyPreview');
        link.hidden = !ok;
        document.getElementById('hyNote').textContent = ok ? ok + ' ready' + (bad ? ', ' + bad + ' could not be read' : '') + '.' : 'None could be read.';
        if (ok && !bad) location.href = link.href;
    });
    document.getElementById('hyClear').addEventListener('click', function () { if (!busy) { queue = []; draw(); } });
    ['hyFiles', 'hyFolder'].forEach(function (id) {
        var input = document.getElementById(id);
        input.addEventListener('change', function () { add(input.files); input.value = ''; });
    });

    // Drag and drop: files, or whole folders (read through the browser's folder entries)
    var drop = document.getElementById('hyDrop');
    function walk(entry, path) {
        return new Promise(function (done) {
            if (entry.isFile) {
                entry.file(function (f) { f.relPath = path + f.name; done([f]); }, function () { done([]); });
            } else if (entry.isDirectory) {
                var reader = entry.createReader(), all = [];
                (function more() {
                    reader.readEntries(function (list) {
                        if (!list.length) {
                            Promise.all(all.map(function (e) { return walk(e, path + entry.name + '/'); })).then(function (r) { done([].concat.apply([], r)); });
                        } else { all = all.concat(Array.prototype.slice.call(list)); more(); }
                    }, function () { done([]); });
                })();
            } else done([]);
        });
    }
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); e.stopPropagation(); drop.classList.add('is-over'); }); });
    drop.addEventListener('dragleave', function (e) { if (!drop.contains(e.relatedTarget)) drop.classList.remove('is-over'); });
    drop.addEventListener('drop', function (e) {
        e.preventDefault(); e.stopPropagation();
        drop.classList.remove('is-over');
        var items = e.dataTransfer.items;
        if (items && items.length && items[0].webkitGetAsEntry) {
            var entries = Array.prototype.map.call(items, function (it) { return it.webkitGetAsEntry(); }).filter(Boolean);
            Promise.all(entries.map(function (en) { return walk(en, ''); })).then(function (r) { add([].concat.apply([], r)); });
        } else {
            add(e.dataTransfer.files);
        }
    });
})();
</script>
