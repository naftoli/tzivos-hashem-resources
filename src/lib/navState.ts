import type { NavigateFunction } from 'react-router-dom';

/**
 * Port of the legacy monolith's global `ctxFrom`/back-`stack` bits of `show()`/
 * `nav()` (see `[data-go]`'s handler, which is the only thing that ever writes
 * `ctxFrom`). Kept as a tiny module-level singleton — same shape as the legacy
 * global var — rather than React state, because it's write-before-navigate/
 * read-on-next-render, exactly like the original: `ContentPage`'s `[data-go]`
 * handler sets it synchronously right before calling `navigate()`, and whatever
 * renders next (`App.tsx`'s branch-bar `aria-current`, `ContentPage`'s own
 * altcrumb swap) reads it during that next render — no extra re-render needed.
 */
let ctxFrom: string | null = null;

export function setCtxFrom(v: string | null): void {
  ctxFrom = v;
}

/**
 * Mirrors legacy `var want = (ctxFrom==='campaign' || ctxFrom==='chitas') ?
 * ctxFrom : (branchOf[pid]||pid);` — callers pass their own `branchOf[pid]||pid`
 * equivalent (usually a page-registry entry's `.branch`) as `fallback`.
 */
export function wantedBranch(fallback: string): string {
  return ctxFrom === 'campaign' || ctxFrom === 'chitas' ? ctxFrom : fallback;
}

/**
 * Port of the `[data-back]` handler's `nav(stack.length ? stack.pop() :
 * bk.getAttribute('data-back'))`. Rather than hand-rolling a navigation stack,
 * this leans on the browser's real session history via react-router's
 * `navigate(-1)` — HashRouter (via the `history` package) stamps every pushed
 * entry's `history.state.idx`, starting at 0 on a fresh load/direct link and
 * incrementing on every subsequent push, so `idx > 0` is the "do we actually
 * have somewhere to go back to" check the legacy `stack.length` guard made.
 * When there's no real history yet (a fresh direct link straight to this page),
 * falls back to `data-back`'s literal target, same as the original.
 */
export function navigateBack(navigate: NavigateFunction, fallbackId: string): void {
  const idx = (window.history.state as { idx?: number } | null)?.idx;
  if (typeof idx === 'number' && idx > 0) {
    navigate(-1);
  } else {
    navigate(`/${fallbackId}`, { replace: true });
  }
}
