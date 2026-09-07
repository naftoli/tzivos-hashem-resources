import DATETBL from '@/data/static/DATETBL.json';
import DATEHD from '@/data/static/DATEHD.json';
import DATEMO from '@/data/static/DATEMO.json';
import DATEYT from '@/data/static/DATEYT.json';
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

/** A `DATEYT` entry: an upcoming Yom Tov that the "this week" widgets point at. */
export interface DateYtEntry {
  /** English Yom Tov name, e.g. "Sukkos" (matches the `YTHEB` keys below). */
  nm: string;
  /** The page id this Yom Tov's content lives under, e.g. "dytSukkos". */
  sl: string;
}

const DATETBL_TYPED = DATETBL as Record<string, DateTblEntry>;
const DATEHD_TYPED = DATEHD as Record<string, string>;
const DATEMO_TYPED = DATEMO as Record<string, string>;
const DATEYT_TYPED = DATEYT as Record<string, DateYtEntry>;
const MOHEB_TYPED = MOHEB as Record<string, string>;

/**
 * Hebrew display names for each Yom Tov `nm`, mirroring the monolith's inline
 * `YTHEB` map in `setWeek()`. Used only for the home hero widget's Hebrew line.
 */
const YTHEB: Record<string, string> = {
  'Rosh Hashanah': 'רֹאשׁ הַשָּׁנָה',
  'Yom Kippur': 'יוֹם כִּפּוּר',
  Sukkos: 'סֻכּוֹת',
  'Shemini Atzeres & Simchas Torah': 'שְׁמִינִי עֲצֶרֶת',
  Chanukah: 'חֲנֻכָּה',
  'Tu B’Shvat': 'ט״ו בִּשְׁבָט',
  Purim: 'פּוּרִים',
  Pesach: 'פֶּסַח',
  'Pesach Sheni': 'פֶּסַח שֵׁנִי',
  'Lag B’Omer': 'ל״ג בָּעוֹמֶר',
  Shavuos: 'שָׁבוּעוֹת',
  '15 Av': 'ט״ו בְּאָב',
};

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

/** An upcoming Yom Tov (today or within the next 7 days), resolved for display. */
export interface UpcomingYomTov {
  /** English Yom Tov name, e.g. "Sukkos". */
  nm: string;
  /** Page id for the Yom Tov's content, e.g. "dytSukkos". */
  sl: string;
  /** Hebrew display name for the hero widget, e.g. "סֻכּוֹת" (falls back to `nm`). */
  heb: string;
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
  /**
   * The Yom Tov falling today or within the next 7 days, if any. When set, the
   * monolith's `setWeek()` re-points every "this week" reference (home hero,
   * Chitas aliyah line, Parsha "now" badge) at the Yom Tov instead of the
   * coming-Shabbos parsha — see `applyWeekState`. The topbar strip is left on
   * the raw parsha (`pe`), matching the legacy markup that `setWeek()` never
   * touched.
   */
  yomTov: UpcomingYomTov | null;
}

/**
 * Mirrors legacy `setWeek()`'s `ytHit` scan: the nearest Yom Tov landing today
 * or within the next 7 days, or `null`. Same 0..7 inclusive day window and same
 * `YYYY-MM-DD` keying the monolith used.
 */
export function getUpcomingYomTov(): UpcomingYomTov | null {
  const d = new Date();
  for (let i = 0; i <= 7; i++) {
    const key = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const hit = DATEYT_TYPED[key];
    if (hit) return { nm: hit.nm, sl: hit.sl, heb: YTHEB[hit.nm] ?? hit.nm };
    d.setDate(d.getDate() + 1);
  }
  return null;
}

/** Mirrors legacy `setWeek()`'s `today`/`wk`/`pe`/`ph`/`sl`/`hdheb`/`ytHit` derivation. */
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
    yomTov: getUpcomingYomTov(),
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
