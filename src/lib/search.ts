/**
 * Port of the legacy monolith's site search (`#siteSearch`/`#srchPanel`) — a
 * fully client-side, fuzzy, Hebrew-aware search over `SIDX.json`'s ~604
 * `{g, t, k, x}` entries (`g` = target page id for navigation, `t` = title,
 * `k` = category/breadcrumb-ish label, `x` = body text for deeper matching).
 *
 * Kept as a pure module (no DOM) — the legacy `run()` mutated `#srchPanel`
 * directly and cached `_t`/`_h`/`_tt`/`_ht` fields onto each SIDX entry the
 * first time it was touched; here that memoization is done once up front in
 * `indexEntries` instead of lazily on first search, since the whole array is
 * small and imported eagerly anyway.
 */

export interface SearchEntry {
  g: string;
  t: string;
  k?: string;
  x?: string;
}

interface IndexedEntry extends SearchEntry {
  _t: string;
  _h: string;
  _tt: string[];
  _ht: string[];
}

export interface SearchResult {
  score: number;
  entry: SearchEntry;
}

/** Port of legacy `norm()`. */
export function norm(s: string): string {
  let out = String(s).toLowerCase();
  out = out.replace(/[֑-ׇ]/g, ''); // strip Hebrew niqqud/cantillation
  out = out
    .replace(/ך/g, 'כ')
    .replace(/ם/g, 'מ')
    .replace(/ן/g, 'נ')
    .replace(/ף/g, 'פ')
    .replace(/ץ/g, 'צ');
  out = out.replace(/[^a-z0-9א-ת ]+/g, ' ');
  return out.replace(/\s+/g, ' ').trim();
}

/** Port of legacy `lev()` — capped edit distance. */
export function lev(a: string, b: string): number {
  const m = a.length;
  const n = b.length;
  if (!m) return n;
  if (!n) return m;
  if (Math.abs(m - n) > 2) return 3;
  let prev: number[] = [];
  let cur: number[] = [];
  for (let j = 0; j <= n; j++) prev[j] = j;
  for (let i = 1; i <= m; i++) {
    cur[0] = i;
    for (let j = 1; j <= n; j++) {
      const c = a.charCodeAt(i - 1) === b.charCodeAt(j - 1) ? 0 : 1;
      cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + c);
    }
    const t = prev;
    prev = cur;
    cur = t;
  }
  return prev[n];
}

/** Port of legacy `fuzTok()` — is `q` close to any haystack token? */
export function fuzTok(q: string, toks: string[]): boolean {
  const th = q.length <= 4 ? 1 : 2;
  for (const tk of toks) {
    if (tk.indexOf(q) === 0) return true;
    if (Math.abs(tk.length - q.length) <= th && lev(q, tk) <= th) return true;
  }
  return false;
}

/** One-time memoization pass, equivalent to the legacy lazy `_t`/`_h`/`_tt`/`_ht` caching. */
function indexEntry(e: SearchEntry): IndexedEntry {
  const _t = norm(e.t);
  const _h = norm(`${e.t || ''} ${e.k || ''} ${e.x || ''}`);
  return { ...e, _t, _h, _tt: _t.split(' ').filter(Boolean), _ht: _h.split(' ').filter(Boolean) };
}

/** Builds a reusable search index from the raw SIDX entries. */
export function buildSearchIndex(entries: SearchEntry[]): IndexedEntry[] {
  return entries.map(indexEntry);
}

/**
 * Port of legacy `run()`'s scoring/ranking (minus the DOM rendering, which
 * lives in the Header component instead). Returns up to 20 results, best
 * first. An empty array means "no matches" or "query too short" — same as
 * the legacy panel closing.
 */
export function searchSite(query: string, index: IndexedEntry[]): SearchResult[] {
  const qn = norm(query);
  if (qn.length < 2) return [];
  const qtok = qn.split(' ').filter(Boolean);

  const res: Array<[number, number, SearchEntry]> = [];
  for (const e of index) {
    let sc = -1;
    if (e._t.indexOf(qn) === 0) sc = 0;
    else if (e._t.indexOf(qn) > -1) sc = 1;
    else if (e._h.indexOf(qn) > -1) sc = 2;
    else if (qtok.every((w) => fuzTok(w, e._tt))) sc = 3;
    else if (qtok.every((w) => fuzTok(w, e._ht))) sc = 4;
    if (sc > -1) res.push([sc, e.t.length, e]);
  }

  res.sort((a, b) => a[0] - b[0] || a[1] - b[1]);
  return res.slice(0, 20).map(([score, , entry]) => ({ score, entry }));
}
