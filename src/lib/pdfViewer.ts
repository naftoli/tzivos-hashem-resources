/**
 * The site's own PDF reader for "View PDF" (the Hachayol bar).
 * It draws each page as a picture, so there is no download, save or print button,
 * and the file comes from siteApi.php only when asked with X-Resources-View: 1
 * (a plain link or the address bar can't send that).
 * Download PDF stays a normal link, and the admin only puts it in for levels allowed to download.
 */

let open: HTMLDivElement | null = null;

/** The watermark: the signed-in person's email (set by AuthGate from checkAuth.php), or "Tzivos Hashem" */
let mark = 'Tzivos Hashem';
export function setWatermarkText(text: unknown): void {
  mark = typeof text === 'string' && text.trim() !== '' ? text.trim() : 'Tzivos Hashem';
}

/** Faint diagonal repeated text, drawn onto the page picture itself */
function bakeWatermark(canvas: HTMLCanvasElement): void {
  const ctx = canvas.getContext('2d');
  if (!ctx) return;
  const size = Math.max(14, Math.round(canvas.width / 28));
  ctx.save();
  ctx.font = `600 ${size}px system-ui, Arial, sans-serif`;
  ctx.fillStyle = 'rgba(20, 30, 60, 0.13)';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.translate(canvas.width / 2, canvas.height / 2);
  ctx.rotate(-Math.PI / 6);
  const stepX = ctx.measureText(mark).width + size * 3;
  const stepY = size * 5;
  const reach = Math.hypot(canvas.width, canvas.height);
  for (let y = -reach; y <= reach; y += stepY) {
    const shift = (Math.round(y / stepY) % 2) * (stepX / 2);
    for (let x = -reach; x <= reach; x += stepX) ctx.fillText(mark, x + shift, y);
  }
  ctx.restore();
}

/** The same watermark as a see-through layer over the page */
function overlayStyle(): string {
  const t = mark.replace(/[<>&"']/g, (c) => `&#${c.charCodeAt(0)};`);
  const w = Math.max(260, mark.length * 11 + 80);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="150"><text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" transform="rotate(-30 ${w / 2} 75)" font-family="system-ui, Arial, sans-serif" font-size="18" font-weight="600" fill="rgba(20,30,60,0.10)">${t}</text></svg>`;
  return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`;
}

function stopKeys(e: KeyboardEvent): void {
  if (!open) return;
  if (e.key === 'Escape') {
    closePdfViewer();
    return;
  }
  const k = e.key.toLowerCase();
  // save, print, view source
  if ((e.ctrlKey || e.metaKey) && (k === 's' || k === 'p' || k === 'u')) {
    e.preventDefault();
    e.stopPropagation();
  }
}

export function closePdfViewer(): void {
  if (!open) return;
  open.remove();
  open = null;
  document.documentElement.classList.remove('pdfv-on');
  document.removeEventListener('keydown', stopKeys, true);
}

export async function openPdfViewer(url: string, title: string): Promise<void> {
  closePdfViewer();
  const root = document.createElement('div');
  root.className = 'pdfv';
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-modal', 'true');
  root.setAttribute('aria-label', title || 'PDF');
  root.innerHTML =
    '<div class="pdfv-top"><span class="pdfv-t"></span><button type="button" class="pdfv-x">Close</button></div>' +
    '<div class="pdfv-pages"><p class="pdfv-msg" role="status">Loading…</p></div>';
  (root.querySelector('.pdfv-t') as HTMLElement).textContent = title;
  root.querySelector('.pdfv-x')!.addEventListener('click', closePdfViewer);
  root.addEventListener('contextmenu', (e) => e.preventDefault());
  root.addEventListener('dragstart', (e) => e.preventDefault());
  document.body.appendChild(root);
  document.documentElement.classList.add('pdfv-on');
  document.addEventListener('keydown', stopKeys, true);
  open = root;

  const pages = root.querySelector('.pdfv-pages') as HTMLElement;
  const msg = pages.querySelector('.pdfv-msg') as HTMLElement;
  try {
    const res = await fetch(url, { credentials: 'include', cache: 'no-store', headers: { 'X-Resources-View': '1' } });
    if (!res.ok) {
      msg.textContent = res.status === 403 ? "You don't have access to this file right now." : 'The file could not be opened.';
      return;
    }
    const data = new Uint8Array(await res.arrayBuffer());
    const pdfjs = await import('pdfjs-dist');
    // built as a plain .js worker file, so the server sends it with the right type
    const PdfWorker = (await import('pdfjs-dist/build/pdf.worker.min.mjs?worker')).default;
    if (!pdfjs.GlobalWorkerOptions.workerPort) pdfjs.GlobalWorkerOptions.workerPort = new PdfWorker();
    const task = pdfjs.getDocument({ data });
    const doc = await task.promise;
    if (open !== root) return;
    msg.remove();
    const width = Math.min(pages.clientWidth - 24, 1000);
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    for (let n = 1; n <= doc.numPages; n++) {
      if (open !== root) return;
      const page = await doc.getPage(n);
      const base = page.getViewport({ scale: 1 });
      const viewport = page.getViewport({ scale: (width / base.width) * ratio });
      const canvas = document.createElement('canvas');
      canvas.className = 'pdfv-page';
      canvas.width = Math.floor(viewport.width);
      canvas.height = Math.floor(viewport.height);
      canvas.style.width = `${Math.floor(viewport.width / ratio)}px`;
      canvas.setAttribute('aria-label', `Page ${n}`);
      const sheet = document.createElement('div');
      sheet.className = 'pdfv-sheet';
      const layer = document.createElement('div');
      layer.className = 'pdfv-mark';
      layer.style.backgroundImage = overlayStyle();
      sheet.append(canvas, layer);
      pages.appendChild(sheet);
      await page.render({ canvas, viewport }).promise;
      bakeWatermark(canvas);
    }
    void task.destroy();
  } catch {
    if (msg.isConnected) msg.textContent = 'The file could not be opened.';
  }
}
