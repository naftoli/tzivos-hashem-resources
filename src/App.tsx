import { useEffect, type ReactNode } from 'react';
import { HashRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { Header } from '@/components/Header';
import { ScheduleModalProvider } from '@/components/ScheduleModal';
import { LightboxProvider } from '@/components/Lightbox';
import { ContentPage } from '@/components/pages/ContentPage';
import { CalendarPage } from '@/components/pages/CalendarPage';
import { MarkingPage } from '@/components/pages/MarkingPage';
import { pageRegistry, altCrumbBranch } from '@/data/pageRegistry';
import { wantedBranch } from '@/lib/navState';
import branchOf from '@/data/static/branchOf.json';
import type { BranchId } from '@/types';

const BRANCH_OF = branchOf as Record<string, string>;

/**
 * Port of legacy `show()`'s branch-bar `aria-current` computation:
 * `var b=(t.dataset.curbranch)||branchOf[pid]||pid;` — `t.dataset.curbranch` is
 * only ever set (to `want`) when the current page's altcrumb actually swapped
 * in, so this re-derives that same "did it swap" decision from `altCrumbBranch`
 * (see pageRegistry.ts) instead of reading it back off the DOM.
 */
function resolveBranch(pageId: string): BranchId {
  if (pageId === 'marking' || pageId === 'calendar') return pageId;
  const fallback = (BRANCH_OF[pageId] || pageRegistry[pageId]?.branch || 'home') as BranchId;
  const altBranch = altCrumbBranch[pageId];
  if (altBranch) {
    const want = wantedBranch(fallback);
    if (want === altBranch) return want as BranchId;
  }
  return fallback;
}

/** Resolves the current `:pageId` param to the right page component + chrome. */
function AppShell() {
  const { pageId = 'home' } = useParams();

  // Port of legacy `show()`'s `window.scrollTo({top:0,behavior:'instant'})` —
  // runs on every route change, for every page type (content pages and the
  // live-tool embeds alike), same as the original.
  useEffect(() => {
    window.scrollTo(0, 0);
  }, [pageId]);

  let content: ReactNode;
  if (pageId === 'marking') {
    content = <MarkingPage />;
  } else if (pageId === 'calendar') {
    content = <CalendarPage />;
  } else if (pageRegistry[pageId]) {
    content = <ContentPage pageId={pageId} />;
  } else {
    return <Navigate to="/home" replace />;
  }

  return (
    <>
      <Header activeBranch={resolveBranch(pageId)} />
      <main id="app">{content}</main>
    </>
  );
}

export function App() {
  return (
    <HashRouter>
      <ScheduleModalProvider>
        <LightboxProvider>
          <Routes>
            <Route path="/" element={<Navigate to="/home" replace />} />
            <Route path="/:pageId" element={<AppShell />} />
            <Route path="*" element={<Navigate to="/home" replace />} />
          </Routes>
        </LightboxProvider>
      </ScheduleModalProvider>
    </HashRouter>
  );
}
