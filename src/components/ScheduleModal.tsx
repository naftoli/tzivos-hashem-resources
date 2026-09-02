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
import { buildOlderRows, buildSchedRows, type OlderRow, type SchedRow } from '@/lib/scheduleModal';
import { useEscapeToClose } from '@/lib/useEscapeToClose';

type ModalState =
  | { open: false }
  | { open: true; kind: 'sched'; title: string; embed?: string; rows: SchedRow[] }
  | { open: true; kind: 'older'; title: string; rows: OlderRow[] };

interface ScheduleModalContextValue {
  /** Opens the schedule modal for a `.schbtn[data-sch]` id ('weekly' | 'mivtza' | a CALCATN category). */
  openSched: (id: string) => void;
  /** Opens the same modal in "older-year PDFs" mode for a `[data-older]` year key. */
  openOlder: (key: string) => void;
}

const ScheduleModalContext = createContext<ScheduleModalContextValue | null>(null);

/** Port of legacy `openSched`/`closeSched` + the `[data-older]` branch's modal, as real React state. */
export function useScheduleModal(): ScheduleModalContextValue {
  const ctx = useContext(ScheduleModalContext);
  if (!ctx) throw new Error('useScheduleModal must be used within a ScheduleModalProvider');
  return ctx;
}

export function ScheduleModalProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<ModalState>({ open: false });
  const listRef = useRef<HTMLDivElement>(null);

  const openSched = useCallback((id: string) => {
    const { title, embed, rows } = buildSchedRows(id);
    setState({ open: true, kind: 'sched', title, embed, rows });
  }, []);

  const openOlder = useCallback((key: string) => {
    const rows = buildOlderRows(key);
    setState({ open: true, kind: 'older', title: 'Older-Year PDFs', rows });
  }, []);

  const close = useCallback(() => setState({ open: false }), []);

  // schL.scrollTop=0 on every open, same as the legacy handlers.
  useEffect(() => {
    if (state.open && listRef.current) listRef.current.scrollTop = 0;
  }, [state]);

  useEscapeToClose(state.open, close);

  function onBackdropClick(e: MouseEvent<HTMLDivElement>) {
    if (e.target === e.currentTarget) close();
  }

  const ctxValue = useMemo(() => ({ openSched, openOlder }), [openSched, openOlder]);

  return (
    <ScheduleModalContext.Provider value={ctxValue}>
      {children}
      <div
        className={`schmodal${state.open ? ' on' : ''}`}
        id="schmodal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="schtitle"
        onClick={onBackdropClick}
      >
        <div className="schbox">
          <button className="schx" id="schx" aria-label="Close" onClick={close}>
            &#10005;
          </button>
          <h3 id="schtitle">
            {state.open && state.kind === 'sched' && state.embed && (
              <img className="schemb" src={state.embed} alt="" />
            )}
            {state.open ? state.title : ''}
          </h3>
          <div className="schlist" id="schlist" ref={listRef}>
            {state.open && state.kind === 'sched' && state.rows.length === 0 && (
              <p className="schempty">Nothing scheduled.</p>
            )}
            {state.open &&
              state.kind === 'sched' &&
              state.rows.map((r, i) => (
                <div className="schrow" key={i}>
                  <div className="schd">
                    <b>{r.a}</b>
                    {r.b ? <span>{r.b}</span> : null}
                  </div>
                  <div className="schc">{r.c}</div>
                </div>
              ))}
            {state.open && state.kind === 'older' && state.rows.length === 0 && (
              <p className="schempty">None.</p>
            )}
            {state.open &&
              state.kind === 'older' &&
              state.rows.map((r, i) => (
                <div className="schrow" key={i}>
                  <a className="schc" href={r.href} target="_blank" rel="noopener noreferrer">
                    {r.label} &#8599;
                  </a>
                </div>
              ))}
          </div>
        </div>
      </div>
    </ScheduleModalContext.Provider>
  );
}
