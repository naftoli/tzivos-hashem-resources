import { getCurrentMonth, getTodayHd, getWeekInfo } from '@/lib/hebrewWeek';

/**
 * Port of the DOM-writing half of legacy `setWeek()`. The monolith ran this once
 * against a document that held all ~690 pages at once; this SPA only ever mounts
 * one page fragment at a time, so this is re-run (via `ContentPage`, see its
 * effect) against whichever container is currently on screen. Every lookup is
 * null-guarded exactly like the original — most of these ids only exist on one
 * or two of the pages this function might be called against.
 *
 * Deliberately NOT ported: `hiskWkLabel`/`hiskWkCards`/`ordWkLabel` — grepping
 * the extracted `src/data/pages/*.json` fragments turns up no elements with
 * those ids anywhere, so that part of the legacy function is dead code here.
 * (This also covers v42's Yom Tov card, which `setWeek()` writes into
 * `hiskWkCards`: the Yom Tov re-pointing of `pe`/`ph`/`sl` below is ported, but
 * the card itself has no target element in this app.)
 */
export function applyWeekState(root: ParentNode): void {
  const info = getWeekInfo();
  const { ph: rawPh, hdheb, yomTov } = info;

  // When a Yom Tov falls today or within the next 7 days, `setWeek()` re-points
  // every "this week" reference at it: the parsha name/Hebrew/page-id are all
  // swapped for the Yom Tov's (`ph` blanked, since a Yom Tov has no parsha line).
  const pe = yomTov ? yomTov.nm : info.pe;
  const ph = yomTov ? '' : rawPh;
  const sl = yomTov ? yomTov.sl : info.sl;

  // --- home hero widget ---
  const elHeb = root.querySelector<HTMLElement>('#wkHeb');
  const elP = root.querySelector<HTMLElement>('#wkParsha');
  const elDH = root.querySelector<HTMLElement>('#wkDateHeb');
  const elB = root.querySelector<HTMLElement>('#wkBtn');
  if (elHeb) {
    // Yom Tov → its Hebrew name; otherwise the parsha line, left untouched when
    // there's no Hebrew parsha to show (mirrors the legacy ternary exactly).
    if (yomTov) elHeb.textContent = yomTov.heb || pe;
    else if (ph) elHeb.textContent = `פָּרָשַׁת ${ph}`;
  }
  if (pe && elP) elP.textContent = yomTov ? pe : `Parshas ${pe}`;
  if (hdheb && elDH) elDH.textContent = hdheb;
  if (sl && elP) elP.setAttribute('data-go', sl);
  if (sl && elB) elB.setAttribute('data-go', sl);

  // --- chitas tab's Chumash line ---
  const cC = root.querySelector<HTMLElement>('#chChumash');
  if (cC) cC.textContent = `${pe || 'This week'} — the day’s aliyah`;

  // --- promotions "this month" label ---
  const month = getCurrentMonth();
  if (month) {
    const pm = root.querySelector<HTMLElement>('#promoMonth');
    if (pm) pm.textContent = `This month · ${month.heb}`;

    // --- Month-list page: badge the current month ---
    const mpage = root.querySelector<HTMLElement>('[data-page="dMonth"]');
    if (mpage) {
      mpage.querySelectorAll('.now-badge').forEach((x) => {
        const b = x.closest('.sc');
        if (b) b.classList.remove('hasnow');
        x.remove();
      });
      const mc = mpage.querySelector(`[data-nk="${month.key}"]`);
      if (mc) {
        mc.classList.add('hasnow');
        mc.insertAdjacentHTML('afterbegin', '<span class="now-badge">This month</span>');
      }
    }
  }

  // --- Parsha-list page: badge the current week's parsha ---
  if (sl && sl.indexOf('dp') === 0 && sl !== 'dParsha') {
    const ppage = root.querySelector<HTMLElement>('[data-page="dParsha"]');
    if (ppage) {
      ppage.querySelectorAll('.now-badge').forEach((x) => {
        const b = x.closest('.sc');
        if (b) b.classList.remove('hasnow');
        x.remove();
      });
      const pc = ppage.querySelector(`[data-go="${sl}"]`);
      if (pc) {
        pc.classList.add('hasnow');
        pc.insertAdjacentHTML('afterbegin', '<span class="now-badge">This week</span>');
      }
    }
  }

  // --- full-year calendar: "Today" marker ---
  const hd = getTodayHd();
  if (hd) {
    root.querySelectorAll('.cal .d.today').forEach((c) => {
      c.classList.remove('today');
      const t = c.querySelector('.today-badge');
      if (t) t.remove();
    });
    root.querySelectorAll(`.cal [data-hd="${hd}"]`).forEach((c) => {
      c.classList.add('today');
      c.insertAdjacentHTML('afterbegin', '<span class="today-badge">Today</span>');
    });
  }
}
