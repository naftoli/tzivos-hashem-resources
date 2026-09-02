import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTzhUser } from '@/components/AuthGate';
import { getWeekInfo } from '@/lib/hebrewWeek';
import { setCtxFrom } from '@/lib/navState';
import { buildSearchIndex, norm, searchSite, type SearchResult } from '@/lib/search';
import { useEscapeToClose } from '@/lib/useEscapeToClose';
import SIDX from '@/data/static/SIDX.json';
import type { BranchId } from '@/types';

const BRANCHES: Array<{ id: BranchId; label: string }> = [
  { id: 'date', label: 'Date' },
  { id: 'campaign', label: 'Campaign' },
  { id: 'rally', label: 'TH Rally' },
  { id: 'promotions', label: 'Promotions' },
  { id: 'marking', label: 'Mission Marking' },
  { id: 'chitas', label: 'Daily Chitas' },
  { id: 'calendar', label: 'Calendar' },
];

/**
 * Ports the monolith's persistent chrome (`.topbar`, `header.mast`, `nav.branchbar`),
 * which in the legacy page sits once outside `#app`/the page sections, never repeated
 * inside individual `data-page` fragments. Rendered once around the router's outlet.
 *
 * The topbar's current-parsha text and the "me" chip's live user identity are
 * both date/session-driven dynamic widgets, ported in phase 2:
 *  - the topbar strip mirrors the same `getWeekInfo()` computation `applyWeekState`
 *    (src/lib/applyWeekState.ts) uses for the home hero widget's `wkParsha` — the
 *    legacy monolith's inline `setWeek()` never actually wired this element up
 *    (it has no id, so it was left static at export time), which reads as an
 *    oversight rather than intent, so it's wired up here for real parity.
 *  - the "me" chip reads straight from `useTzhUser()` (AuthGate's checkAuth.php
 *    response) instead of the legacy's `window.TZH_USER`/URL-param/host-endpoint
 *    fallback chain — this app already gates on a real logged-in session, and
 *    checkAuth.php was written to return exactly the `{name, role, initials}`
 *    shape that chain expected.
 */
function initialsOf(name: string | undefined): string {
  return (name || '')
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w[0])
    .join('')
    .slice(0, 2)
    .toUpperCase();
}

export function Header({ activeBranch }: { activeBranch: BranchId }) {
  const navigate = useNavigate();
  const user = useTzhUser();
  const [weekParsha, setWeekParsha] = useState('');
  const searchIndex = useMemo(() => buildSearchIndex(SIDX), []);
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const searchRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setWeekParsha(getWeekInfo().pe);
  }, []);

  const results: SearchResult[] = useMemo(() => searchSite(query, searchIndex), [query, searchIndex]);

  // Port of legacy `document.addEventListener('input', ...)`'s `qn.length<2`
  // close-the-panel behavior — the panel opens once there's a real query,
  // closes again once it's cleared (by typing or by a result click below).
  useEffect(() => {
    setOpen(norm(query).length >= 2);
  }, [query]);

  useEscapeToClose(open, () => setOpen(false));

  // Port of legacy's outside-click close (`if(!e.target.closest('#siteSearch'))
  // pan.classList.remove('open')`) — result clicks close it themselves below.
  useEffect(() => {
    if (!open) return;
    function onDocClick(e: MouseEvent) {
      if (searchRef.current && !searchRef.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener('click', onDocClick);
    return () => document.removeEventListener('click', onDocClick);
  }, [open]);

  // Port of the `.sr` click branch: navigate, then close the panel and clear
  // the query, same as the legacy handler. Uses the same `go()` navigation
  // this chrome already uses for its other `[data-go]`-equivalent buttons
  // (brand/me/branch links) — this search UI sits outside any content page's
  // container, so it never goes through ContentPage's delegated `[data-go]`
  // handler either.
  function goToResult(id: string) {
    go(id);
    setOpen(false);
    setQuery('');
  }

  const name = user?.name || "R' Mendel Katz";
  const role = user?.role || 'Field Marshal';
  const avatar = user?.initials || initialsOf(user?.name) || 'MK';

  // Every one of this chrome's nav targets (`brand`, `meChip`, the `.bl` branch
  // buttons) is `[data-go]` in the legacy markup, sitting outside any
  // `[data-page]` section — so the shared `[data-go]` handler's
  // `ctxFrom = from ? ... : null` always resolves to `null` for these. Ported
  // here explicitly since this chrome bypasses that handler (it's rendered
  // once outside `ContentPage`, not part of any extracted page fragment).
  function go(id: string) {
    setCtxFrom(null);
    navigate(`/${id}`);
  }

  return (
    <>
      <div className="topbar">
        <div className="wrap">
          <span className="r">{weekParsha ? `Parshas ${weekParsha}` : ''}</span>
        </div>
      </div>
      <header className="mast">
        <div className="wrap">
          <button className="brand" onClick={() => go('home')}>
            <span className="logo" />
            <span>
              <b>Tzivos Hashem</b>
              <span>Commander&rsquo;s Resources</span>
            </span>
          </button>
          <div className="search" id="siteSearch" ref={searchRef}>
            <span>&#9906;</span>
            <input
              type="search"
              placeholder="Search the library…"
              aria-label="Search the library"
              autoComplete="off"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              onFocus={() => setOpen(norm(query).length >= 2)}
            />
            <div className={`srchpanel${open ? ' open' : ''}`} id="srchPanel">
              {open && results.length === 0 && <div className="sr-none">No matches</div>}
              {open &&
                results.map(({ entry }) => (
                  <button
                    key={entry.g}
                    className="sr"
                    data-go={entry.g}
                    onClick={() => goToResult(entry.g)}
                  >
                    <b>{entry.t}</b>
                    <span>{entry.k || ''}</span>
                  </button>
                ))}
            </div>
          </div>
          <span className="spacer" />
          <button className="me" onClick={() => go('marking')}>
            <span className="av">{avatar}</span>
            <span>
              <b>{name}</b>
              <span>{role}</span>
            </span>
          </button>
        </div>
      </header>
      <nav className="branchbar" aria-label="Branches">
        <div className="wrap">
          {BRANCHES.map((branch) => (
            <button
              key={branch.id}
              className="bl"
              data-branch={branch.id}
              aria-current={activeBranch === branch.id ? 'true' : undefined}
              onClick={() => go(branch.id)}
            >
              {branch.label}
            </button>
          ))}
        </div>
      </nav>
    </>
  );
}
