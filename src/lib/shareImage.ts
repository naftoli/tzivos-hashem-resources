/** `data:image/jpeg;base64,...` -> `File`. Port of legacy `_dataURItoFile`. */
export function dataURItoFile(u: string, name: string): File {
  const parts = u.split(',');
  const mime = /:(.*?);/.exec(parts[0])?.[1] || 'image/jpeg';
  const bin = atob(parts[1]);
  const arr = new Uint8Array(bin.length);
  for (let n = 0; n < bin.length; n++) arr[n] = bin.charCodeAt(n);
  return new File([arr], name, { type: mime });
}

/** Re-encodes `dataURI` as a PNG blob (WhatsApp-paste friendly). Port of `_jpgToPngBlob`. */
export function jpgToPngBlob(dataURI: string): Promise<Blob> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => {
      const c = document.createElement('canvas');
      c.width = img.naturalWidth;
      c.height = img.naturalHeight;
      const ctx2d = c.getContext('2d');
      if (!ctx2d) {
        reject(new Error('ctx2d'));
        return;
      }
      ctx2d.drawImage(img, 0, 0);
      c.toBlob((b) => (b ? resolve(b) : reject(new Error('blob'))), 'image/png');
    };
    img.onerror = () => reject(new Error('img'));
    img.src = dataURI;
  });
}

/** Port of legacy `_copyImageToClipboard`. */
export function copyImageToClipboard(dataURI: string | undefined): Promise<void> {
  if (!dataURI || !(navigator.clipboard && window.ClipboardItem && navigator.clipboard.write)) {
    return Promise.reject(new Error('noclip'));
  }
  try {
    const item = new ClipboardItem({ 'image/png': jpgToPngBlob(dataURI) });
    return navigator.clipboard.write([item]);
  } catch (e) {
    return Promise.reject(e instanceof Error ? e : new Error('clip'));
  }
}

/** Port of legacy `_downloadFiles`. */
export function downloadFiles(files: File[]): void {
  files.forEach((f) => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(f);
    a.download = f.name;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
      URL.revokeObjectURL(a.href);
      a.remove();
    }, 1500);
  });
}
