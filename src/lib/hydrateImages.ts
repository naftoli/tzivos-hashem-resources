import IMG from '@/data/static/IMG.json';
import HW from '@/data/static/HW.json';
import NP from '@/data/static/NP.json';

const IMG_TYPED = IMG as Record<string, string>;
const HW_TYPED = HW as Record<string, string>;
const NP_TYPED = NP as Record<string, string>;

/**
 * Port of the monolith's boot-time image pass (right after `branchOf`):
 * `<img data-img>` / `data-hw` / `data-np` have no `src` in the extracted
 * fragments — the maps hold the real `/resources/assets/img/...` URLs.
 * Missing keys remove the picture (or its `.handimg` / `.nigpanel` wrapper),
 * same as the original.
 */
export function hydrateImages(root: ParentNode): void {
  root.querySelectorAll<HTMLImageElement>('[data-img]').forEach((im) => {
    const u = IMG_TYPED[im.getAttribute('data-img') ?? ''];
    if (u) im.src = u;
    else im.remove();
  });
  root.querySelectorAll<HTMLImageElement>('[data-hw]').forEach((im) => {
    const u = HW_TYPED[im.getAttribute('data-hw') ?? ''];
    if (u) im.src = u;
    else im.closest('.handimg')?.remove();
  });
  root.querySelectorAll<HTMLImageElement>('[data-np]').forEach((im) => {
    const u = NP_TYPED[im.getAttribute('data-np') ?? ''];
    if (u) {
      im.src = u;
      im.className = 'zoom';
    } else {
      im.closest('.nigpanel')?.remove();
    }
  });
}
