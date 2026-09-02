import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from '@/App';
import { AuthGate } from '@/components/AuthGate';
import '@/index.css';

// Legacy links/bookmarks use bare `#pageId` hashes (e.g. `#dYear`) with no leading
// slash; HashRouter expects `#/pageId`. Normalize once at boot so old-style hashes
// still resolve to the right route.
if (location.hash && !location.hash.startsWith('#/')) {
  location.hash = `/${location.hash.slice(1)}`;
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AuthGate>
      <App />
    </AuthGate>
  </StrictMode>,
);
