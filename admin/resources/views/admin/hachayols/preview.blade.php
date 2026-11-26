@extends('layouts.admin')

@section('title', 'Hachayol preview | Resources Admin')
@section('page-title', 'Upload Full Hachayols')

@push('head')
<style>
    .hp-top { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
    .hp-top h1 { margin: 0; font-size: 18px; font-weight: 600; color: #181c32; }
    .hp-top p { margin: 2px 0 0; color: #7e8299; font-size: 12.5px; }
    .hp-top .right { margin-left: auto; display: flex; gap: 8px; }
    .hp-issue { background: #fff; border-radius: 14px; box-shadow: 0 0 30px rgba(82,63,105,.06); margin-bottom: 22px; border: 1px solid #eef0f6; }
    .hp-issue.is-off { opacity: .6; }
    .hp-head { display: flex; align-items: center; gap: 14px; padding: 16px 22px; border-bottom: 1px solid #f0f1f6; flex-wrap: wrap; }
    .hp-num { display: inline-flex; align-items: center; justify-content: center; min-width: 60px; height: 42px; padding: 0 10px; border-radius: 10px; background: #151828; color: #fff; font-weight: 700; font-size: 15px; }
    .hp-head .t b { display: block; font-size: 15px; color: #181c32; }
    .hp-head .t span { color: #a1a5b7; font-size: 12px; }
    .hp-head .right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
    .hp-switch { display: inline-flex; align-items: center; gap: 8px; font-size: 12.5px; font-weight: 600; color: #3f4254; cursor: pointer; user-select: none; }
    .hp-switch input { appearance: none; -webkit-appearance: none; width: 38px; height: 22px; border-radius: 999px; background: #d5d9e4; position: relative; cursor: pointer; transition: background .15s; margin: 0; }
    .hp-switch input::after { content: ''; position: absolute; top: 3px; left: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .15s; }
    .hp-switch input:checked { background: #1bc5bd; }
    .hp-switch input:checked::after { transform: translateX(16px); }
    .hp-warn { margin: 14px 22px 0; padding: 10px 14px; border-radius: 10px; background: #fff4de; color: #8a5a00; font-size: 12.5px; }
    .hp-warn.red { background: #ffe2e5; color: #b4162d; }
    .hp-meta { display: grid; grid-template-columns: 130px 1fr; gap: 22px; padding: 18px 22px; }
    .hp-cover { width: 130px; border-radius: 8px; box-shadow: 0 4px 14px rgba(21,24,40,.15); background: #f3f6f9; aspect-ratio: 654 / 834; object-fit: cover; }
    .hp-fields { display: grid; grid-template-columns: 120px 1.4fr 1fr 1fr; gap: 14px 16px; align-content: start; }
    .hp-f label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #a1a5b7; margin-bottom: 6px; }
    .hp-f input, .hp-f select, .hp-f textarea { width: 100%; box-sizing: border-box; height: 38px; padding: 0 12px; border: 1px solid #e1e4ee; border-radius: 8px; font: inherit; font-size: 13px; color: #181c32; background: #fff; }
    .hp-f textarea { height: auto; padding: 10px 12px; line-height: 1.55; resize: vertical; }
    .hp-f input:focus, .hp-f select:focus, .hp-f textarea:focus { outline: 0; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(54,153,255,.15); }
    .hp-f .hint { color: #a1a5b7; font-size: 11.5px; margin-top: 5px; }
    .hp-secs { padding: 4px 22px 22px; display: flex; flex-direction: column; gap: 14px; }
    .hp-secs h3 { margin: 6px 0 2px; font-size: 13px; font-weight: 600; color: #181c32; display: flex; align-items: center; gap: 8px; }
    .hp-sec { display: grid; grid-template-columns: 110px 1fr; gap: 18px; padding: 16px; border-radius: 12px; border: 1px solid #eceef5; background: #fafbfd; }
    .hp-sec.is-off { opacity: .55; }
    .hp-thumb { width: 110px; border-radius: 6px; box-shadow: 0 2px 8px rgba(21,24,40,.12); background: #eef1f6; aspect-ratio: 654 / 834; object-fit: cover; }
    .hp-sec-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
    .hp-ribbon { display: inline-flex; align-items: center; height: 24px; padding: 0 10px; border-radius: 6px; font-size: 11.5px; font-weight: 700; color: #fff; background: #8950fc; }
    .hp-ribbon.picture { background: #7e8299; }
    .hp-sec-head .pages { color: #7e8299; font-size: 12px; }
    .hp-sec-head .right { margin-left: auto; }
    .hp-grid { display: grid; grid-template-columns: 1.6fr 200px 90px 90px; gap: 12px 14px; }
    .hp-grid .wide { grid-column: 1 / -1; }
    .hp-recs { display: flex; flex-wrap: wrap; gap: 8px; }
    .hp-rec { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 999px; border: 1px solid #d8e9ff; background: #fff; font-size: 12px; color: #3f4254; cursor: pointer; }
    .hp-rec input { margin: 0; accent-color: var(--blue); }
    .hp-left-out { color: #b26b00; font-size: 11.5px; margin-top: 5px; }
    .hp-bar { position: sticky; bottom: 0; z-index: 5; display: flex; align-items: center; gap: 12px; padding: 14px 22px; margin: 0 -4px; border-radius: 14px 14px 0 0; background: rgba(255,255,255,.96); box-shadow: 0 -6px 24px rgba(21,24,40,.08); backdrop-filter: blur(6px); }
    .hp-bar .note { color: #7e8299; font-size: 12.5px; }
    .hp-bar .right { margin-left: auto; display: flex; gap: 10px; }
    .btn.red-light { background: #ffe2e5; color: #d1213d; }
    @media (max-width: 1100px) { .hp-fields { grid-template-columns: 1fr 1fr; } .hp-grid { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 700px) { .hp-meta, .hp-sec { grid-template-columns: 1fr; } }
</style>
@endpush

@php
    $colors = ['c-red' => '#e5484d', 'c-orange' => '#f08a24', 'c-teal' => '#14b8a6', 'c-cyan' => '#0ea5c6', 'c-gold' => '#c99a06', 'c-green' => '#3a9d4a', 'c-blue' => '#3b82f6', 'c-purple' => '#8950fc'];
@endphp

@section('content')
    @if (session('status'))
        <div class="ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="error">{{ $errors->first() }}</div>
    @endif

    <div class="hp-top">
        <div>
            <h1>Check before publishing</h1>
            <p>{{ count($items) }} Hachayol PDF(s) read. Change anything below, untick what should not go on the site, then Publish.</p>
        </div>
        <div class="right">
            <a class="btn light" href="{{ route('admin.hachayols') }}">+ Add more PDFs</a>
        </div>
    </div>

    @unless ($canPicture)
        <div class="error">{{ \App\Support\Hachayol\PdfTools::MISSING_PICTURES }}</div>
    @endunless

    <form method="POST" action="{{ route('admin.hachayols.publish', $batch) }}" id="hpForm">
        @csrf
        @foreach ($items as $item)
            @php
                $a = $item['analysis'];
                $id = $item['id'];
                $slot = $slots[$item['slot']] ?? null;
                $taken = $slot && $slot['issue'] !== null;
                $on = ! $taken && $a['issue'] !== null && $slot !== null;
                $thumb = fn ($p) => $item['thumbs'] ? route('admin.hachayols.thumb', [$batch, $id, $p]) : null;
            @endphp
            <section class="hp-issue {{ $on ? '' : 'is-off' }}" data-issue>
                <div class="hp-head">
                    <span class="hp-num">#{{ $a['issue'] ?? '?' }}</span>
                    <div class="t">
                        <b>Hachayol #{{ $a['issue'] ?? '?' }}@if ($slot) · Parshas {{ $slot['english'] }}@endif @if ($a['date']) · {{ $a['date'] }}@endif</b>
                        <span>{{ $item['name'] }} · {{ $a['pages'] }} pages · {{ number_format($item['size'] / 1048576, 1) }} MB</span>
                    </div>
                    <div class="right">
                        <label class="hp-switch"><input type="checkbox" name="issues[{{ $id }}][on]" value="1" @checked($on) data-toggle="issue"> Publish this issue</label>
                    </div>
                </div>
                @if ($taken)
                    <div class="hp-warn red">Parshas {{ $slot['english'] }} already has Hachayol #{{ $slot['issue'] }} on the site. Pick another Parsha to publish this one.</div>
                @endif
                @foreach ($a['warnings'] as $w)
                    <div class="hp-warn">{{ $w }}</div>
                @endforeach

                <div class="hp-meta">
                    @if ($thumb(1))<img class="hp-cover" src="{{ $thumb(1) }}" alt="Cover">@else<div class="hp-cover"></div>@endif
                    <div class="hp-fields">
                        <div class="hp-f"><label>Issue #</label><input name="issues[{{ $id }}][issue]" value="{{ $a['issue'] }}" inputmode="numeric"></div>
                        <div class="hp-f"><label>Parsha (week on the site)</label>
                            <select name="issues[{{ $id }}][slot]" data-slot>
                                <option value="">Choose…</option>
                                @foreach ($slots as $s)
                                    <option value="{{ $s['key'] }}" data-english="{{ $s['english'] }}" @selected($item['slot'] === $s['key'])>{{ $s['english'] }} · {{ $s['hebrew'] }}{{ $s['issue'] ? ' (has #'.$s['issue'].')' : '' }}</option>
                                @endforeach
                            </select>
                            @if ($a['parsha_hebrew'])<div class="hint">Cover says: פרשת {{ $a['parsha_hebrew'] }}</div>@endif
                        </div>
                        <div class="hp-f"><label>Parsha name shown</label><input name="issues[{{ $id }}][parsha_name]" value="{{ $slot['english'] ?? '' }}" data-parsha-name></div>
                        <div class="hp-f"><label>Date</label><input name="issues[{{ $id }}][date]" value="{{ $a['date'] }}" placeholder="21 Shvat 5787"></div>
                    </div>
                </div>

                <div class="hp-secs">
                    <h3>Features in this Hachayol <span class="pill grey">{{ count($a['sections']) }}</span></h3>
                    @foreach ($a['sections'] as $i => $s)
                        @php
                            $f = $s['feature'];
                            $parts = $f ? ($item['parts'][$f] ?? null) : null;
                            $color = $colors[$parts['ribbon_class'] ?? ''] ?? '#8950fc';
                            $n = "issues[{$id}][sections][{$i}]";
                        @endphp
                        <div class="hp-sec" data-sec>
                            @if ($thumb($s['start']))<img class="hp-thumb" src="{{ $thumb($s['start']) }}" alt="Page {{ $s['start'] }}" loading="lazy">@else<div class="hp-thumb"></div>@endif
                            <div>
                                <div class="hp-sec-head">
                                    <span class="hp-ribbon {{ $f ? '' : 'picture' }}" style="{{ $f ? 'background:'.$color : '' }}">{{ $parts['ribbon'] ?? $s['label'] }}</span>
                                    <span class="pages">pp. {{ $s['start'] }}{{ $s['end'] > $s['start'] ? '–'.$s['end'] : '' }} · {{ $f ? 'its own page, with a card on the Parsha page' : 'a picture card on the Parsha page' }}</span>
                                    <div class="right"><label class="hp-switch"><input type="checkbox" name="{{ $n }}[on]" value="1" checked data-toggle="sec"> Put on the site</label></div>
                                </div>
                                <div class="hp-grid">
                                    <div class="hp-f"><label>Title</label><input name="{{ $n }}[title]" value="{{ $s['title'] }}" @if ($f === 'niggun') dir="auto" @endif></div>
                                    <div class="hp-f"><label>Shown as</label>
                                        <select name="{{ $n }}[feature]">
                                            @foreach ($features as $key => $names)
                                                <option value="{{ $key }}" @selected($f === $key)>{{ $item['parts'][$key]['ribbon'] ?? $names[0] }}</option>
                                            @endforeach
                                            <option value="" @selected(! $f)>Picture card only ({{ $s['label'] }})</option>
                                        </select>
                                    </div>
                                    <div class="hp-f"><label>From page</label><input type="number" min="1" max="{{ $a['pages'] }}" name="{{ $n }}[start]" value="{{ $s['start'] }}"></div>
                                    <div class="hp-f"><label>To page</label><input type="number" min="1" max="{{ $a['pages'] }}" name="{{ $n }}[end]" value="{{ $s['end'] }}"></div>
                                    @if ($f)
                                        <div class="hp-f wide"><label>Mission</label><input name="{{ $n }}[mission]" value="{{ $s['mission'] }}"></div>
                                    @endif
                                    @if ($f === 'niggun')
                                        <div class="hp-f wide"><label>Niggun recordings already on the site</label>
                                            @if ($s['recordings'])
                                                <div class="hp-recs">
                                                    @foreach ($s['recordings'] as $r)
                                                        <label class="hp-rec"><input type="checkbox" name="{{ $n }}[recordings][]" value="{{ $r['href'] }}" checked> {{ $r['label'] }}</label>
                                                    @endforeach
                                                </div>
                                            @else
                                                <div class="hint">No recording with this name is on the site yet. Add one later with Upload files.</div>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($f)
                                        <div class="hp-f wide"><label>Text</label>
                                            <textarea name="{{ $n }}[text]" rows="7">{{ $s['text'] }}</textarea>
                                            <div class="hint">An empty line starts a new paragraph. A line starting with ## is a heading.</div>
                                            @if ($s['hebrew_lines_left_out'])<div class="hp-left-out">{{ $s['hebrew_lines_left_out'] }} Hebrew line(s) with nekudos were left out (they come out jumbled from a PDF). They are still in the page pictures.</div>@endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="hp-bar">
            <span class="note">Publishing adds the PDF, the page pictures, a page for each feature and the week's Hachayol bar and cards.</span>
            <div class="right">
                <button type="submit" form="hpDiscard" class="btn red-light">Discard</button>
                <button type="submit" class="btn" @disabled(! $canPicture)>Publish</button>
            </div>
        </div>
    </form>
    <form method="POST" action="{{ route('admin.hachayols.discard', $batch) }}" id="hpDiscard" onsubmit="return confirm('Discard these PDFs? Nothing will be added to the site.')">
        @csrf
        @method('DELETE')
    </form>

    <script>
    (function () {
        document.querySelectorAll('[data-toggle]').forEach(function (c) {
            var box = c.closest(c.dataset.toggle === 'issue' ? '[data-issue]' : '[data-sec]');
            c.addEventListener('change', function () { box.classList.toggle('is-off', !c.checked); });
        });
        document.querySelectorAll('[data-slot]').forEach(function (sel) {
            sel.addEventListener('change', function () {
                var o = sel.options[sel.selectedIndex];
                var name = sel.closest('[data-issue]').querySelector('[data-parsha-name]');
                if (o && o.dataset.english) name.value = o.dataset.english;
            });
        });
    })();
    </script>
@endsection
