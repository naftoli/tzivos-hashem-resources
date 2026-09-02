import { useEffect, useMemo, useState } from 'react';
import { fetchPromotions, sortPromotions, type Promotion, type PromotionSortKey } from '@/lib/promotions';

/**
 * Live-rendered replacement for the `#promoMonth`/`#promoRows` subtree inside
 * the `promotions` page fragment — same architectural shape as
 * `chitasFeed.ts`'s live region for the `chitas` page: a real backend fetch,
 * not table-derived from the static extracted HTML. Unlike chitas's feed
 * (which patches the DOM in place), this is a real React component so the
 * sortable columns can be plain state instead of hand-rolled DOM toggling —
 * `ContentPage` mounts it in place of the dead placeholder markup for the
 * `promotions` page only (see its `pageId === 'promotions'` branch).
 *
 * The `useEffect`'s empty dependency array is this component's version of
 * the legacy `started` guard: `ContentPage` only mounts this component while
 * the `promotions` route is actually active, so "fetch once per mount" is
 * already "fetch once per genuine visit" — no extra singleton needed.
 */

const SORT_KEYS: PromotionSortKey[] = ['sort_name', 'was_ord', 'now_ord', 'when_jd'];

function isSortKey(v: string | null): v is PromotionSortKey {
  return v !== null && (SORT_KEYS as string[]).includes(v);
}

export function PromotionsTable() {
  const [monthLabel, setMonthLabel] = useState('');
  const [rows, setRows] = useState<Promotion[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [sortKey, setSortKey] = useState<PromotionSortKey | null>(null);
  const [sortDir, setSortDir] = useState<1 | -1>(1);

  useEffect(() => {
    let cancelled = false;
    fetchPromotions()
      .then((data) => {
        if (cancelled) return;
        if (data.month?.he) setMonthLabel(`This month · ${data.month.he}`);
        if (!data.promotions.length) {
          setError(`No promotions yet this month${data.month?.en ? ` — ${data.month.en}` : ''}.`);
          setRows([]);
          return;
        }
        setRows(data.promotions);
      })
      .catch(() => {
        setError('Couldn’t load this month’s promotions — try again in a moment.');
        setRows([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const sorted = useMemo(() => sortPromotions(rows || [], sortKey, sortDir), [rows, sortKey, sortDir]);

  function onHeaderClick(key: string | null) {
    if (!isSortKey(key)) return;
    setSortDir((prevDir) => (key === sortKey ? ((-prevDir) as 1 | -1) : 1));
    setSortKey(key);
  }

  const loading = rows === null && !error;

  return (
    <>
      <div className="sh" id="promoMonth">
        {monthLabel || 'This month'}
      </div>
      <table className="tbl">
        <thead>
          <tr>
            <th
              data-sort="sort_name"
              scope="col"
              aria-sort={sortKey === 'sort_name' ? (sortDir > 0 ? 'ascending' : 'descending') : undefined}
              onClick={() => onHeaderClick('sort_name')}
            >
              Soldier
              <span className="so">{sortKey === 'sort_name' ? (sortDir > 0 ? '▲' : '▼') : ''}</span>
            </th>
            <th
              data-sort="was_ord"
              scope="col"
              aria-sort={sortKey === 'was_ord' ? (sortDir > 0 ? 'ascending' : 'descending') : undefined}
              onClick={() => onHeaderClick('was_ord')}
            >
              Was
              <span className="so">{sortKey === 'was_ord' ? (sortDir > 0 ? '▲' : '▼') : ''}</span>
            </th>
            <th
              data-sort="now_ord"
              scope="col"
              aria-sort={sortKey === 'now_ord' ? (sortDir > 0 ? 'ascending' : 'descending') : undefined}
              onClick={() => onHeaderClick('now_ord')}
            >
              Now
              <span className="so">{sortKey === 'now_ord' ? (sortDir > 0 ? '▲' : '▼') : ''}</span>
            </th>
            <th
              data-sort="when_jd"
              scope="col"
              aria-sort={sortKey === 'when_jd' ? (sortDir > 0 ? 'ascending' : 'descending') : undefined}
              onClick={() => onHeaderClick('when_jd')}
            >
              When
              <span className="so">{sortKey === 'when_jd' ? (sortDir > 0 ? '▲' : '▼') : ''}</span>
            </th>
          </tr>
        </thead>
        <tbody id="promoRows">
          {loading && (
            <tr>
              <td colSpan={4}>Loading this month’s promotions…</td>
            </tr>
          )}
          {error && rows && rows.length === 0 && (
            <tr>
              <td colSpan={4}>{error}</td>
            </tr>
          )}
          {!loading &&
            sorted.map((p) => (
              <tr key={p.user_id}>
                <td>{p.name}</td>
                <td className="rk">{p.was || '—'}</td>
                <td className="rk">{p.now}</td>
                <td>{p.when}</td>
              </tr>
            ))}
        </tbody>
      </table>
    </>
  );
}
