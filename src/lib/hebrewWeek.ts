import DATETBL from '@/data/static/DATETBL.json';
import DATEHD from '@/data/static/DATEHD.json';
import DATEMO from '@/data/static/DATEMO.json';
import MOHEB from '@/data/static/MOHEB.json';

/**
 * Port of the monolith's inline `(function(){ ... setWeek(); })()` block (the
 * script that used to sit right after the topbar/header markup). That script
 * computed "today"/"the coming Shabbos" against `DATETBL` and used the result
 * to fill in a handful of placeholder elements scattered across several pages
 * (home's hero widget, the topbar parsha strip, the Month/Parsha "now" badges
 * on the Date branch, the Promotions month label, and the "Today" marker on
 * the full-year calendar grid).
 *
 * Kept as pure date/lookup functions here so both `ContentPage` (which needs
 * to imperatively patch whichever page fragment is currently mounted) and
 * `Header` (persistent chrome, rendered once) can share one computation
 * instead of duplicating the table lookups.
 */

export interface DateTblEntry {
  /** English parsha name, e.g. "Eikev". Empty string on non-Shabbos days. */
  pe: string;
  /** Hebrew parsha name. Empty string on non-Shabbos days. */
  ph: string;
  /** The page id this date's parsha content lives under, e.g. "dpEikev". */
  sl: string;
  /** Hebrew date display string, e.g. "י״ח אָב". */
  hdheb: string;
}

const DATETBL_TYPED = DATETBL as Record<string, DateTblEntry>;
const DATEHD_TYPED = DATEHD as Record<string, string>;
const DATEMO_TYPED = DATEMO as Record<string, string>;
const MOHEB_TYPED = MOHEB as Record<string, string>;

function pad(n: number): string {
  return n < 10 ? `0${n}` : `${n}`;
}

/** Today's date as the `YYYY-MM-DD` key `DATETBL`/`DATEHD`/`DATEMO` are keyed by. */
export function todayKey(): string {
  const d = new Date();
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

/** The coming Shabbos (today if today IS Shabbos) as the same `YYYY-MM-DD` key. */
export function comingShabbosKey(): string {
  const d = new Date();
  const add = (6 - d.getDay() + 7) % 7;
  d.setDate(d.getDate() + add);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export interface WeekInfo {
  /** `DATETBL` entry for today, if today is within the table's range. */
  today: DateTblEntry | undefined;
  /** `DATETBL` entry for the coming Shabbos (falls back to `today` if missing). */
  week: DateTblEntry | undefined;
  /** English parsha name for the coming Shabbos, or '' if unavailable. */
  pe: string;
  /** Hebrew parsha name for the coming Shabbos, or '' if unavailable. */
  ph: string;
  /** Page id to navigate to for "this week"'s content. Defaults to 'dParsha'. */
  sl: string;
  /** Today's Hebrew date display string, or '' if unavailable. */
  hdheb: string;
}

/** Mirrors legacy `setWeek()`'s `today`/`wk`/`pe`/`ph`/`sl`/`hdheb` derivation. */
export function getWeekInfo(): WeekInfo {
  const today = DATETBL_TYPED[todayKey()];
  const week = DATETBL_TYPED[comingShabbosKey()] ?? today;
  return {
    today,
    week,
    pe: week?.pe ?? '',
    ph: week?.ph ?? '',
    sl: week?.sl ?? 'dParsha',
    hdheb: today?.hdheb ?? '',
  };
}

export interface MonthInfo {
  /** English month key, e.g. "Elul". */
  key: string;
  /** Hebrew month name, e.g. "אֱלוּל". */
  heb: string;
}

/** Mirrors legacy `setWeek()`'s `moKey`/`mh` derivation for the Promotions month label. */
export function getCurrentMonth(): MonthInfo | undefined {
  const key = DATEMO_TYPED[todayKey()];
  if (!key) return undefined;
  return { key, heb: MOHEB_TYPED[key] ?? key };
}

/** Today's `Month-Day` key into the full-year calendar's `data-hd` cells, e.g. "Av-18". */
export function getTodayHd(): string | undefined {
  return DATEHD_TYPED[todayKey()];
}
