{{-- Who can see / who can download / when. Needs $rule (or null), $levels, $download (bool). Inside a <form>. --}}
<div class="acc">
    <div class="acc-row"><span class="acc-l">Who can see</span>
        <div class="acc-opts">
            <label class="chip"><input type="checkbox" checked disabled> HQ admin</label>
            @foreach ($levels as $role => $label)
                <label class="chip"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked(! $rule || $rule->roles === null || in_array($role, $rule->roles, true))> {{ $label }}</label>
            @endforeach
        </div>
    </div>
    @if ($download)
        <div class="acc-row"><span class="acc-l">Who can download</span>
            <div class="acc-opts">
                <label class="chip"><input type="checkbox" checked disabled> HQ admin</label>
                @foreach ($levels as $role => $label)
                    <label class="chip"><input type="checkbox" name="download_roles[]" value="{{ $role }}" @checked(! $rule || $rule->download_roles === null || in_array($role, $rule->download_roles, true))> {{ $label }}</label>
                @endforeach
            </div>
        </div>
    @endif
    <div class="acc-row"><span class="acc-l">When</span>
        <div class="acc-opts acc-when">
            <label class="date">From <input type="datetime-local" name="starts_at" value="{{ \App\Support\AccessForm::show($rule?->starts_at) }}"></label>
            <label class="date">Until <input type="datetime-local" name="ends_at" value="{{ \App\Support\AccessForm::show($rule?->ends_at) }}"></label>
            <button class="btn acc-save" type="submit">Save</button>
        </div>
    </div>
</div>
