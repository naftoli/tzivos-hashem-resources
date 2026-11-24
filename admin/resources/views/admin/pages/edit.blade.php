@extends('layouts.admin')

@section('title', $block->block_key . ' | ' . $page->slug . ' | Resources Admin')
@section('page-title', 'Site pages')

@section('content')
    <style>
        .crumbs { color: #b5b5c3; font-weight: 500; margin-bottom: 14px; }
        .crumbs a { color: #7e8299; }
        .meta { color: #b5b5c3; font-size: 12px; font-weight: 500; }
        .help { background: #f3f6f9; border-radius: 8px; padding: 12px 16px; margin-bottom: 14px; color: #5e6278; }
        .bar { position: sticky; top: 64px; z-index: 30; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; background: #fff; padding: 12px 26px; border-bottom: 1px solid var(--line); }
        .movebar { display: inline-flex; gap: 6px; padding-left: 10px; border-left: 1px solid var(--line); }
        .movebar[hidden] { display: none; }
        .bar .count { color: #7e8299; font-weight: 500; }
        .card-head .tabs { margin-left: auto; }
        .seg { display: inline-flex; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
        .seg button { all: unset; padding: 7px 12px; font-weight: 600; font-size: 12.5px; color: #7e8299; cursor: pointer; }
        .seg button.on { background: #e1f0ff; color: var(--blue); }
        .movebar .readout { color: #7e8299; font-weight: 500; align-self: center; min-width: 120px; }
        .movebar [hidden] { display: none; }
        .phone-wrap { background: #f3f6f9; border-radius: 8px; padding: 14px 0; }
        #preview { width: 100%; height: 300px; border: 1px solid var(--line); border-radius: 8px; background: #EDF0F6; display: block; }
        details { margin-top: 18px; border: 1px solid var(--line); border-radius: 8px; padding: 12px 16px; background: #fff; }
        summary { cursor: pointer; font-weight: 600; color: #181c32; }
        .hidden-list label { font-weight: 400; font-size: 12px; color: #7e8299; margin: 10px 0 4px; }
        .hidden-list input { width: 100%; }
        .hidden-list input.changed { background: #fff3b0; }
        textarea { width: 100%; min-height: 60vh; padding: 12px; border: 1px solid #e4e6ef; border-radius: 8px; background: #f9fafc; font: 13px/1.45 ui-monospace, Consolas, monospace; white-space: pre-wrap; }
    </style>
    <div class="crumbs"><a href="{{ route('admin.pages') }}">Site pages</a> › <a href="{{ route('admin.pages.show', $page) }}">{{ $page->slug }}</a> › {{ $block->block_key }}</div>

    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="card">
        <div class="card-head">
            <div>
                <h2>{{ $page->slug }} / {{ $block->block_key }}</h2>
                <div class="meta">
                    {{ $block->heading() }}
                    @if ($block->edited_at)
                        · Last edited {{ $block->edited_at->format('M j, Y g:i A') }}@if ($block->editor) by {{ $block->editor->email }}@endif
                    @endif
                </div>
            </div>
            <nav class="tabs modes"><button type="button" class="mode on" id="modeWords">Change words</button><button type="button" class="mode" id="modeMove">Move things</button></nav>
        </div>

        <form id="textForm" method="POST" action="{{ route('admin.pages.block.text', [$page, $block]) }}" style="margin:0">
            @csrf
            @method('PUT')
            <input type="hidden" name="base" value="{{ $base }}">
            <input type="hidden" name="changes" id="changesField" value="{}">
            <input type="hidden" name="order" id="orderField" value="{}">
            <input type="hidden" name="nudges" id="nudgesField" value="{}">
            <div class="bar">
                <button type="submit" class="btn" id="saveBtn" disabled>Save</button>
                <button type="button" class="btn light" id="stepUndo" disabled title="Undo the last step (Ctrl+Z)">↶ Undo</button>
                <button type="button" class="btn light" id="stepRedo" disabled title="Redo the step you undid (Ctrl+Y)">↷ Redo</button>
                <button type="button" class="btn light" id="undoBtn" disabled>Undo all my changes</button>
                <span class="movebar" id="moveBar" hidden>
                    <span class="seg" title="What dragging with the mouse does"><button type="button" class="on" data-sub="free" id="subFree">Move freely</button><button type="button" data-sub="swap" id="subSwap">Swap places</button></span>
                    <button type="button" class="btn light" data-act="earlier" data-only="swap" hidden disabled>⬆ Earlier</button><button type="button" class="btn light" data-act="later" data-only="swap" hidden disabled>Later ⬇</button><button type="button" class="btn light" data-act="up" disabled>Pick the box around it</button><button type="button" class="btn light" data-act="reset" data-only="free" disabled>Reset position</button>
                    <span class="readout" id="readout" data-only="free"></span>
                </span>
                <span class="count" id="count">Loading the preview…</span>
                <span class="seg" style="margin-left:auto" title="Preview width"><button type="button" class="on" id="viewDesk">Desktop</button><button type="button" id="viewPhone">Phone</button></span>
            </div>
        </form>
        <form id="undoForm" method="POST" action="{{ route('admin.pages.block.undo', [$page, $block]) }}" hidden>@csrf</form>

        <div class="card-body">
            <div class="help" id="helpWords">Click on any words below and type to change them. Only the words change; the layout stays the same. Links and buttons don't open here. When you're done, press <b>Save</b>.</div>
            <div class="help" id="helpMove" hidden><span data-only="free"><b>Move freely:</b> hold the mouse down on any line of text or box and drag it a little, or as far as you like, in any direction. Or click it and use the arrow keys (1 pixel per press, hold <b>Shift</b> for 10). To move the bigger box it sits in, press <b>Pick the box around it</b>. <b>Reset position</b> puts it back exactly where it was.</span><span data-only="swap" hidden><b>Swap places:</b> click a box, button or piece of text (red outline marks what can swap). Then use <b>Earlier</b> / <b>Later</b>, or drag it onto a neighbor to swap them.</span> Nothing is added or removed. <b>Undo</b> / <b>Redo</b> go one step at a time. When you're done, press <b>Save</b>.</div>

            <div id="previewWrap"><iframe id="preview" sandbox="allow-same-origin" title="Preview of {{ $block->block_key }}"></iframe></div>

            <details id="hiddenBox" class="hidden-list" hidden>
                <summary id="hiddenSummary">Text that isn't shown in the preview</summary>
                <p style="color:#7e8299">This text is in this part but hidden on screen (for example in a pop-up or a tab). You can change it here.</p>
                <div id="hiddenFields"></div>
            </details>

            <details id="codeBox" @if (request('code') || $errors->has('html')) open @endif>
                <summary>Advanced: edit code</summary>
                <p style="color:#7e8299">For experts only. This shows the page code (HTML). Saving here replaces the whole part with exactly what's in the box.</p>
                <form method="POST" action="{{ route('admin.pages.block.update', [$page, $block]) }}">
                    @csrf
                    @method('PUT')
                    <textarea id="html" name="html" spellcheck="false">{{ $block->html }}</textarea>
                    <p style="margin-top:14px"><button type="submit" class="btn">Save code</button></p>
                </form>
            </details>
        </div>
    </div>

    <script>
    (function () {
        const TEXTS = @json($texts);
        const LOCKED = new Set(@json(array_values($locked ?? [])));
        const SAVED_UNDO = @json((int) ($savedUndo ?? 0));
        const MARKED = @json($marked);
        const GROUP_SIZES = @json($groupSizes);
        const ELS = @json($elInfo); // per element that can be nudged: [tag, style without our nudge (null = none), x, y, kind]
        const ASSETS = @json(asset('site-preview'));
        const IMG_HOST = 'https://tzivoshashem.com';
        const HTML_CLASS = 'on-hachayol on-rally on-chidon on-khk on-meetings on-promo on-shipment on-events on-mevorchim on-mivtza';
        const FONTS = 'https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@500;700&family=Poppins:wght@600;700;800&family=Saira+Condensed:wght@600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400&display=swap';
        const EDIT_CSS = '.tzh-edit{cursor:text;-webkit-user-select:text;user-select:text;border-radius:2px}'
            + '.tzh-edit:hover{outline:1px dashed rgba(27,79,216,.7);outline-offset:1px}'
            + '.tzh-edit:focus{outline:2px solid #1B4FD8;outline-offset:1px}'
            + '.tzh-edit.tzh-changed{background:rgba(255,214,0,.45)}'
            + 'a,button{cursor:text}'
            + '.tzh-swap [data-tzh-g]{outline:1px dashed rgba(225,27,46,.55);outline-offset:-1px;cursor:pointer}'
            + '.tzh-free [data-tze]{cursor:move;touch-action:none}'
            + '.tzh-free .tzh-hov{outline:1px dashed rgba(225,27,46,.85);outline-offset:1px}'
            + '.tzh-nudged{outline:1px dotted #f59e0b}'
            + '.tzh-move .tzh-edit:hover{outline:none}'
            + '.tzh-move,.tzh-move *{-webkit-user-select:none!important;user-select:none!important}'
            + '.tzh-swap [data-tzh-g]{cursor:grab;touch-action:none}'
            + '.tzh-move.tzh-dragging,.tzh-move.tzh-dragging *{cursor:grabbing!important}'
            + '.tzh-move .tzh-lift{opacity:.75}'
            + '.tzh-move .tzh-sel{outline:3px solid #E11B2E!important;outline-offset:2px}'
            + '.tzh-move .tzh-over{box-shadow:0 0 0 3px #1B4FD8!important}'
            + '.tzh-moved{box-shadow:inset 0 0 0 9999px rgba(255,214,0,.18)}'
            + '#tzh-tools{position:absolute;z-index:2147483647;display:none;gap:4px;background:#1b283f;padding:4px;border-radius:6px;font:600 13px system-ui,sans-serif;box-shadow:0 4px 14px rgba(0,0,0,.3)}'
            + '#tzh-tools button{all:unset;background:#fff;color:#1b283f;padding:4px 9px;border-radius:4px;cursor:pointer;white-space:nowrap}'
            + '#tzh-tools button:disabled{opacity:.4;cursor:default}';

        const frame = document.getElementById('preview');
        const form = document.getElementById('textForm');
        const saveBtn = document.getElementById('saveBtn');
        const undoBtn = document.getElementById('undoBtn');
        const stepUndo = document.getElementById('stepUndo');
        const stepRedo = document.getElementById('stepRedo');
        const countEl = document.getElementById('count');
        const field = document.getElementById('changesField');
        const orderField = document.getElementById('orderField');
        const nudgesField = document.getElementById('nudgesField');
        const readout = document.getElementById('readout');
        const moveEls = {};   // element number -> element in the preview that can be nudged
        const off = {};       // element number -> [x, y, kind] when different from how it was loaded
        let sub = 'free';     // in Move things: 'free' (drag anywhere) or 'swap' (swap with neighbors)
        let nudging = false, nudgeTimer = null;
        const groupOk = {};   // group number -> its parent element in the preview, if it can be moved
        let mode = 'words';
        let sel = null, tools = null, dragging = null;
        const editors = {};   // piece number -> element holding its text (span in preview, or input)
        let submitting = false;

        // Preview only: the site shows a page once its class has "on" (same as the live site does)
        const body = MARKED.replace('class="page"', 'class="page on"');
        frame.srcdoc = '<!doctype html><html lang="en" class="' + HTML_CLASS + '"><head><meta charset="utf-8">'
            + '<link rel="stylesheet" href="' + FONTS + '"><link rel="stylesheet" href="' + ASSETS + '/index.css">'
            + '<style>' + EDIT_CSS + '</style></head><body><main id="app"><div>' + body + '</div></main></body></html>';

        function changes() {
            const out = {};
            for (const i in editors) {
                const el = editors[i];
                const now = el.tagName === 'INPUT' ? el.value : el.textContent;
                if (now !== TEXTS[i]) out[i] = now;
            }
            return out;
        }
        function itemsOf(g) {
            const par = groupOk[g];
            return par ? Array.from(par.children).filter(el => el.dataset.tzhG === String(g)) : [];
        }
        function orders() {
            const out = {};
            for (const g in groupOk) {
                const perm = itemsOf(g).map(el => +el.dataset.tzhK);
                if (perm.some((k, i) => k !== i)) out[g] = perm;
            }
            return out;
        }
        // Free moving: x/y pixels kept in the element's own style (same form the server writes)
        const REPLACED = ['IMG', 'VIDEO', 'CANVAS', 'IFRAME', 'INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'OBJECT', 'EMBED', 'svg', 'SVG', 'PICTURE', 'AUDIO'];
        function cur(n) { return off[n] || [ELS[n][2], ELS[n][3], ELS[n][4]]; }
        function kindFor(el) {
            const n = el.dataset.tze, k = cur(n)[2];
            if (k) return k;
            const cs = el.ownerDocument.defaultView.getComputedStyle(el);
            if (cs.display === 'contents' || cs.display === 'none') return null;
            if (cs.display === 'inline' && !REPLACED.includes(el.tagName)) return cs.position === 'static' ? 'p' : null;
            return cs.translate && cs.translate !== 'none' ? null : 't';
        }
        function render(n) {
            const el = moveEls[n]; if (!el) return;
            const base = ELS[n][1], [x, y, k] = cur(n);
            const ours = x || y ? (k === 'p' ? 'position:relative;left:' + x + 'px;top:' + y + 'px' : 'translate:' + x + 'px ' + y + 'px') : '';
            if (base === null) { if (ours) el.setAttribute('style', ours); else el.removeAttribute('style'); }
            else el.setAttribute('style', base + (ours ? ';' + ours : ''));
        }
        function setOffset(el, x, y) {
            const n = el.dataset.tze, k = kindFor(el);
            if (!k) return false;
            off[n] = [Math.round(x), Math.round(y), k];
            render(n);
            return true;
        }
        function nudges() {
            const out = {};
            for (const n in off) {
                const [x, y, k] = off[n], [ix, iy, ik] = [ELS[n][2], ELS[n][3], ELS[n][4]];
                if (x !== ix || y !== iy || ((x || y) && k !== ik)) out[n] = [x, y, k];
            }
            return out;
        }
        function showReadout() {
            if (!sel || sel.dataset.tze === undefined) { readout.textContent = sel ? "This one can't move freely" : 'Pick something to move'; return; }
            const [x, y] = cur(sel.dataset.tze);
            const h = x === 0 ? '' : Math.abs(x) + ' px ' + (x > 0 ? 'right' : 'left');
            const v = y === 0 ? '' : Math.abs(y) + ' px ' + (y > 0 ? 'down' : 'up');
            readout.textContent = h || v ? [h, v].filter(Boolean).join(', ') : 'In its normal spot';
        }
        function refresh() {
            const ch = changes();
            const or = orders();
            const nu = nudges();
            for (const n in moveEls) moveEls[n].classList.toggle('tzh-nudged', n in nu);
            for (const g in groupOk) itemsOf(g).forEach((el, i) => el.classList.toggle('tzh-moved', g in or && +el.dataset.tzhK !== i));
            const n = Object.keys(ch).length + Object.keys(or).length + Object.keys(nu).length;
            for (const i in editors) editors[i].classList.toggle(editors[i].tagName === 'INPUT' ? 'changed' : 'tzh-changed', i in ch);
            saveBtn.disabled = n === 0;
            undoBtn.disabled = n === 0;
            stepUndo.disabled = past.length === 0 && SAVED_UNDO === 0;
            stepRedo.disabled = future.length === 0;
            countEl.textContent = n === 0 ? 'No changes yet.' : n + (n === 1 ? ' change' : ' changes') + ' not saved yet.';
            return [ch, or, nu];
        }
        function fit() {
            const d = frame.contentDocument;
            if (d && d.documentElement) frame.style.height = Math.max(200, d.documentElement.scrollHeight) + 'px';
        }

        function placeTools() {
            const d = frame.contentDocument;
            const mb = document.getElementById('moveBar');
            mb.hidden = mode !== 'move';
            const can = { earlier: false, later: false, up: false, reset: false, done: true };
            if (sel && mode === 'move') {
                const its = itemsOf(sel.dataset.tzhG), j = its.indexOf(sel);
                can.earlier = j > 0; can.later = j < its.length - 1;
                can.up = !!(sel.parentElement && sel.parentElement.closest(sub === 'free' ? '[data-tze]' : '[data-tzh-g]'));
                if (sel.dataset.tze !== undefined) { const c = cur(sel.dataset.tze); can.reset = !!(c[0] || c[1]); }
            }
            document.querySelectorAll('[data-only]').forEach(b => { b.hidden = b.dataset.only !== sub; });
            mb.querySelectorAll('[data-act]').forEach(b => { b.disabled = !can[b.dataset.act]; });
            showReadout();
            if (!tools) return;
            tools.querySelectorAll('[data-act]').forEach(b => { b.disabled = !can[b.dataset.act]; b.style.display = b.dataset.only && b.dataset.only !== sub ? 'none' : ''; });
            if (!sel || mode !== 'move') { tools.style.display = 'none'; return; }
            const r = sel.getBoundingClientRect();
            tools.style.display = 'flex';
            tools.style.top = Math.max(0, r.top + d.defaultView.scrollY - 36) + 'px';
            tools.style.left = Math.max(0, r.left + d.defaultView.scrollX) + 'px';
        }
        function select(el) {
            if (sel) sel.classList.remove('tzh-sel');
            sel = el || null;
            if (sel) sel.classList.add('tzh-sel');
            placeTools();
        }
        function moved() { refresh(); fit(); placeTools(); }

        // Undo / Redo, one step at a time. A step = one move, or one stretch of typing.
        const past = [], future = [];
        let typing = false, typingTimer = null;
        function snapshot() {
            const order = {}, text = {};
            for (const g in groupOk) order[g] = itemsOf(g);
            for (const i in editors) text[i] = editors[i].tagName === 'INPUT' ? editors[i].value : editors[i].textContent;
            const offs = {};
            for (const n in off) offs[n] = off[n].slice();
            return { order, text, offs };
        }
        function restore(st) {
            for (const g in st.order) {
                const now = itemsOf(g), want = st.order[g];
                if (now.length !== want.length || now.every((el, k) => el === want[k])) continue;
                const marks = now.map(el => { const c = el.ownerDocument.createComment(''); el.parentNode.insertBefore(c, el); return c; });
                marks.forEach((c, k) => { c.parentNode.insertBefore(want[k], c); c.remove(); });
            }
            for (const i in st.text) {
                const el = editors[i]; if (!el) continue;
                if (el.tagName === 'INPUT') { if (el.value !== st.text[i]) el.value = st.text[i]; }
                else if (el.textContent !== st.text[i]) el.textContent = st.text[i];
            }
            for (const n of new Set([...Object.keys(off), ...Object.keys(st.offs)])) {
                if (n in st.offs) off[n] = st.offs[n].slice(); else delete off[n];
                render(n);
            }
        }
        function checkpoint() { past.push(snapshot()); future.length = 0; if (past.length > 500) past.shift(); }
        function endTyping() { typing = false; clearTimeout(typingTimer); nudging = false; clearTimeout(nudgeTimer); }
        // Arrow keys nudge the picked thing: 1 px, or 10 px with Shift. One run of presses = one Undo step.
        function nudgeKeys(e) {
            const dir = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
            if (!dir || mode !== 'move' || !sel || sel.dataset.tze === undefined || e.ctrlKey || e.metaKey || e.altKey) return;
            e.preventDefault();
            if (!kindFor(sel)) { showReadout(); return; }
            if (!nudging) { endTyping(); checkpoint(); nudging = true; }
            clearTimeout(nudgeTimer); nudgeTimer = setTimeout(() => { nudging = false; }, 1000);
            const step = e.shiftKey ? 10 : 1, [x, y] = cur(sel.dataset.tze);
            setOffset(sel, x + dir[0] * step, y + dir[1] * step);
            moved();
        }
        function beforeTyping() {
            if (!typing) { checkpoint(); typing = true; }
            clearTimeout(typingTimer); typingTimer = setTimeout(endTyping, 1200);
        }
        function undoStep() {
            endTyping();
            if (past.length) { future.push(snapshot()); restore(past.pop()); moved(); return; }
            if (SAVED_UNDO > 0) { submitting = true; document.getElementById('undoForm').submit(); }
        }
        function redoStep() { endTyping(); if (!future.length) return; past.push(snapshot()); restore(future.pop()); moved(); }
        function historyKeys(e) {
            if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
            const k = (e.key || '').toLowerCase();
            if (k === 'z' && !e.shiftKey) { e.preventDefault(); undoStep(); }
            else if (k === 'y' || (k === 'z' && e.shiftKey)) { e.preventDefault(); redoStep(); }
        }
        document.addEventListener('keydown', e => {
            if (e.target.closest && e.target.closest('#codeBox')) return;
            historyKeys(e);
            if (!(e.target.closest && e.target.closest('input,textarea,select,[contenteditable]'))) nudgeKeys(e);
        });
        stepUndo.addEventListener('click', undoStep);
        stepRedo.addEventListener('click', redoStep);
        function act(a) {
            if (!sel || !a) return;
            const items = itemsOf(sel.dataset.tzhG), i = items.indexOf(sel);
            if (a === 'earlier' && i > 0) { endTyping(); checkpoint(); sel.parentNode.insertBefore(sel, items[i - 1]); moved(); }
            if (a === 'later' && i < items.length - 1) { endTyping(); checkpoint(); sel.parentNode.insertBefore(items[i + 1], sel); moved(); }
            if (a === 'up') select(sel.parentElement && sel.parentElement.closest(sub === 'free' ? '[data-tze]' : '[data-tzh-g]'));
            if (a === 'reset' && sel.dataset.tze !== undefined) { const c = cur(sel.dataset.tze); if (c[0] || c[1]) { endTyping(); checkpoint(); setOffset(sel, 0, 0); moved(); } }
            if (a === 'done') select(null);
        }
        document.getElementById('moveBar').addEventListener('click', e => { const b = e.target.closest('[data-act]'); if (b) act(b.dataset.act); });
        function setMode(m) {
            mode = m;
            const d = frame.contentDocument;
            document.getElementById('modeWords').classList.toggle('on', m === 'words');
            document.getElementById('modeMove').classList.toggle('on', m === 'move');
            document.getElementById('helpWords').hidden = m !== 'words';
            document.getElementById('helpMove').hidden = m !== 'move';
            if (!d || !d.body) return;
            d.body.classList.toggle('tzh-move', m === 'move');
            d.body.classList.toggle('tzh-free', m === 'move' && sub === 'free');
            d.body.classList.toggle('tzh-swap', m === 'move' && sub === 'swap');
            d.querySelectorAll('.tzh-hov').forEach(x => x.classList.remove('tzh-hov'));
            d.querySelectorAll('.tzh-edit[data-piece]').forEach(s => s.setAttribute('contenteditable', m === 'words' ? 'plaintext-only' : 'false'));
            if (m === 'words') select(null);
            placeTools();
        }
        document.getElementById('modeWords').addEventListener('click', () => setMode('words'));
        document.getElementById('modeMove').addEventListener('click', () => setMode('move'));
        function setSub(s) {
            sub = s;
            document.getElementById('subFree').classList.toggle('on', s === 'free');
            document.getElementById('subSwap').classList.toggle('on', s === 'swap');
            if (s === 'swap' && sel && !sel.closest('[data-tzh-g]')) select(null);
            else if (s === 'swap' && sel) select(sel.closest('[data-tzh-g]'));
            setMode(mode);
        }
        document.getElementById('subFree').addEventListener('click', () => setSub('free'));
        document.getElementById('subSwap').addEventListener('click', () => setSub('swap'));
        // Preview width: the site's own phone layout shows when the preview is narrow
        function setView(phone) {
            document.getElementById('viewDesk').classList.toggle('on', !phone);
            document.getElementById('viewPhone').classList.toggle('on', phone);
            frame.style.width = phone ? '390px' : '100%';
            frame.style.margin = phone ? '0 auto' : '';
            document.getElementById('previewWrap').classList.toggle('phone-wrap', phone);
            setTimeout(() => { fit(); placeTools(); }, 50);
        }
        document.getElementById('viewDesk').addEventListener('click', () => setView(false));
        document.getElementById('viewPhone').addEventListener('click', () => setView(true));

        frame.addEventListener('load', function () {
            const d = frame.contentDocument;

            // Pictures: same lookup the live site does; missing ones just stay blank
            Promise.all(['IMG', 'HW', 'NP'].map(n => fetch(ASSETS + '/' + n + '.json').then(r => r.json()).catch(() => ({}))))
                .then(([IMG, HW, NP]) => {
                    const set = (sel, attr, map) => d.querySelectorAll(sel).forEach(im => {
                        const u = map[im.getAttribute(attr) || ''];
                        if (u) im.src = (u.startsWith('/') ? IMG_HOST : '') + u;
                    });
                    set('[data-img]', 'data-img', IMG);
                    set('[data-hw]', 'data-hw', HW);
                    set('[data-np]', 'data-np', NP);
                });

            // Find each marked piece of text and make just that text editable
            const walker = d.createTreeWalker(d.body, NodeFilter.SHOW_COMMENT);
            const marks = [], gmarks = [], emarks = [];
            while (walker.nextNode()) {
                const m = /^tzh:(\d+)$/.exec(walker.currentNode.data);
                if (m) marks.push([walker.currentNode, +m[1]]);
                const gm = /^tzg:(\d+):(\d+)$/.exec(walker.currentNode.data);
                if (gm) gmarks.push([walker.currentNode, +gm[1], +gm[2]]);
                const em = /^tze:(\d+)$/.exec(walker.currentNode.data);
                if (em) emarks.push([walker.currentNode, +em[1]]);
            }
            // Movable groups: every item must be found, in order, side by side in one parent, and visible
            const gItems = {};
            for (const [c, g, k] of gmarks) {
                const el = c.nextSibling;
                c.remove();
                (gItems[g] = gItems[g] || [])[k] = el && el.nodeType === 1 ? el : null;
            }
            GROUP_SIZES.forEach((size, g) => {
                const els = gItems[g] || [];
                const par = els[0] && els[0].parentNode;
                let ok = els.length === size && els.every(el => el && el.parentNode === par);
                if (ok) {
                    const kids = Array.from(par.children);
                    ok = els.every((el, k) => k === 0 || kids.indexOf(el) > kids.indexOf(els[k - 1]))
                        && els.every(el => el.getClientRects().length > 0);
                }
                if (ok) {
                    els.forEach((el, k) => { el.dataset.tzhG = g; el.dataset.tzhK = k; });
                    groupOk[g] = par;
                }
            });
            // Elements that can be nudged (marker is right before each, after the item markers are gone)
            for (const [c, n] of emarks) {
                let el = c.nextSibling;
                while (el && el.nodeType === 8 && /^tz[gh]:/.test(el.data)) el = el.nextSibling;
                c.remove();
                if (el && el.nodeType === 1 && el.tagName.toLowerCase() === ELS[n][0]) { el.dataset.tze = n; moveEls[n] = el; }
            }
            const offscreen = [];
            const found = new Set();
            for (const [c, i] of marks) {
                const t = c.nextSibling;
                c.remove();
                if (LOCKED.has(i)) { found.add(i); continue; }
                if (t && t.nodeType === 3 && t.data === TEXTS[i]) {
                    const s = d.createElement('span');
                    s.className = 'tzh-edit';
                    s.setAttribute('contenteditable', 'plaintext-only');
                    s.setAttribute('spellcheck', 'true');
                    s.dataset.piece = i;
                    t.parentNode.insertBefore(s, t);
                    s.appendChild(t);
                    editors[i] = s;
                    found.add(i);
                }
            }
            // Text that isn't visible on screen (or couldn't be placed) gets a plain box below
            for (const i in editors) {
                if (editors[i].getClientRects().length === 0) {
                    editors[i].removeAttribute('contenteditable');
                    editors[i].removeAttribute('data-piece');
                    delete editors[i];
                    found.delete(+i);
                }
            }
            TEXTS.forEach((t, i) => { if (!found.has(i)) offscreen.push(i); });
            if (offscreen.length) {
                const box = document.getElementById('hiddenFields');
                offscreen.forEach(i => {
                    const lab = document.createElement('label');
                    lab.textContent = 'Text ' + (i + 1);
                    const inp = document.createElement('input');
                    inp.type = 'text'; inp.dir = 'auto'; inp.value = TEXTS[i]; inp.dataset.piece = i;
                    inp.addEventListener('beforeinput', beforeTyping);
                    inp.addEventListener('input', refresh);
                    inp.addEventListener('blur', endTyping);
                    box.appendChild(lab); box.appendChild(inp);
                    editors[i] = inp;
                });
                document.getElementById('hiddenSummary').textContent = "Text that isn't shown in the preview (" + offscreen.length + ')';
                document.getElementById('hiddenBox').hidden = false;
            }

            // Keep the layout safe: no new lines, no dropped-in content, links and buttons don't go anywhere
            d.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); historyKeys(e); if (mode === 'move') nudgeKeys(e); }, true);
            d.addEventListener('beforeinput', e => {
                if (e.inputType === 'historyUndo' || e.inputType === 'historyRedo') { e.preventDefault(); return; }
                beforeTyping();
            }, true);
            d.addEventListener('focusout', endTyping, true);
            d.addEventListener('click', e => {
                if (tools && tools.contains(e.target)) return;
                if (e.target.closest && e.target.closest('a,button,label,summary,form')) e.preventDefault();
                if (mode === 'move') e.preventDefault(); // picking happens on mouse down (below)
            }, true);
            d.addEventListener('submit', e => e.preventDefault(), true);
            d.addEventListener('dragstart', e => { if (mode === 'move') e.preventDefault(); }, true);

            // Move mode: press on an item to pick it; keep holding and drag to move it among its neighbors
            let press = null;
            const itemAt = (x, y, g, par) => {
                const hit = d.elementFromPoint(x, y);
                const t = hit && hit.closest ? hit.closest('[data-tzh-g="' + g + '"]') : null;
                return t && t.parentNode === par ? t : null;
            };
            d.addEventListener('pointerdown', e => {
                if (mode !== 'move' || e.button !== 0 || (tools && tools.contains(e.target))) return;
                e.preventDefault();
                if (sub === 'free') {
                    const inner = e.target.closest ? e.target.closest('[data-tze]') : null;
                    const item = sel && inner && sel.contains(inner) && sel.dataset.tze !== undefined ? sel : inner;
                    select(item);
                    if (!item) return;
                    const [x0, y0] = cur(item.dataset.tze);
                    press = { free: true, id: e.pointerId, x: e.clientX, y: e.clientY, item, on: false, x0, y0 };
                    try { d.documentElement.setPointerCapture(e.pointerId); } catch (_) {}
                    return;
                }
                const inner = e.target.closest ? e.target.closest('[data-tzh-g]') : null;
                const item = sel && inner && sel.contains(inner) ? sel : inner; // keep a picked outer box when pressing inside it
                select(item);
                if (!item) return;
                press = { id: e.pointerId, x: e.clientX, y: e.clientY, item, on: false, last: null };
                try { d.documentElement.setPointerCapture(e.pointerId); } catch (_) {} // the page itself holds the pointer, so moving the item never drops it
            }, true);
            let hov = null;
            d.addEventListener('pointermove', e => {
                if (!press && mode === 'move' && sub === 'free') {
                    const h = e.target.closest ? e.target.closest('[data-tze]') : null;
                    if (h !== hov) { if (hov) hov.classList.remove('tzh-hov'); hov = h; if (hov) hov.classList.add('tzh-hov'); }
                }
                if (!press || e.pointerId !== press.id) return;
                if (press.free) {
                    const dx = e.clientX - press.x, dy = e.clientY - press.y;
                    if (!press.on) {
                        if (Math.abs(dx) + Math.abs(dy) < 3) return;
                        if (!kindFor(press.item)) { press = null; showReadout(); return; }
                        press.on = true;
                        endTyping(); checkpoint();
                        d.body.classList.add('tzh-dragging');
                        if (tools) tools.style.display = 'none';
                    }
                    e.preventDefault();
                    setOffset(press.item, press.x0 + dx, press.y0 + dy);
                    showReadout();
                    return;
                }
                if (!press.on) {
                    if (Math.abs(e.clientX - press.x) + Math.abs(e.clientY - press.y) < 6) return;
                    press.on = true;
                    d.body.classList.add('tzh-dragging');
                    if (tools) tools.style.display = 'none';
                }
                e.preventDefault();
                if (!dragging) {
                    // Which thing is being dragged: the pressed piece, or the box around it, whichever has a neighbor under the pointer
                    for (let c = press.item; c; c = c.parentElement && c.parentElement.closest('[data-tzh-g]')) {
                        const t = itemAt(e.clientX, e.clientY, c.dataset.tzhG, c.parentNode);
                        if (t && t !== c) { dragging = c; break; }
                    }
                    if (!dragging) return;
                    select(dragging); if (tools) tools.style.display = 'none';
                    dragging.classList.add('tzh-lift');
                }
                const t = itemAt(e.clientX, e.clientY, dragging.dataset.tzhG, dragging.parentNode);
                if (!t || t === dragging) { press.last = null; return; }
                if (t === press.last) return; // wait until the pointer reaches a different neighbor
                press.last = t;
                if (!press.saved) { endTyping(); checkpoint(); press.saved = true; }
                const items = itemsOf(dragging.dataset.tzhG);
                if (items.indexOf(dragging) < items.indexOf(t)) t.parentNode.insertBefore(dragging, t.nextSibling);
                else t.parentNode.insertBefore(dragging, t);
                refresh(); fit();
            }, true);
            const endPress = e => {
                if (!press || (e && e.pointerId !== press.id)) return;
                if (press.free) {
                    if (press.on) { d.body.classList.remove('tzh-dragging'); moved(); }
                    try { d.documentElement.releasePointerCapture(press.id); } catch (_) {}
                    press = null; placeTools();
                    return;
                }
                if (press.on) { d.body.classList.remove('tzh-dragging'); if (dragging) dragging.classList.remove('tzh-lift'); moved(); }
                try { d.documentElement.releasePointerCapture(press.id); } catch (_) {}
                press = null; dragging = null;
            };
            d.addEventListener('pointerup', endPress, true);
            d.addEventListener('pointercancel', endPress, true);

            // Floating Earlier / Later tools (preview only, never saved)
            tools = d.createElement('div');
            tools.id = 'tzh-tools';
            tools.innerHTML = '<button type="button" data-act="earlier" data-only="swap">⬆ Earlier</button><button type="button" data-act="later" data-only="swap">Later ⬇</button><button type="button" data-act="up">Pick the box around it</button><button type="button" data-act="reset" data-only="free">Reset position</button><button type="button" data-act="done">Done</button>';
            tools.addEventListener('mousedown', e => e.preventDefault());
            tools.addEventListener('pointerdown', e => e.preventDefault());
            tools.addEventListener('click', e => {
                e.preventDefault(); e.stopPropagation();
                const b = e.target.closest && e.target.closest('[data-act]');
                if (b) act(b.dataset.act);
            });
            d.body.appendChild(tools);
            window.tzhGroups = { total: GROUP_SIZES.length, usable: Object.keys(groupOk).length };
            d.addEventListener('input', () => { refresh(); fit(); }, true);
            d.addEventListener('load', fit, true);
            if (window.ResizeObserver) new ResizeObserver(fit).observe(d.documentElement);
            fit();
            refresh();
            window.tzhReady = { pieces: TEXTS.length, inPreview: Object.keys(editors).length - offscreen.length, offscreen: offscreen.length };
        });

        form.addEventListener('submit', function (e) {
            const [ch, or, nu] = refresh();
            if (!Object.keys(ch).length && !Object.keys(or).length && !Object.keys(nu).length) { e.preventDefault(); return; }
            field.value = JSON.stringify(ch);
            orderField.value = JSON.stringify(or);
            nudgesField.value = JSON.stringify(nu);
            submitting = true;
        });
        undoBtn.addEventListener('click', function () { submitting = true; location.reload(); });
        window.addEventListener('beforeunload', function (e) {
            if (!submitting && (Object.keys(changes()).length || Object.keys(orders()).length || Object.keys(nudges()).length)) { e.preventDefault(); e.returnValue = ''; }
        });
        window.tzhChanges = changes;
        window.tzhOrders = orders;
        window.tzhNudges = nudges;
    })();
    </script>
@endsection
