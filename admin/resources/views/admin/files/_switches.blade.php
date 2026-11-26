{{-- Files › Full and features: Full and each feature with an on/off switch. Click one to open it: its switch and Choose weeks (a feature also has who can see it).
     Needs $switches (weeks, features, state), $rules, $levels. --}}
@php
    $items = array_merge([['id' => 'hachayol', 'name' => 'Full', 'scope' => 'full']], array_map(fn ($f) => $f + ['scope' => 'feature'], $switches['features']));
    $icon = [
        'full' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5V5a2 2 0 0 1 2-2h12v16H6a2 2 0 0 0-2 2z"/><path d="M8 7h6M8 11h8"/></svg>',
        'feature' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
    ];
@endphp
<div id="switches" class="sw">
    <div class="sw-view" id="swList">
        <div class="fx-section-head">
            <h2>Full and features</h2>
            <span class="sw-status" id="swStatus" aria-live="polite"></span>
        </div>
        @foreach ($items as $it)
            @php $key = $it['scope'].'-'.$it['id']; @endphp
            @if ($it['scope'] === 'full')
                <div class="sw-full" data-card="{{ $key }}">
                    <div class="sw-full-icon">{!! $icon['full'] !!}</div>
                    <div class="sw-full-main">
                        <button type="button" class="sw-open" data-open="{{ $key }}">
                            <span class="sw-name">{{ $it['name'] }}</span>
                            <span class="sw-pill" data-state="{{ $key }}"></span>
                        </button>
                        <div class="sw-cover"><span class="sw-bar"><span data-bar="{{ $key }}"></span></span><span class="sw-sub" data-sub="{{ $key }}"></span></div>
                    </div>
                    <div class="sw-full-side">
                        <button type="button" class="switch switch-lg" role="switch" aria-label="{{ $it['name'] }}" data-switch="{{ $key }}"><span class="knob"></span></button>
                        <button type="button" class="sw-link" data-weeks="{{ $key }}" data-name="{{ $it['name'] }}">Choose weeks →</button>
                    </div>
                </div>
                <div class="sw-grid">
            @else
                    <div class="sw-card" data-card="{{ $key }}">
                        <div class="sw-card-top">
                            <span class="sw-ico">{!! $icon['feature'] !!}</span>
                            <button type="button" class="switch" role="switch" aria-label="{{ $it['name'] }}" data-switch="{{ $key }}"><span class="knob"></span></button>
                        </div>
                        <button type="button" class="sw-open" data-open="{{ $key }}">
                            <span class="sw-name">{{ $it['name'] }}</span>
                            <span class="sw-chev">›</span>
                        </button>
                        <span class="sw-pill" data-state="{{ $key }}"></span>
                        <div class="sw-cover"><span class="sw-bar"><span data-bar="{{ $key }}"></span></span><span class="sw-sub" data-sub="{{ $key }}"></span></div>
                    </div>
            @endif
        @endforeach
                </div>
    </div>

    @foreach ($items as $it)
        @php $key = $it['scope'].'-'.$it['id']; $rule = $rules[$it['scope'].':'.$it['id']] ?? null; @endphp
        <div class="sw-view sw-detail" id="switch-{{ $key }}" data-view="{{ $key }}" hidden>
            <div class="sw-bar-head">
                <button type="button" class="btn light sw-back" data-back="list">‹ Back</button>
                <span class="sw-ico">{!! $icon[$it['scope']] !!}</span>
                <h3>{{ $it['name'] }}</h3>
                <span class="sw-pill" data-state="{{ $key }}"></span>
                <span class="sw-head-right"><button type="button" class="switch switch-lg" role="switch" aria-label="{{ $it['name'] }}" data-switch="{{ $key }}"><span class="knob"></span></button></span>
            </div>
            <div class="sw-split @if ($it['scope'] === 'full') one @endif">
                <div class="sw-panel">
                    <div class="sw-panel-title">Choose weeks</div>
                    <div class="sw-cover big"><span class="sw-bar"><span data-bar="{{ $key }}"></span></span><span class="sw-sub" data-weeks-count="{{ $key }}"></span></div>
                    <button type="button" class="btn" data-weeks="{{ $key }}" data-name="{{ $it['name'] }}">Choose weeks</button>
                </div>
                @if ($it['scope'] === 'feature')
                    <form method="POST" action="{{ route('admin.files.update') }}" class="sw-panel sw-who">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="scope" value="{{ $it['scope'] }}">
                        <input type="hidden" name="key" value="{{ $it['id'] }}">
                        @include('admin.files._access', ['rule' => $rule, 'levels' => $levels, 'download' => false])
                    </form>
                @endif
            </div>
        </div>
    @endforeach

    <div class="sw-view sw-detail" id="swWeeks" hidden>
        <div class="sw-bar-head">
            <button type="button" class="btn light sw-back" id="swWeeksBack">‹ Back</button>
            <h3 id="swWeeksTitle">Choose weeks</h3>
            <span class="sw-head-right sw-all">
                <span>All weeks</span>
                <button type="button" class="switch" role="switch" aria-label="All weeks" id="swAllWeeks"><span class="knob"></span></button>
            </span>
        </div>
        <div id="swWeekList"></div>
    </div>
