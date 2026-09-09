import { useEffect, useLayoutEffect, useRef, useState, type MouseEvent } from 'react';
import { createPortal } from 'react-dom';
import { useNavigate } from 'react-router-dom';
import { altCrumbBranch, hasPage, pageBranch } from '@/data/pageMeta';
import { loadPageHtml, peekPageHtml } from '@/data/pageBodies';
import { applyWeekState } from '@/lib/applyWeekState';
import { hydrateImages } from '@/lib/hydrateImages';
import { dispatchContentClick } from '@/lib/dispatchContentClick';
import { useScheduleModal } from '@/components/ScheduleModal';
import { useLightbox } from '@/components/Lightbox';
import { navigateBack, setCtxFrom, wantedBranch } from '@/lib/navState';
import { PromotionsTable } from '@/components/pages/PromotionsTable';
import { ChitasFeed } from '@/components/pages/ChitasFeed';

interface ContentPageProps {
  pageId: string;
}

function swapLiveRegion(container: HTMLElement, selectors: string[]): HTMLElement | null {
  const nodes = selectors
    .map((sel) => container.querySelector(sel))
    .filter((n): n is Element => Boolean(n));
  if (!nodes.length || !nodes[0].parentNode) return null;
  const portalNode = document.createElement('div');
  portalNode.className = 'live-region';
  nodes[0].parentNode.insertBefore(portalNode, nodes[0]);
  nodes.forEach((n) => n.remove());
  return portalNode;
}

function applyAltCrumb(container: HTMLElement, pageId: string, fallbackBranch: string): void {
  const altBranch = altCrumbBranch[pageId];
  if (!altBranch) return;
  if (wantedBranch(fallbackBranch) !== altBranch) return;
  const altTemplate = container.querySelector<HTMLTemplateElement>('template.altcrumb');
  const bar = container.querySelector('.crumb .wrap');
  if (!altTemplate || !bar) return;
  const pdfBtn = bar.querySelector('.pdfbtn');
  const pdfHTML = pdfBtn ? pdfBtn.outerHTML : '';
  bar.innerHTML = altTemplate.innerHTML + pdfHTML;
}

/**
 * Generic renderer for the extracted content pages (date/campaign/rally/
 * promotions/chitas/home). Hydrates `data-img`/`data-hw` maps, then delegates
 * clicks to `dispatchContentClick`.
 */
export function ContentPage({ pageId }: ContentPageProps) {
  const navigate = useNavigate();
  const { openSched, openOlder } = useScheduleModal();
  const { openLightbox } = useLightbox();
  const branch = pageBranch[pageId];
  const containerRef = useRef<HTMLDivElement>(null);
  // null only while a lazy branch chunk is still fetching; the fast path below
  // seeds it synchronously for static/already-loaded branches so those never
  // flash a loading state.
  const [html, setHtml] = useState<string | null>(() => peekPageHtml(pageId));
  const [promoPortalNode, setPromoPortalNode] = useState<HTMLElement | null>(null);
  const [chitasPortalNode, setChitasPortalNode] = useState<HTMLElement | null>(null);

  // Fetch the page body when the route changes. If its branch is already loaded
  // (static branch, or a lazy chunk visited earlier this session), take the
  // synchronous value and skip the loading state entirely; only a genuine
  // uncached `date`/`campaign` chunk fetch drops to `null` and shows it.
  useEffect(() => {
    const ready = peekPageHtml(pageId);
    if (ready != null) {
      setHtml(ready);
      return;
    }
    let alive = true;
    setHtml(null);
    loadPageHtml(pageId).then((h) => {
      if (alive) setHtml(h ?? '');
    });
    return () => {
      alive = false;
    };
  }, [pageId]);

  useLayoutEffect(() => {
    const container = containerRef.current;
    if (!container || html == null) return;

    // Assign HTML here, not via dangerouslySetInnerHTML. A React-managed
    // innerHTML host wipes the live-region node on the next render, so the
    // AJAX result painted into a detached portal while the page kept showing
    // the static "Loading…" placeholders.
    container.innerHTML = html;
    hydrateImages(container);
    applyWeekState(container);
    applyAltCrumb(container, pageId, branch ?? pageId);

    const promo = pageId === 'promotions' ? swapLiveRegion(container, ['#promoMonth', 'table.tbl']) : null;
    const chitas = pageId === 'chitas' ? swapLiveRegion(container, ['#chFeed']) : null;
    setPromoPortalNode(promo);
    setChitasPortalNode(chitas);

    const h = container.querySelector('h1,h2');
    document.title = `Tzivos Hashem · ${h ? (h.textContent ?? '').trim() : 'Resources'}`;

    return () => {
      setPromoPortalNode(null);
      setChitasPortalNode(null);
      container.innerHTML = '';
    };
  }, [html, pageId, branch]);

  function handleClick(event: MouseEvent<HTMLDivElement>) {
    if (!containerRef.current) return;
    dispatchContentClick(event, containerRef.current, {
      openSched,
      openOlder,
      openLightbox,
      navigateBack: (fallback) => navigateBack(navigate, fallback),
      go: (id) => {
        setCtxFrom(branch ?? pageId);
        navigate(`/${id}`);
      },
    });
  }

  if (!hasPage(pageId)) {
    return (
      <section className="page on" data-page={pageId}>
        <div className="sec">
          <div className="wrap">
            <div className="sec-h">
              <h2>Page not found</h2>
            </div>
            <button className="btn btn-p" onClick={() => navigate('/home')}>
              Back to Resources
            </button>
          </div>
        </div>
      </section>
    );
  }

  return (
    <>
      {/* Sibling, never a child of the innerHTML-managed container: shown only
          while a lazy branch chunk is still fetching (see the load effect). */}
      {html == null && (
        <section className="page on">
          <div className="sec">
            <div className="wrap">
              <p className="loading-note" role="status" aria-live="polite">
                Loading…
              </p>
            </div>
          </div>
        </section>
      )}
      <div ref={containerRef} onClick={handleClick} />
      {promoPortalNode && createPortal(<PromotionsTable />, promoPortalNode)}
      {chitasPortalNode && createPortal(<ChitasFeed />, chitasPortalNode)}
    </>
  );
}
