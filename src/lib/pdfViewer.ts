/**
 * The site's own PDF reader for "View PDF" (the Hachayol bar).
 * It draws each page as a picture, so there is no download, save or print button,
 * and the file comes from siteApi.php only when asked with X-Resources-View: 1
 * (a plain link or the address bar can't send that).
 * Download PDF stays a normal link, and the admin only puts it in for levels allowed to download.
 */

let open: HTMLDivElement | null = null;

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
      pages.appendChild(canvas);
      await page.render({ canvas, viewport }).promise;
    }
    void task.destroy();
  } catch {
    if (msg.isConnected) msg.textContent = 'The file could not be opened.';
  }
}
