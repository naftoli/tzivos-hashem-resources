{{-- Remove (or Restore) one box from the site. Needs $action, $restore, $removed, $name, and $fields (hidden inputs). --}}
<form method="POST" action="{{ $removed ? $restore : $action }}" class="fx-rm-form"
      @unless ($removed) data-confirm="Remove {{ $name }} from the site? It will be gone for everyone until you restore it." @endunless>
    @csrf
    @foreach ($fields as $n => $v)
        <input type="hidden" name="{{ $n }}" value="{{ $v }}">
    @endforeach
    @if ($removed)
        <button type="submit" class="fx-rm restore">Restore</button>
    @else
        <button type="submit" class="fx-rm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>Remove</button>
    @endif
</form>