</div>

<script>
(function () {
    const WEEKS = @json($switches['weeks']);
    const URL_SAVE = @json(route('admin.files.visibility.update'));
    const CSRF = @json(csrf_token());
    let state = @json($switches['state']);
    let weeksFor = null;   // "full-hachayol" or "feature-orders" while Choose weeks is open
    let back = 'list';
    const status = document.getElementById('swStatus');

    const featureOf = (key) => key.startsWith('feature-') ? key.slice(8) : null;
    // the weeks that belong to this item: every Hachayol for Full, the weeks that have the feature for a feature
    const weeksOf = (key) => { const f = featureOf(key); return f ? WEEKS.filter(w => w.features.includes(f)) : WEEKS; };
    const isOn = (key) => { const f = featureOf(key); return f ? state.features[f].on : state.full; };
    function weekOn(key, id) {
        const f = featureOf(key);
        if (!f) return !state.weeks_off.includes(id);
        const list = state.features[f].weeks;
        return list === null || list.includes(id);
    }
    function setWeek(key, id, on) {
        const f = featureOf(key);
        if (!f) {
            state.weeks_off = state.weeks_off.filter(x => x !== id);
            if (!on) state.weeks_off.push(id);
            return;
        }
        const all = weeksOf(key).map(w => w.id);
        let list = state.features[f].weeks === null ? all.slice() : state.features[f].weeks.slice();
        list = list.filter(x => x !== id);
        if (on) list.push(id);
        state.features[f].weeks = all.every(x => list.includes(x)) ? null : list;
    }
    function setAllWeeks(key, on) {
        const f = featureOf(key);
        if (!f) { state.weeks_off = on ? [] : WEEKS.map(w => w.id); return; }
        state.features[f].weeks = on ? null : [];
    }

    function paint() {
        document.querySelectorAll('[data-switch]').forEach(b => b.setAttribute('aria-checked', isOn(b.dataset.switch) ? 'true' : 'false'));
        const count = (key) => { const ws = weeksOf(key); return [ws.filter(w => weekOn(key, w.id)).length, ws.length]; };
        document.querySelectorAll('[data-sub]').forEach(el => {
            const [on, all] = count(el.dataset.sub);
            el.textContent = on + ' of ' + all + ' weeks';
        });
        document.querySelectorAll('[data-bar]').forEach(el => {
            const [on, all] = count(el.dataset.bar);
            el.style.width = (all ? Math.round(on / all * 100) : 0) + '%';
        });
        document.querySelectorAll('[data-state]').forEach(el => {
            el.classList.toggle('on', isOn(el.dataset.state));
            el.textContent = isOn(el.dataset.state) ? 'On' : 'Off';
        });
        document.querySelectorAll('[data-card]').forEach(el => el.classList.toggle('is-off', !isOn(el.dataset.card)));
        const onCount = document.getElementById('swOnCount');
        if (onCount) onCount.textContent = document.querySelectorAll('#swList [data-card]:not(.is-off)').length + ' on';
        document.querySelectorAll('[data-weeks-count]').forEach(el => {
            const key = el.dataset.weeksCount, ws = weeksOf(key);
            el.textContent = ws.filter(w => weekOn(key, w.id)).length + ' of ' + ws.length + ' weeks on';
        });
        if (weeksFor) {
            const ws = weeksOf(weeksFor);
            document.getElementById('swAllWeeks').setAttribute('aria-checked', ws.every(w => weekOn(weeksFor, w.id)) ? 'true' : 'false');
            document.querySelectorAll('#swWeekList [data-week]').forEach(b => b.setAttribute('aria-checked', weekOn(weeksFor, b.dataset.week) ? 'true' : 'false'));
        }
    }

    let timer = null;
    function save() {
        paint();
        status.textContent = 'Saving…';
        status.dataset.s = 'saving';
        clearTimeout(timer);
        timer = setTimeout(() => {
            fetch(URL_SAVE, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(state) })
                .then(r => { if (!r.ok) throw 0; return r.json(); })
                .then(d => { state = d.state; paint(); status.textContent = 'Saved'; status.dataset.s = 'saved'; })
                .catch(() => { status.textContent = 'Not saved. Try again.'; status.dataset.s = 'error'; });
        }, 250);
    }

    function show(id) {
        document.querySelectorAll('#switches .sw-view').forEach(v => v.hidden = v.id !== id);
    }

    document.querySelectorAll('[data-switch]').forEach(b => b.addEventListener('click', () => {
        const key = b.dataset.switch, f = featureOf(key);
        if (f) state.features[f].on = !state.features[f].on; else state.full = !state.full;
        save();
    }));
    document.querySelectorAll('[data-open]').forEach(b => b.addEventListener('click', () => show('switch-' + b.dataset.open)));
    document.querySelectorAll('[data-back="list"]').forEach(b => b.addEventListener('click', () => show('swList')));
    document.querySelectorAll('[data-weeks]').forEach(b => b.addEventListener('click', () => {
        weeksFor = b.dataset.weeks; back = 'switch-' + weeksFor;
        document.getElementById('swWeeksTitle').textContent = 'Choose weeks · ' + b.dataset.name;
        const list = document.getElementById('swWeekList');
        list.innerHTML = '';
        weeksOf(weeksFor).forEach(w => {
            // a week tile: click to turn this week on or off
            const tile = document.createElement('button');
            tile.type = 'button'; tile.className = 'wk-tile'; tile.setAttribute('role', 'switch'); tile.setAttribute('aria-label', w.name);
            tile.dataset.week = w.id;
            const tick = document.createElement('span');
            tick.className = 'wk-tick';
            tick.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
            const name = document.createElement('span');
            name.className = 'wk-name';
            name.textContent = w.name;
            tile.append(tick, name);
            tile.addEventListener('click', () => { setWeek(weeksFor, w.id, !weekOn(weeksFor, w.id)); save(); });
            list.appendChild(tile);
        });
        paint();
        show('swWeeks');
    }));
    document.getElementById('swAllWeeks').addEventListener('click', () => {
        const ws = weeksOf(weeksFor);
        setAllWeeks(weeksFor, !ws.every(w => weekOn(weeksFor, w.id)));
        save();
    });
    document.getElementById('swWeeksBack').addEventListener('click', () => { weeksFor = null; show(back); });

    // after saving who can see, come back to the open feature
    if (location.hash.startsWith('#switch-') && document.getElementById(location.hash.slice(1))) show(location.hash.slice(1));
    paint();
})();
</script>
