import type { PageBundle } from '@/types';
import { pageBranch } from '@/data/pageMeta';
import home from '@/data/pages/home.json';
import rally from '@/data/pages/rally.json';
import promotions from '@/data/pages/promotions.json';
import chitas from '@/data/pages/chitas.json';

/**
 * On-demand loader for page HTML bodies.
 *
 * The two large text branches — `date` (~1.45 MB) and `campaign` (~2.81 MB) —
 * are dynamic `import()`s, so Vite emits each as its own chunk that only loads
 * when a page in that branch is first visited. The small branches (home, rally,
 * promotions, chitas — ~20 KB total, and home is the landing page) stay in the
 * initial chunk so the first paint needs no extra round-trip.
 */
const STATIC: Record<string, PageBundle> = {
  home: home as PageBundle,
  rally: rally as PageBundle,
  promotions: promotions as PageBundle,
  chitas: chitas as PageBundle,
};

const LAZY: Record<string, () => Promise<{ default: PageBundle }>> = {
  date: () => import('./pages/date.json'),
  campaign: () => import('./pages/campaign.json'),
};

const lazyCache: Record<string, PageBundle> = {};

/**
 * The extracted fragments carry `class="page"`, which is `display:none` per
 * index.css until a `.on` toggles it. This SPA only ever mounts the current
 * route's page, so it should always render visible. (Ported verbatim from the
 * old pageRegistry's `markOn`.)
 */
function markOn(html: string): string {
  return html.replace('class="page"', 'class="page on"');
}

async function bundleFor(branch: string): Promise<PageBundle | null> {
  if (STATIC[branch]) return STATIC[branch];
  if (lazyCache[branch]) return lazyCache[branch];
  const loader = LAZY[branch];
  if (!loader) return null;
  const mod = await loader();
  lazyCache[branch] = mod.default;
  return mod.default;
}

/** Resolves a page's ready-to-render HTML, loading its branch chunk if needed. */
export async function loadPageHtml(pageId: string): Promise<string | null> {
  const branch = pageBranch[pageId];
  if (!branch) return null;
  const bundle = await bundleFor(branch);
  const html = bundle?.[pageId];
  return html == null ? null : markOn(html);
}

/**
 * Synchronous fast path: the page's HTML if its branch is already available
 * (a static branch, or a lazy branch whose chunk has been loaded this session),
 * else `null` — meaning the caller must `loadPageHtml` and wait. Lets callers
 * skip the loading state entirely for the common already-loaded case.
 */
export function peekPageHtml(pageId: string): string | null {
  const branch = pageBranch[pageId];
  if (!branch) return null;
  const bundle = STATIC[branch] ?? lazyCache[branch];
  const html = bundle?.[pageId];
  return html == null ? null : markOn(html);
}
