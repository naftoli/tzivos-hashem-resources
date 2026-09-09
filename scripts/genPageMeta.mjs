// Derives the small, always-bundled page metadata (id -> branch, and the
// altcrumb branch for the ~121 date "issue" pages reachable from Campaign) from
// the extracted page bundles. Run this whenever the page bundles change:
//   node scripts/genPageMeta.mjs
// The HTML bodies themselves are loaded on demand (see src/data/pageBodies.ts);
// this file is what stays in the initial chunk so routing resolves synchronously.
import { readFileSync, writeFileSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const here = dirname(fileURLToPath(import.meta.url));
const pagesDir = resolve(here, '../src/data/pages');
const BRANCHES = ['home', 'date', 'campaign', 'rally', 'promotions', 'chitas'];
const ALTCRUMB_RE = /<template class="altcrumb" data-branch="([^"]+)"/;

const branch = {};
const altCrumb = {};
for (const b of BRANCHES) {
  const bundle = JSON.parse(readFileSync(resolve(pagesDir, `${b}.json`), 'utf8'));
  for (const [id, html] of Object.entries(bundle)) {
    branch[id] = b;
    const m = ALTCRUMB_RE.exec(html);
    if (m) altCrumb[id] = m[1];
  }
}

const out = resolve(here, '../src/data/static/pageMeta.json');
writeFileSync(out, JSON.stringify({ branch, altCrumb }));
console.error(
  `pageMeta.json: ${Object.keys(branch).length} pages, ${Object.keys(altCrumb).length} altcrumbs, ` +
    `${(readFileSync(out).length / 1024).toFixed(1)} KB`,
);
