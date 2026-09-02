import type { MouseEvent } from 'react';
import { hkCardShouldHide } from '@/lib/contentFilters';
import { openTefPdf } from '@/lib/tefPdf';
import TEFN from '@/data/static/TEFN.json';

const TEFN_TYPED = TEFN as Record<string, number>;

export interface ContentClickHandlers {
  openSched: (id: string) => void;
  openOlder: (key: string) => void;
  openLightbox: (ids: string) => void;
  navigateBack: (fallback: string) => void;
  go: (id: string) => void;
}

/**
 * Port of the monolith's `document.addEventListener('click', ...)` chain
 * (schedule, filters, jumps, lightbox, tefila, `[data-go]` / `[data-back]`).
 * Returns true when the click was handled so the caller can skip the rest.
 */
export function dispatchContentClick(
  event: MouseEvent<HTMLElement>,
  scope: ParentNode,
  handlers: ContentClickHandlers,
): boolean {
  const el = event.target as HTMLElement;

  const schBtn = el.closest<HTMLElement>('.schbtn');
  if (schBtn) {
    const id = schBtn.getAttribute('data-sch');
    if (id) {
      event.preventDefault();
      handlers.openSched(id);
      return true;
    }
  }

  const olderBtn = el.closest<HTMLElement>('[data-older]');
  if (olderBtn) {
    event.preventDefault();
    handlers.openOlder(olderBtn.getAttribute('data-older') ?? '');
    return true;
  }

  const catTog = el.closest<HTMLElement>('.cattog');
  if (catTog) {
    event.preventDefault();
    const cat = catTog.getAttribute('data-cat');
    if (cat) {
      const on = document.documentElement.classList.toggle(`on-${cat}`);
      document.querySelectorAll(`.cattog[data-cat="${cat}"]`).forEach((b) => {
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
    }
    return true;
  }

  const hkTog = el.closest<HTMLElement>('.hktog');
  if (hkTog) {
    event.preventDefault();
    const filterScope = hkTog.closest('.wrap') ?? scope;
    const v = hkTog.getAttribute('data-hk') ?? '';
    filterScope.querySelectorAll('.hktog').forEach((b) => {
      b.setAttribute('aria-pressed', b === hkTog ? 'true' : 'false');
    });
    filterScope.querySelectorAll('.hkcard').forEach((c) => {
      c.classList.toggle('mvhide', hkCardShouldHide(c.getAttribute('data-hk'), v));
    });
    return true;
  }

  const mvTab = el.closest<HTMLElement>('.mvtab');
  if (mvTab) {
    event.preventDefault();
    const filterScope = mvTab.closest('.wrap') ?? scope;
    filterScope.querySelectorAll('.mvtog').forEach((b) => b.setAttribute('aria-pressed', 'false'));
    filterScope.querySelectorAll('.mvcard').forEach((c) => c.classList.remove('mvhide'));
    filterScope.querySelectorAll('.mvtab').forEach((b) => b.classList.toggle('on', b === mvTab));
    const id = mvTab.getAttribute('data-mvtab');
    filterScope.querySelectorAll('.mvgrid').forEach((g) => g.classList.toggle('off', g.id !== id));
    return true;
  }

  const mvTog = el.closest<HTMLElement>('.mvtog');
  if (mvTog) {
    event.preventDefault();
    const filterScope = mvTog.closest('.wrap') ?? scope;
    const was = mvTog.getAttribute('aria-pressed') === 'true';
    filterScope.querySelectorAll('.mvtog').forEach((b) => b.setAttribute('aria-pressed', 'false'));
    if (was) {
      filterScope.querySelectorAll('.mvcard').forEach((c) => c.classList.remove('mvhide'));
      const activeTab = filterScope.querySelector('.mvtab.on');
      const id = activeTab ? activeTab.getAttribute('data-mvtab') : null;
      filterScope.querySelectorAll('.mvgrid').forEach((g) => g.classList.toggle('off', id !== null && g.id !== id));
    } else {
      mvTog.setAttribute('aria-pressed', 'true');
      const mv = mvTog.getAttribute('data-mv');
      filterScope.querySelectorAll('.mvgrid').forEach((g) => g.classList.remove('off'));
      filterScope.querySelectorAll('.mvcard').forEach((c) => c.classList.toggle('mvhide', c.getAttribute('data-mv') !== mv));
    }
    return true;
  }

  const jumpBtn = el.closest<HTMLElement>('.yjbtn,.chumjump');
  if (jumpBtn) {
    const targetId = jumpBtn.getAttribute('data-jump') ?? jumpBtn.getAttribute('data-cj');
    const targetEl = targetId ? document.getElementById(targetId) : null;
    if (targetEl) {
      event.preventDefault();
      targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    return true;
  }

  const zoomImg = el.closest<HTMLImageElement>('img.zoom');
  if (zoomImg) {
    event.preventDefault();
    event.stopPropagation();
    const row = zoomImg.closest('.pagerow');
    if (row) {
      const ids = Array.from(row.querySelectorAll('img.zoom'))
        .map((x) => x.getAttribute('data-img'))
        .filter((v): v is string => Boolean(v));
      handlers.openLightbox(ids.join(','));
    } else {
      const id = zoomImg.getAttribute('data-img');
      if (id) handlers.openLightbox(id);
    }
    return true;
  }

  const fullBtn = el.closest<HTMLElement>('[data-full]');
  if (fullBtn) {
    event.preventDefault();
    event.stopPropagation();
    const ids = fullBtn.getAttribute('data-full');
    if (ids) handlers.openLightbox(ids);
    return true;
  }

  const tefView = el.closest<HTMLElement>('[data-tefview]');
  if (tefView) {
    event.preventDefault();
    event.stopPropagation();
    const sg = tefView.getAttribute('data-tefview');
    if (sg) {
      const n = TEFN_TYPED[sg] || 0;
      const ids: string[] = [];
      for (let p = 1; p <= n; p++) ids.push(`tefp_${sg}_${p < 10 ? '0' + p : p}`);
      if (ids.length) handlers.openLightbox(ids.join(','));
    }
    return true;
  }

  const tefPdfBtn = el.closest<HTMLElement>('[data-tefpdf]');
  if (tefPdfBtn) {
    event.preventDefault();
    event.stopPropagation();
    const slug = tefPdfBtn.getAttribute('data-tefpdf');
    if (slug) openTefPdf(slug);
    return true;
  }

  const backBtn = el.closest<HTMLElement>('[data-back]');
  if (backBtn) {
    event.preventDefault();
    const fallback = backBtn.getAttribute('data-back');
    if (fallback) handlers.navigateBack(fallback);
    return true;
  }

  const tpLb = el.closest<HTMLElement>('.tp-lb');
  if (tpLb) {
    event.preventDefault();
    const ids = tpLb.getAttribute('data-lb');
    if (ids) handlers.openLightbox(ids);
    return true;
  }

  const goBtn = el.closest<HTMLElement>('[data-go]');
  if (goBtn) {
    event.preventDefault();
    const id = goBtn.getAttribute('data-go');
    if (id) handlers.go(id);
    return true;
  }

  return false;
}
