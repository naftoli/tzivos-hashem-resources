import { useEffect } from 'react';

/**
 * Shared Escape-to-close listener for a full-screen overlay. Both the schedule
 * modal (ScheduleModal.tsx) and the image lightbox (Lightbox.tsx) use this
 * instead of each hand-rolling its own `document.addEventListener('keydown', ...)`
 * — mirrors the legacy monolith, where a single shared `keydown` listener closed
 * whichever one of these overlays happened to be open.
 */
export function useEscapeToClose(active: boolean, onClose: () => void): void {
  useEffect(() => {
    if (!active) return;
    function onKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose();
    }
    document.addEventListener('keydown', onKeyDown);
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [active, onClose]);
}
