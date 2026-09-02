import CALWEEK from '@/data/static/CALWEEK.json';
import CALMIV from '@/data/static/CALMIV.json';
import CALPROG from '@/data/static/CALPROG.json';
import CALCATN from '@/data/static/CALCATN.json';
import CATEMB from '@/data/static/CATEMB.json';
import MONTHORDER from '@/data/static/MONTHORDER.json';
import MOHEB from '@/data/static/MOHEB.json';
import NIGOLD from '@/data/static/NIGOLD.json';

/**
 * Port of the monolith's `openSched()`/older-PDF-list logic (see the big
 * `.schbtn`/`[data-older]` click-delegation chain that used to live right
 * after the topbar script). Kept as pure lookups here — no DOM — so the
 * `ScheduleModal` component (src/components/ScheduleModal.tsx) just renders
 * whatever these return.
 */

const CALWEEK_TYPED = CALWEEK as Array<[string, string]>;
const CALMIV_TYPED = CALMIV as Record<string, string>;
const CALPROG_TYPED = CALPROG as Array<{ hd: string; iso: string; cat: string; label: string }>;
const CALCATN_TYPED = CALCATN as Record<string, string>;
const CATEMB_TYPED = CATEMB as Record<string, string>;
const MONTHORDER_TYPED = MONTHORDER as string[];
const MOHEB_TYPED = MOHEB as Record<string, string>;
const NIGOLD_TYPED = NIGOLD as unknown as Record<string, Array<[string, string]>>;

const DOWF = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONF = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** `YYYY-MM-DD` -> "Fri Aug 30, 2026". */
export function fmtGreg(iso: string): string {
  const [y, m, d] = iso.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return `${DOWF[date.getDay()]} ${MONF[m - 1]} ${d}, ${y}`;
}

/** `"Elul-17"` -> `"17 Elul"` (Hebrew-date key -> display order). */
export function fmtHd(hd: string): string {
  const i = hd.lastIndexOf('-');
  return `${hd.slice(i + 1)} ${hd.slice(0, i)}`;
}

export interface SchedRow {
  a: string;
  b: string;
  c: string;
}

export interface SchedResult {
  title: string;
  /** Small icon shown next to the title, when the category has one (CATEMB). */
  embed?: string;
  rows: SchedRow[];
}

/** Mirrors legacy `openSched(id)`'s row-building for the three id shapes it handles. */
export function buildSchedRows(id: string): SchedResult {
  let title: string;
  let rows: SchedRow[];

  if (id === 'weekly') {
    title = 'Weekly Workflow';
    rows = CALWEEK_TYPED.map(([a, c]) => ({ a, b: '', c }));
  } else if (id === 'mivtza') {
    title = 'Mivtza of the Month';
    rows = MONTHORDER_TYPED.filter((m) => CALMIV_TYPED[m]).map((m) => ({
      a: MOHEB_TYPED[m] || m,
      b: '',
      c: CALMIV_TYPED[m],
    }));
  } else {
    title = CALCATN_TYPED[id] || id;
    rows = CALPROG_TYPED.filter((e) => e.cat === id)
      .slice()
      .sort((x, y) => (x.iso < y.iso ? -1 : 1))
      .map((e) => ({ a: fmtGreg(e.iso), b: fmtHd(e.hd), c: e.label }));
  }

  return { title, embed: CATEMB_TYPED[id], rows };
}

export interface OlderRow {
  label: string;
  href: string;
}

/** Mirrors legacy `[data-older]` handler: the older-year niggun PDF list, keyed by year suffix. */
export function buildOlderRows(key: string): OlderRow[] {
  const list = NIGOLD_TYPED[key] || [];
  return list.map(([label, href]) => ({ label, href }));
}
