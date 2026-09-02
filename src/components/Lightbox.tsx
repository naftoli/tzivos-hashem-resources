import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type MouseEvent,
  type ReactNode,
} from 'react';
import IMG from '@/data/static/IMG.json';
import { copyImageToClipboard, dataURItoFile, downloadFiles } from '@/lib/shareImage';
import { useEscapeToClose } from '@/lib/useEscapeToClose';

const IMG_TYPED = IMG as Record<string, string>;

interface LightboxContextValue {
  /** Opens the lightbox for a comma-separated list of IMG ids (`img.zoom`'s
   * `.pagerow`-grouped ids, `[data-full]`, `[data-tefview]`'s generated
   * `tefp_*` ids, or `.tp-lb`'s `data-lb`). Mirrors legacy `openLB(ids)`. */
  openLightbox: (ids: string) => void;
}

const LightboxContext = createContext<LightboxContextValue | null>(null);

/** Port of legacy `openLB`/`closeLB`/`shareLB` as real React state. */
export function useLightbox(): LightboxContextValue {
  const ctx = useContext(LightboxContext);
  if (!ctx) throw new Error('useLightbox must be used within a LightboxProvider');
  return ctx;
}

type LightboxState = { open: false } | { open: true; ids: string[]; issue: string };

export function LightboxProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<LightboxState>({ open: false });
  const rootRef = useRef<HTMLDivElement>(null);
  const spreadRef = useRef<HTMLDivElement>(null);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const [toast, setToast] = useState<{ msg: string; show: boolean }>({ msg: '', show: false });

  const openLightbox = useCallback((idsRaw: string) => {
    const list = String(idsRaw)
      .split(',')
      .map((s) => s.trim())
      .filter(Boolean);
    if (!list.some((id) => IMG_TYPED[id])) return;
    const m = /^i(\d+)sect/.exec(list[0] || '');
    setState({ open: true, ids: list, issue: m ? m[1] : '' });
  }, []);

  const close = useCallback(() => {
    setState({ open: false });
    if (document.fullscreenElement) {
      document.exitFullscreen().catch(() => {});
    }
  }, []);

  useEscapeToClose(state.open, close);

  // lbspread.scrollTop=0 on every open, same as legacy openLB.
  useEffect(() => {
    if (state.open && spreadRef.current) spreadRef.current.scrollTop = 0;
  }, [state]);

  // lb.requestFullscreen(), same as legacy openLB (best-effort — declined/
  // unsupported is silently ignored, same try/catch).
  useEffect(() => {
    if (state.open && rootRef.current?.requestFullscreen) {
      rootRef.current.requestFullscreen().catch(() => {});
    }
  }, [state.open]);

  function onBackdropClick(e: MouseEvent<HTMLDivElement>) {
    if (e.target === e.currentTarget) close();
  }

  function showToast(msg: string) {
    setToast({ msg, show: true });
    clearTimeout(toastTimer.current);
    toastTimer.current = setTimeout(() => setToast((t) => ({ ...t, show: false })), 6000);
  }

  // Port of legacy `_desktopShare`: opens WhatsApp Web, copies page 1 to the
  // clipboard so it can be pasted into a chat, and (if there's more than one
  // page) downloads the rest for the visitor to attach manually.
  async function desktopShare(files: File[], firstUrl: string | undefined) {
    const mod = navigator.platform && navigator.platform.indexOf('Mac') >= 0 ? '⌘' : 'Ctrl';
    window.open('https://web.whatsapp.com/', '_blank', 'noopener');
    try {
      await copyImageToClipboard(firstUrl);
      showToast(
        `Picture copied — in WhatsApp, open your chat and press ${mod}+V to paste it` +
          (files.length > 1 ? ' (page 1; use the other pages from your downloads)' : '') +
          '.',
      );
      if (files.length > 1) downloadFiles(files.slice(1));
    } catch {
      downloadFiles(files);
      showToast('Picture saved to your downloads — drag it into your WhatsApp chat.');
    }
  }

  // Port of legacy `shareLB`.
  const shareLightbox = useCallback(async () => {
    if (!state.open) return;
    const { ids, issue } = state;
    const title = issue ? `Hachayol #${issue}` : 'Hachayol';
    const urls = ids.map((id) => IMG_TYPED[id]).filter(Boolean);
    if (!urls.length) return;

    let files: File[];
    try {
      files = await Promise.all(
        urls.map(async (u, i) => {
          const name = `Hachayol-${issue || 'page'}-p${i + 1}.jpg`;
          if (u.indexOf('data:') === 0) return dataURItoFile(u, name);
          const r = await fetch(u);
          const b = await r.blob();
          return new File([b], name, { type: b.type || 'image/jpeg' });
        }),
      );
    } catch {
      // fetch blocked -> nothing to share, same as legacy's swallowed .catch()
      return;
    }

    const canFiles = !!(navigator.canShare && navigator.canShare({ files }));
    if (navigator.share && canFiles) {
      try {
        await navigator.share({ files, title });
      } catch (e) {
        if (e instanceof Error && e.name === 'AbortError') return;
        await desktopShare(files, urls[0]);
      }
      return;
    }
    if (navigator.share) {
      try {
        await navigator.share({ files, title });
      } catch (e) {
        if (e instanceof Error && e.name === 'AbortError') return;
        await desktopShare(files, urls[0]);
      }
      return;
    }
    await desktopShare(files, urls[0]);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state]);

  const ctxValue = useMemo(() => ({ openLightbox }), [openLightbox]);

  const spreadClassName =
    state.open && state.ids.length > 2 ? 'lbspread many' : state.open && state.ids.length > 1 ? 'lbspread two' : 'lbspread';

  return (
    <LightboxContext.Provider value={ctxValue}>
      {children}
      <div className={`lb${state.open ? ' on' : ''}`} id="lb" ref={rootRef} onClick={onBackdropClick}>
        <button className="lbwa" id="lbwa" type="button" aria-label="Share with WhatsApp" onClick={shareLightbox}>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M17.5 14.4c-.3-.15-1.7-.85-2-.95-.26-.1-.45-.15-.64.15-.19.28-.73.94-.9 1.13-.16.19-.33.21-.61.07-.3-.15-1.24-.46-2.36-1.46-.87-.78-1.46-1.73-1.63-2.02-.17-.29-.02-.44.13-.59.13-.13.29-.33.44-.5.15-.17.19-.29.29-.48.1-.19.05-.36-.02-.5-.08-.15-.64-1.55-.88-2.12-.23-.56-.47-.48-.64-.49h-.55c-.19 0-.5.07-.76.36-.26.28-1 .98-1 2.38s1.02 2.76 1.17 2.95c.15.19 2.02 3.08 4.9 4.32.68.29 1.22.47 1.63.6.69.22 1.31.19 1.81.11.55-.08 1.7-.69 1.94-1.36.24-.67.24-1.24.17-1.36-.07-.12-.26-.19-.55-.34zM12.05 21.5h-.01a9.44 9.44 0 0 1-4.8-1.32l-.34-.2-3.57.93.96-3.48-.22-.36a9.42 9.42 0 0 1-1.44-5.02c0-5.2 4.24-9.44 9.46-9.44 2.53 0 4.9.99 6.68 2.78a9.37 9.37 0 0 1 2.77 6.68c0 5.2-4.24 9.44-9.46 9.44zm8.05-17.49A11.36 11.36 0 0 0 12.05.5C5.8.5.72 5.58.72 11.82c0 2.08.55 4.11 1.58 5.9L.62 23.5l5.92-1.55a11.33 11.33 0 0 0 5.5 1.4h.01c6.24 0 11.32-5.08 11.32-11.32 0-3.03-1.18-5.87-3.32-8.02z" />
          </svg>
          Share with WhatsApp
        </button>
        <button className="lbx" id="lbx" aria-label="Exit" onClick={close}>
          &#10005; Exit
        </button>
        <div className={spreadClassName} id="lbspread" ref={spreadRef}>
          {state.open &&
            state.ids.map((id, i) =>
              IMG_TYPED[id] ? <img key={`${id}-${i}`} src={IMG_TYPED[id]} alt="" /> : null,
            )}
        </div>
        <div className={`lbtoast${toast.show ? ' show' : ''}`} id="lbtoast" role="status">
          {toast.msg}
        </div>
      </div>
    </LightboxContext.Provider>
  );
}
