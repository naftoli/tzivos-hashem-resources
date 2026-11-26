{{-- Files: Full and each feature, each with an on/off switch. Click one to open it: its switch, Choose weeks, and who can see it.
     Needs $switches (weeks, features, state), $rules, $levels. --}}
@php
    $items = array_merge([['id' => 'hachayol', 'name' => 'Full', 'scope' => 'full']], array_map(fn ($f) => $f + ['scope' => 'feature'], $switches['features']));
@endphp
<div class="card" id="switches">
    <div class="card-head"><h2>Full and features</h2><div class="right"><span class="sub" id="swStatus" aria-live="polite"></span></div></div>
    <div class="card-body" style="padding-top:4px">
        <div class="sw-view" id="swList">
            @foreach ($items as $it)
                <div class="sw-row">
                    <button type="button" class="sw-open" data-open="{{ $it['scope'] }}-{{ $it['id'] }}">
                        <span><span class="sw-name">{{ $it['name'] }}</span><span class="sw-sub" data-sub="{{ $it['scope'] }}-{{ $it['id'] }}"></span></span>
                        <span class="sw-chev">›</span>
                    </button>
                    <button type="button" class="switch" role="switch" aria-label="{{ $it['name'] }}" data-switch="{{ $it['scope'] }}-{{ $it['id'] }}"><span class="knob"></span></button>
                </div>
            @endforeach
        </div>

        @foreach ($items as $it)
            @php $rule = $rules[$it['scope'].':'.$it['id']] ?? null; @endphp
            <div class="sw-view" id="switch-{{ $it['scope'] }}-{{ $it['id'] }}" data-view="{{ $it['scope'] }}-{{ $it['id'] }}" hidden>
                <div class="sw-nav"><button type="button" class="btn light sw-back" data-back="list">‹ Back</button><h3>{{ $it['name'] }}</h3></div>
                <div class="sw-row">
                    <span><span class="sw-name">{{ $it['name'] }}</span><span class="sw-sub" data-sub="{{ $it['scope'] }}-{{ $it['id'] }}"></span></span>
                    <button type="button" class="switch" role="switch" aria-label="{{ $it['name'] }}" data-switch="{{ $it['scope'] }}-{{ $it['id'] }}"><span class="knob"></span></button>
                </div>
                <div class="sw-row">
                    <span class="sw-sub" data-weeks-count="{{ $it['scope'] }}-{{ $it['id'] }}"></span>
                    <button type="button" class="btn light" data-weeks="{{ $it['scope'] }}-{{ $it['id'] }}">Choose weeks</button>
                </div>
                <form method="POST" action="{{ route('admin.files.update') }}" class="sw-who">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="scope" value="{{ $it['scope'] }}">
                    <input type="hidden" name="key" value="{{ $it['id'] }}">
                    @include('admin.files._access', ['rule' => $rule, 'levels' => $levels, 'download' => $it['scope'] === 'full'])
                </form>
            </div>
        @endforeach

        <div class="sw-view" id="swWeeks" hidden>
            <div class="sw-nav"><button type="button" class="btn light sw-back" id="swWeeksBack">‹ Back</button><h3 id="swWeeksTitle">Choose weeks</h3></div>
            <div class="sw-row sw-all">
                <span class="sw-name">All weeks</span>
                <button type="button" class="switch" role="switch" aria-label="All weeks" id="swAllWeeks"><span class="knob"></span></button>
            </div>
            <div id="swWeekList"></div>
        </div>
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
        document.querySelectorAll('[data-sub]').forEach(el => {
            const key = el.dataset.sub;
            const ws = weeksOf(key), on = ws.filter(w => weekOn(key, w.id)).length;
            el.textContent = (isOn(key) ? 'On' : 'Off') + ' · ' + on + ' of ' + ws.length + ' weeks';
        });
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
        clearTimeout(timer);
        timer = setTimeout(() => {
            fetch(URL_SAVE, { method: 'PUT', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }, body: JSON.stringify(state) })
                .then(r => { if (!r.ok) throw 0; return r.json(); })
                .then(d => { state = d.state; paint(); status.textContent = 'Saved'; })
                .catch(() => { status.textContent = 'Not saved. Try again.'; });
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
        document.getElementById('swWeeksTitle').textContent = 'Choose weeks · ' + b.closest('.sw-view').querySelector('h3').textContent;
        const list = document.getElementById('swWeekList');
        list.innerHTML = '';
        weeksOf(weeksFor).forEach(w => {
            const row = document.createElement('div');
            row.className = 'sw-row';
            const name = document.createElement('span');
            name.textContent = w.name;
            const sw = document.createElement('button');
            sw.type = 'button'; sw.className = 'switch'; sw.setAttribute('role', 'switch'); sw.setAttribute('aria-label', w.name);
            sw.dataset.week = w.id; sw.innerHTML = '<span class="knob"></span>';
            sw.addEventListener('click', () => { setWeek(weeksFor, w.id, !weekOn(weeksFor, w.id)); save(); });
            row.append(name, sw);
            list.appendChild(row);
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

    // after saving who can see, come back to the open Full / feature
    if (location.hash.startsWith('#switch-') && document.getElementById(location.hash.slice(1))) show(location.hash.slice(1));
    paint();
})();
</script>
