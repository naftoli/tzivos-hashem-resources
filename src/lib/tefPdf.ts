import TEFPDF from '@/data/static/TEFPDF.json';

const TEFPDF_TYPED = TEFPDF as Record<string, string>;

/**
 * Port of legacy `[data-tefpdf]`'s handler. The original expects `TEFPDF[slug]`
 * to be a `data:application/pdf;base64,...` URI, which it decodes into a Blob
 * and opens via an object URL; if that decode fails for any reason it falls
 * back to `window.open(u, '_blank')` on the raw value.
 *
 * `src/data/static/TEFPDF.json` (an extraction-pipeline input, not touched here)
 * currently holds plain relative paths (e.g. `"pirush-hamilos/g1.pdf"`), not
 * data URIs — matching the legacy markup's own
 * `<div class="note">PDF links open once the files are hosted alongside the
 * site.</div>` note, i.e. this is intentionally not finished upstream yet. With
 * a plain path, `u.split(',')[1]` is `undefined` and `atob(undefined)` throws
 * (an odd-length invalid base64 string), so this hits the exact same fallback
 * branch the legacy code does — `window.open(u, '_blank')` — which is the
 * correct behavior for a plain path anyway. Ported as-is (both branches) so
 * this keeps working unmodified once TEFPDF.json's values become real data URIs.
 */
export function openTefPdf(slug: string): void {
  const u = TEFPDF_TYPED[slug];
  if (!u) return;
  try {
    const b64 = u.split(',')[1];
    const bin = atob(b64);
    const len = bin.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) bytes[i] = bin.charCodeAt(i);
    const blob = new Blob([bytes], { type: 'application/pdf' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.target = '_blank';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 60000);
  } catch {
    window.open(u, '_blank');
  }
}
