{{-- Shared by "Upload" and "Edit": page on the site, and who can see the file. Needs $perms; $upload when editing. --}}
@php
    $roleNames = \App\Models\User::ROLES;
    $modes = \App\Models\UploadPermission::MODES;
    $months = \App\Support\HebrewDate::MONTHS;
    $old = old('perm');
    $val = function ($role, ...$path) use ($perms, $old) {
        $v = $old !== null ? ($old[$role] ?? []) : $perms[$role];
        foreach ($path as $p) { $v = is_array($v) ? ($v[$p] ?? null) : null; }
        return $v;
    };
    $isOn = fn ($role) => $role === 'hq_admin' || ($old !== null ? ! empty($old[$role]['on']) : $perms[$role]['on']);
@endphp

<div class="field">
    <label for="file_type">File type</label>
    @php $curType = old('file_type', $upload->file_type ?? ''); @endphp
    <select id="file_type" name="file_type" style="width:320px;max-width:100%" @if (! $upload || $upload->file_type !== null) required @endif>
        @if (! $upload)
            <option value="" disabled @selected($curType === '')>Choose a type…</option>
        @elseif ($upload->file_type === null)
            <option value="" @selected($curType === '')>Not set</option>
        @endif
        @foreach (\App\Models\Upload::TYPES as $t => $tLabel)
            <option value="{{ $t }}" @selected($curType === $t)>{{ $tLabel }}</option>
        @endforeach
    </select>
</div>

<div class="field">
    <label for="page_url">Page on the site (paste its web address)</label>
    <div style="display:flex;gap:8px">
        <input id="page_url" type="text" name="page_url" value="{{ old('page_url', $upload->page_url ?? '') }}" placeholder="https://tzivoshashem.com/resources/site/#/campaign" required style="flex:1" autocomplete="off" spellcheck="false">
        <button type="button" class="btn light" id="openSite">Open site</button>
    </div>
    <input type="hidden" name="page_block_id" id="page_block_id" value="{{ old('page_block_id', $upload->page_block_id ?? '') }}">
    <div class="url-check" id="urlCheck" aria-live="polite"></div>
    <div class="hint">Open the page on the Resources site, copy the address from the top of the browser and paste it here. Or press <b>Open site</b> and click the spot where the file goes.</div>
</div>

<div class="field">
    <label>Who can see it</label>
    <div class="perm-list">
        @foreach ($roleNames as $role => $label)
            <div class="perm @if ($isOn($role)) on @endif" data-role="{{ $role }}">
                <label class="perm-name">
                    <input type="checkbox" name="perm[{{ $role }}][on]" value="1" @checked($isOn($role)) @disabled($role === 'hq_admin')>
                    {{ $label }}
                </label>
                @if ($role === 'hq_admin')
                    <div class="perm-when"><span class="pill blue">Always</span> <span class="hint">HQ admin can always see every file.</span></div>
                @else
                    <div class="perm-when">
                        <select name="perm[{{ $role }}][mode]" class="mode">
                            @foreach ($modes as $m => $mLabel)
                                <option value="{{ $m }}" @selected(($val($role, 'mode') ?? 'always') === $m)>{{ $mLabel }}</option>
                            @endforeach
                        </select>
                        @foreach (['start' => 'Starts', 'end' => 'Ends'] as $which => $wLabel)
                            @php $kind = $val($role, $which, 'kind') ?? 'secular'; @endphp
                            <div class="when {{ $which }}" data-which="{{ $which }}">
                                <span class="when-label">{{ $wLabel }}</span>
                                <span class="kind">
                                    <label><input type="radio" name="perm[{{ $role }}][{{ $which }}][kind]" value="secular" @checked($kind !== 'hebrew')> Secular date</label>
                                    <label><input type="radio" name="perm[{{ $role }}][{{ $which }}][kind]" value="hebrew" @checked($kind === 'hebrew')> Hebrew date</label>
                                </span>
                                <span class="secular"><input type="date" name="perm[{{ $role }}][{{ $which }}][date]" value="{{ $val($role, $which, 'date') }}"></span>
                                <span class="hebrew">
                                    <select name="perm[{{ $role }}][{{ $which }}][hd]" class="hd" aria-label="Hebrew day">
                                        <option value="">Day</option>
                                        @for ($d = 1; $d <= 30; $d++)<option value="{{ $d }}" @selected((int) $val($role, $which, 'hd') === $d)>{{ $d }}</option>@endfor
                                    </select>
                                    <select name="perm[{{ $role }}][{{ $which }}][hm]" class="hm" aria-label="Hebrew month">
                                        <option value="">Month</option>
                                        @foreach ($months as $n => $mName)<option value="{{ $n }}" @selected((int) $val($role, $which, 'hm') === $n)>{{ $n === 7 ? 'Adar / Adar II' : $mName }}</option>@endforeach
                                    </select>
                                    <input type="number" name="perm[{{ $role }}][{{ $which }}][hy]" class="hy" value="{{ $val($role, $which, 'hy') }}" placeholder="Year (5787)" min="5600" max="6000" style="width:110px" aria-label="Hebrew year">
                                    <span class="heb-out"></span>
                                </span>
                                <span class="time"><input type="time" name="perm[{{ $role }}][{{ $which }}][time]" value="{{ $val($role, $which, 'time') ?? ($which === 'start' ? '00:00' : '23:59') }}"> <span class="hint">New York time</span></span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    <div class="hint">Levels that aren't checked can't see the file. Each checked level gets its own time setting.</div>
