import { useLayoutEffect, useMemo, useRef, useState, type MouseEvent } from 'react';
import { createPortal } from 'react-dom';
import { useNavigate } from 'react-router-dom';
import { pageRegistry, altCrumbBranch } from '@/data/pageRegistry';
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
  const entry = pageRegistry[pageId];
  const html = useMemo(() => entry?.html ?? '', [entry]);
  const containerRef = useRef<HTMLDivElement>(null);
  const [promoPortalNode, setPromoPortalNode] = useState<HTMLElement | null>(null);
  const [chitasPortalNode, setChitasPortalNode] = useState<HTMLElement | null>(null);

  useLayoutEffect(() => {
    const container = containerRef.current;
    setPromoPortalNode(null);
    setChitasPortalNode(null);
    if (!container) return;

    hydrateImages(container);
    applyWeekState(container);
    applyAltCrumb(container, pageId, entry?.branch ?? pageId);

    if (pageId === 'promotions') {
      setPromoPortalNode(swapLiveRegion(container, ['#promoMonth', 'table.tbl']));
    }
    if (pageId === 'chitas') {
      setChitasPortalNode(swapLiveRegion(container, ['#chFeed']));
    }

    const h = container.querySelector('h1,h2');
    document.title = `Tzivos Hashem · ${h ? (h.textContent ?? '').trim() : 'Resources'}`;
  }, [html, pageId, entry]);

  function handleClick(event: MouseEvent<HTMLDivElement>) {
    if (!containerRef.current) return;
    dispatchContentClick(event, containerRef.current, {
      openSched,
      openOlder,
      openLightbox,
      navigateBack: (fallback) => navigateBack(navigate, fallback),
      go: (id) => {
        setCtxFrom(entry?.branch ?? pageId);
        navigate(`/${id}`);
      },
    });
  }

  if (!entry) {
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
      {/* eslint-disable-next-line react/no-danger */}
      <div ref={containerRef} onClick={handleClick} dangerouslySetInnerHTML={{ __html: html }} />
      {promoPortalNode && createPortal(<PromotionsTable />, promoPortalNode)}
      {chitasPortalNode && createPortal(<ChitasFeed />, chitasPortalNode)}
    </>
  );
}
