import { useEffect, useRef, useState } from 'react';

interface LiveFrameProps {
  /** Iframe src. Passing null/undefined keeps the frame unmounted (e.g. marking before a tool is picked). */
  src: string | null | undefined;
  title: string;
  /** Shown in the live-status bar, e.g. "Live · Base Commander" or "Tzivos Hashem Calendar 5787". */
  label: string;
  /** "Open full screen" link target; hidden while empty. */
  openHref?: string;
  /** Extra CSS text injected into the iframe's own <head> once it loads (same-origin only). */
  injectCss?: string;
  className?: string;
}

/**
 * Ports mountLive()/the calendar-mount script from the legacy page: a same-origin
 * iframe that grows to fit its content (so it never scrolls inside its own box) and
 * shows a "still loading, open in a new tab" fallback after 8s.
 */
export function LiveFrame({ src, title, label, openHref, injectCss, className }: LiveFrameProps) {
  const frameRef = useRef<HTMLIFrameElement>(null);
  const [loaded, setLoaded] = useState(false);
  const [slow, setSlow] = useState(false);
  const [height, setHeight] = useState(0);

  useEffect(() => {
    setLoaded(false);
    setSlow(false);
    setHeight(0);
    if (!src) return;

    const timer = setTimeout(() => setSlow(true), 8000);
    return () => clearTimeout(timer);
  }, [src]);

  useEffect(() => {
    if (!loaded) return;
    const frame = frameRef.current;
    if (!frame) return;

    function fit() {
      try {
        const doc = frame!.contentDocument;
        if (!doc?.body) return;
        const root = doc.getElementById('root');
        const h = Math.max(
          doc.body.scrollHeight,
          doc.documentElement?.scrollHeight ?? 0,
          root?.scrollHeight ?? 0,
        );
        if (h > 80) setHeight(h);
      } catch {
        // cross-origin (shouldn't happen here — both embeds are same-origin) — leave height alone
      }
    }

    let ro: ResizeObserver | undefined;
    try {
      const doc = frame.contentDocument;
      if (doc?.head && injectCss) {
        const style = doc.createElement('style');
        style.textContent = injectCss;
        doc.head.appendChild(style);
      }
      fit();
      if (window.ResizeObserver && doc) {
        ro = new ResizeObserver(fit);
        ro.observe(doc.documentElement);
        ro.observe(doc.body);
      }
    } catch {
      // ignore
    }
    const interval = setInterval(fit, 1000);
    const win = frame.contentWindow ?? window;
    win.addEventListener('resize', fit);
    return () => {
      clearInterval(interval);
      ro?.disconnect();
      win.removeEventListener('resize', fit);
    };
  }, [loaded, injectCss]);

  return (
    <div className={`wrap ${className ?? ''}`}>
      <div className="liveframe">
        <div className="lf-bar">
          <span className="lf-dot" />
          <span className="lf-lab">{label}</span>
          {openHref && (
            <a className="lf-open" href={openHref} target="_blank" rel="noopener">
              Open full screen &#8599;
            </a>
          )}
        </div>
        <div className="lf-body">
          {!loaded && (
            <div className="lf-msg">
              {slow ? (
                <>
                  Still loading&hellip; if nothing appears,{' '}
                  <a href={openHref ?? src ?? '#'} target="_blank" rel="noopener">
                    open it in a new tab
                  </a>
                  .
                </>
              ) : src ? (
                'Loading…'
              ) : (
                'Pick a tool above to open it here.'
              )}
            </div>
          )}
          {src && (
            <iframe
              ref={frameRef}
              className={`lf-frame${loaded ? ' on' : ''}`}
              title={title}
              src={src}
              style={{ height: loaded ? height : 0 }}
              onLoad={() => setLoaded(true)}
            />
          )}
        </div>
      </div>
    </div>
  );
}