</div>

{{-- "Open site": browse the site's pages and click where the file goes --}}
<div class="picker" id="picker" hidden>
    <div class="picker-box">
        <div class="picker-top">
            <button type="button" class="btn light" id="pickBack" disabled>‹ Back</button>
            <select id="pickJump" aria-label="Go to a page" style="max-width:340px"><option value="">Go to a page…</option></select>
            <span class="picker-where" id="pickWhere">Loading…</span>
            <button type="button" class="btn" id="pickUse" disabled>Put the file here</button>
            <button type="button" class="btn light" id="pickClose">Close</button>
        </div>
        <div class="picker-help">Click a button on the site to go to that page. Click anywhere else on a page to pick it, then press <b>Put the file here</b>.</div>
        <iframe id="pickFrame" sandbox="allow-same-origin" title="The Resources site"></iframe>
    </div>
</div>

@push('head')
<style>
    .hint { color: #b5b5c3; font-size: 12px; margin-top: 6px; }
    .url-check { margin-top: 6px; font-weight: 500; font-size: 12.5px; min-height: 18px; }
    .url-check.good { color: #1bc5bd; }
    .url-check.bad { color: #f64e60; }
    .perm-list { border: 1px solid var(--line); border-radius: 8px; }
    .perm { display: flex; gap: 14px; padding: 12px 14px; border-top: 1px solid var(--line); align-items: flex-start; flex-wrap: wrap; }
    .perm:first-child { border-top: 0; }
    .perm-name { width: 170px; margin: 6px 0 0; font-weight: 600; display: flex; gap: 8px; align-items: center; }
    .perm-when { flex: 1; min-width: 300px; }
    .perm:not(.on) .perm-when { display: none; }
    .perm-when .mode { height: 32px; }
    .when { display: none; flex-wrap: wrap; gap: 8px 12px; align-items: center; margin-top: 8px; }
    .when.show { display: flex; }
    .when-label { width: 46px; font-weight: 600; color: #7e8299; }
    .when .kind label { display: inline-flex; gap: 4px; align-items: center; margin: 0 8px 0 0; font-weight: 400; }
    .when input[type=date], .when input[type=time], .when select, .when input[type=number] { height: 32px; }
    .when:not(.heb) .hebrew, .when.heb .secular { display: none; }
    .heb-out { color: #1bc5bd; font-size: 12px; font-weight: 500; }
    .heb-out.bad { color: #f64e60; }
    .picker { position: fixed; inset: 0; z-index: 200; background: rgba(30, 30, 45, .55); display: flex; padding: 28px; }
    .picker[hidden] { display: none; }
    .picker-box { background: #fff; border-radius: 10px; flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .picker-top { display: flex; gap: 10px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--line); }
    .picker-where { flex: 1; font-weight: 600; color: #181c32; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .picker-help { padding: 8px 16px; color: #7e8299; background: #f3f6f9; font-size: 12px; }
    #pickFrame { flex: 1; border: 0; width: 100%; background: #EDF0F6; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    // --- page address check ---
    const urlIn = document.getElementById('page_url'), out = document.getElementById('urlCheck'), blockIn = document.getElementById('page_block_id');
    let timer = null, lastChecked = null;
    function check() {
        const v = urlIn.value.trim();
        if (v === lastChecked) return; lastChecked = v;
        if (!v) { out.textContent = ''; out.className = 'url-check'; return; }
        fetch(@json(route('admin.uploads.check-url')) + '?url=' + encodeURIComponent(v), { headers: { Accept: 'application/json' } })
            .then(r => r.json()).then(j => {
                if (urlIn.value.trim() !== v) return;
                out.className = 'url-check ' + (j.ok ? 'good' : 'bad');
                out.textContent = j.ok ? '✓ ' + j.label : j.message;
            }).catch(() => {});
    }
    urlIn.addEventListener('input', () => { blockIn.value = ''; clearTimeout(timer); timer = setTimeout(check, 300); });
    check();

    // --- who can see it ---
    document.querySelectorAll('.perm').forEach(row => {
        const box = row.querySelector('input[type=checkbox]'), mode = row.querySelector('.mode');
        if (!mode) return;
        const show = () => {
            row.classList.toggle('on', box.checked);
            row.querySelector('.when.start').classList.toggle('show', ['start', 'limited'].includes(mode.value));
            row.querySelector('.when.end').classList.toggle('show', ['end', 'limited'].includes(mode.value));
        };
        box.addEventListener('change', show); mode.addEventListener('change', show); show();
        row.querySelectorAll('.when').forEach(w => {
            const heb = () => { w.classList.toggle('heb', w.querySelector('input[value=hebrew]').checked); conv(); };
            const outH = w.querySelector('.heb-out');
            const conv = () => {
                const d = w.querySelector('.hd').value, m = w.querySelector('.hm').value, y = w.querySelector('.hy').value;
                if (!w.classList.contains('heb') || !d || !m || !y) { outH.textContent = ''; return; }
                fetch(@json(route('admin.uploads.hebrew')) + `?d=${d}&m=${m}&y=${y}`, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(j => {
                    outH.className = 'heb-out' + (j.ok ? '' : ' bad'); outH.textContent = j.ok ? '= ' + j.label : j.message;
                }).catch(() => {});
            };
            w.querySelectorAll('input[type=radio]').forEach(r => r.addEventListener('change', heb));
            w.querySelectorAll('.hd,.hm,.hy').forEach(el => el.addEventListener('change', conv));
            heb();
        });
    });

    // --- "Open site" picker: shows the site's pages from this admin, buttons go to other pages ---
    const picker = document.getElementById('picker'), frame = document.getElementById('pickFrame');
    const where = document.getElementById('pickWhere'), useBtn = document.getElementById('pickUse'), backBtn = document.getElementById('pickBack'), jump = document.getElementById('pickJump');
    const PART = @json(route('admin.uploads.site.part', ['key' => '__KEY__']));
    const ASSETS = @json(asset('site-preview'));
    const IMG_HOST = 'https://tzivoshashem.com';
    const HTML_CLASS = 'on-hachayol on-rally on-chidon on-khk on-meetings on-promo on-shipment on-events on-mevorchim on-mivtza';
    const FONTS = 'https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@500;700&family=Poppins:wght@600;700;800&family=Saira+Condensed:wght@600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;1,400&display=swap';
    const CSS = '[data-go],a[href^="#"]{cursor:pointer}body{cursor:crosshair}.tzh-pick{outline:3px solid #E11B2E!important;outline-offset:2px}';
    let maps = null, current = null, history = [], picked = null, listLoaded = false;
    function loadMaps() {
        if (!maps) maps = Promise.all(['IMG', 'HW', 'NP'].map(n => fetch(ASSETS + '/' + n + '.json').then(r => r.json()).catch(() => ({}))));
        return maps;
    }
    function go(key, push) {
        where.textContent = 'Loading…'; useBtn.disabled = true; picked = null;
        fetch(PART.replace('__KEY__', encodeURIComponent(key)), { headers: { Accept: 'application/json' } }).then(r => { if (!r.ok) throw 0; return r.json(); }).then(p => {
            if (push && current) history.push(current.key);
            current = p; backBtn.disabled = history.length === 0;
            where.textContent = p.label;
            const body = p.html.replace('class="page"', 'class="page on"');
            frame.srcdoc = '<!doctype html><html lang="en" class="' + HTML_CLASS + '"><head><meta charset="utf-8"><link rel="stylesheet" href="' + FONTS + '"><link rel="stylesheet" href="' + ASSETS + '/index.css"><style>' + CSS + '</style></head><body><main id="app"><div>' + body + '</div></main></body></html>';
        }).catch(() => { if (key !== 'home') go('home', false); else where.textContent = "That page couldn't be opened."; });
    }
    frame.addEventListener('load', () => {
        const d = frame.contentDocument; if (!d || !current) return;
        loadMaps().then(([IMG, HW, NP]) => {
            const set = (sel, attr, map) => d.querySelectorAll(sel).forEach(im => { const u = map[im.getAttribute(attr) || '']; if (u) im.src = (u.startsWith('/') ? IMG_HOST : '') + u; });
            set('[data-img]', 'data-img', IMG); set('[data-hw]', 'data-hw', HW); set('[data-np]', 'data-np', NP);
        });
        d.addEventListener('submit', e => e.preventDefault(), true);
        d.addEventListener('click', e => {
            e.preventDefault();
            const t = e.target.closest ? e.target : null; if (!t) return;
            const nav = t.closest('[data-go]'), a = t.closest('a[href^="#"]');
            const key = nav ? nav.getAttribute('data-go') : (a ? a.getAttribute('href').replace(/^#\/?/, '').split(/[?\/]/)[0] : '');
            if (key) { go(key, true); return; }
            if (t.closest('[data-back]') && history.length) { backBtn.click(); return; }
            d.querySelectorAll('.tzh-pick').forEach(x => x.classList.remove('tzh-pick'));
            t.classList.add('tzh-pick');
            picked = current; useBtn.disabled = false;
            where.textContent = current.label + ' — picked';
        }, true);
    });
    backBtn.addEventListener('click', () => { const k = history.pop(); if (k) go(k, false); });
    jump.addEventListener('change', () => { if (jump.value) go(jump.value, true); jump.value = ''; });
    document.getElementById('pickClose').addEventListener('click', () => { picker.hidden = true; });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !picker.hidden) picker.hidden = true; });
    useBtn.addEventListener('click', () => {
        if (!picked) return;
        urlIn.value = picked.url; blockIn.value = picked.id; lastChecked = null; check();
        picker.hidden = true;
    });
    document.getElementById('openSite').addEventListener('click', () => {
        picker.hidden = false;
        if (!listLoaded) {
            listLoaded = true;
            fetch(@json(route('admin.uploads.site')), { headers: { Accept: 'application/json' } }).then(r => r.json()).then(list => {
                const groups = {};
                list.forEach(p => { (groups[p.page] = groups[p.page] || []).push(p); });
                for (const g in groups) {
                    const og = document.createElement('optgroup'); og.label = g;
                    groups[g].forEach(p => { const o = document.createElement('option'); o.value = p.key; o.textContent = p.label; og.appendChild(o); });
                    jump.appendChild(og);
                }
            });
        }
        // start where the address field points, or the home page
        let start = 'home';
        const m = /#\/?([^?\/]+)/.exec(urlIn.value);
        if (m) start = decodeURIComponent(m[1]);
        history = []; current = null;
        go(start, false);
    });
})();
</script>
@endpush
