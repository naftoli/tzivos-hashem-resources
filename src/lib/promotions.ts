/**
 * Port of the legacy monolith's Promotions tab script — a genuine live fetch
 * (scoped server-side by the login, same as the rest of the app), not
 * table-derived. See `promotions.php` (same origin, `credentials:'include'`)
 * for the exact response shape this mirrors.
 *
 * Split from the React rendering (src/components/pages/PromotionsTable.tsx)
 * — this module is pure data + pure sort, no DOM.
 */

import { phpUrl } from '@/lib/phpUrl';

const ENDPOINT = phpUrl('promotions.php');

export interface Promotion {
  user_id: number;
  name: string;
  sort_name: string;
  was: string | null;
  now: string;
  was_ord: number;
  now_ord: number;
  when_jd: number;
  when: string;
  when_he?: string;
}

export interface PromotionsMonth {
  he?: string;
  en?: string;
}

export interface PromotionsData {
  month: PromotionsMonth;
  promotions: Promotion[];
}

interface PromotionsApiResponse {
  success?: boolean;
  error?: string;
  message?: string;
  data?: PromotionsData;
}

export type PromotionSortKey = 'sort_name' | 'was_ord' | 'now_ord' | 'when_jd';

/** Fetches this Hebrew month's promotions for whoever is logged in. */
export async function fetchPromotions(): Promise<PromotionsData> {
  const r = await fetch(ENDPOINT, { credentials: 'include' });
  if (!r.ok) throw new Error(String(r.status));
  const res = (await r.json()) as PromotionsApiResponse;
  if (!res || !res.success) throw new Error(res?.error || res?.message || 'failed');
  return res.data || { month: {}, promotions: [] };
}

/**
 * Port of legacy `draw()`'s sort — a stable sort by `key` (numeric or
 * string, whichever `rows[key]` happens to hold), falling back to `name`
 * localeCompare on ties, then flipped by `dir`. Pure, so the component just
 * calls this from a `useMemo` instead of re-deriving `rows` in place.
 */
export function sortPromotions(rows: Promotion[], key: PromotionSortKey | null, dir: 1 | -1): Promotion[] {
  if (!key) return rows;
  const list = rows.slice();
  list.sort((a, b) => {
    const x = a[key];
    const y = b[key];
    let d: number;
    if (typeof x === 'number' || typeof y === 'number') d = (Number(x) || 0) - (Number(y) || 0);
    else d = String(x || '').localeCompare(String(y || ''));
    return (d || String(a.name || '').localeCompare(String(b.name || ''))) * dir;
  });
  return list;
}
