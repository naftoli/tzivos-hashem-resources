import { phpUrl } from '@/lib/phpUrl';
import type { SearchEntry } from '@/lib/search';

/**
 * The public site asks Mashpia's siteApi.php (next to checkAuth.php).
 * That file talks to the admin with the secret, so the secret never reaches the browser.
 * If the file is missing or the admin is down, these calls fail and the site keeps its built-in pages.
 */

export interface RemoteSearchHit extends SearchEntry {
  href?: string | null;
}

const TIMEOUT_MS = 4000;

async function ask(query: string): Promise<Response | null> {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    return await fetch(`${phpUrl('siteApi.php')}?${query}`, {
      credentials: 'include',
      headers: { Accept: 'application/json' },
      signal: ctrl.signal,
    });
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

/** One page's HTML from the admin, or null when the admin can't be reached. */
export async function fetchRemotePageHtml(pageId: string): Promise<string | null> {
  const response = await ask(`page=${encodeURIComponent(pageId)}`);
  if (!response?.ok) return null;
  try {
    const data = (await response.json()) as { html?: unknown };
    return typeof data.html === 'string' && data.html !== '' ? data.html : null;
  } catch {
    return null;
  }
}

/**
 * Search results the signed-in person is allowed to see, or null when the admin
 * can't be reached (the caller then uses the built-in search list).
 */
export async function fetchRemoteSearch(query: string): Promise<RemoteSearchHit[] | null> {
  const response = await ask(`q=${encodeURIComponent(query)}`);
  if (!response?.ok) return null;
  try {
    const data = (await response.json()) as { results?: RemoteSearchHit[] };
    return Array.isArray(data.results) ? data.results : null;
  } catch {
    return null;
  }
}
