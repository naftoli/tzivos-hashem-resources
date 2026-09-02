import type { BranchId, PageBundle } from '@/types';
import home from '@/data/pages/home.json';
import date from '@/data/pages/date.json';
import campaign from '@/data/pages/campaign.json';
import rally from '@/data/pages/rally.json';
import promotions from '@/data/pages/promotions.json';
import chitas from '@/data/pages/chitas.json';

export interface RegistryEntry {
  /** The page's `<section class="page on" data-page="ID">...</section>` HTML, ready to render. */
  html: string;
  branch: BranchId;
}

// marking.json/calendar.json exist alongside these but are deliberately excluded:
// those two branches are live-tool embeds rendered by MarkingPage/CalendarPage, not
// generic content pages.
const BUNDLES: Array<[BranchId, PageBundle]> = [
  ['home', home],
  ['date', date],
  ['campaign', campaign],
  ['rally', rally],
  ['promotions', promotions],
  ['chitas', chitas],
];

function markOn(html: string): string {
  // The extracted fragments carry `class="page"`, which is `display:none` per
  // index.css until a `.on` toggles it. The legacy app kept every page mounted
  // and toggled visibility; this app only ever mounts the current route's page,
  // so it should always render visible.
  return html.replace('class="page"', 'class="page on"');
}

export const pageRegistry: Record<string, RegistryEntry> = {};

// pageId -> the `data-branch` of its `<template class="altcrumb" data-branch="...">`,
// for the ~121 date-branch "issue" pages (i660orders, i660rebbe, ...) that are also
// reachable from Campaign (see cHiskashrus's `[data-go]` cards) and show a different
// breadcrumb in that context. Ports the legacy `t.querySelector('template.altcrumb')`
// lookup as a one-time regex pass over the extracted HTML, so `App.tsx`/`ContentPage`
// can make the swap decision (see `src/lib/navState.ts`'s `wantedBranch`) without
// re-parsing the DOM on every render.
export const altCrumbBranch: Record<string, string> = {};
const ALTCRUMB_RE = /<template class="altcrumb" data-branch="([^"]+)"/;

for (const [branch, bundle] of BUNDLES) {
  for (const [id, html] of Object.entries(bundle)) {
    pageRegistry[id] = { html: markOn(html), branch };
    const m = ALTCRUMB_RE.exec(html);
    if (m) altCrumbBranch[id] = m[1];
  }
}
