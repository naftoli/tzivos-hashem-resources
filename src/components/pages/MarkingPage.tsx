import { useState } from 'react';
import { LiveFrame } from '@/components/LiveFrame';
import { Crumb } from '@/components/Crumb';

const TOOLS = [
  {
    src: '/new/missions/print',
    name: 'Print',
    desc: 'Mission sheets, ready to hand out.',
    color: 'var(--navy)',
    path: 'M6 9V3h12v6 M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2 M6 14h12v7H6z',
  },
  {
    src: '/new/missions/mark',
    name: 'Mark',
    desc: 'The grid where the platoon gets marked.',
    color: 'var(--blue)',
    path: 'M9 11l3 3L22 4 M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
  },
  {
    src: '/new/missions/personalize',
    name: 'Personalize',
    desc: 'Choose which missions a soldier gets.',
    color: 'var(--purple)',
    path: 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3 M1 14h6M9 8h6M17 16h6',
  },
  {
    src: '/new/missions/duch',
    name: 'Duch',
    desc: 'Prepare and submit a Duch.',
    color: 'var(--teal)',
    path: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z M14 2v6h6 M8 13h8M8 17h5',
  },
  {
    src: '/new/missions/streaks',
    name: 'Streaks',
    desc: 'Set up a streak on a task for a soldier.',
    color: 'var(--green)',
    path: 'M13 2L3 14h8l-1 8 10-12h-8z',
  },
] as const;

// Base Commander's blue (#3e6dc4 and friends) restated in the palette this page
// uses: navy surfaces, --line borders, --blue for accents.
const BASE_COMMANDER_CSS = `
#mashpia-navbar,#sidebar{display:none!important}
html,body{height:auto!important;overflow:hidden!important}
#dashboard-body{height:auto!important;min-height:0!important}
#dashboard-content{padding:10px 14px!important}
.Label>.row:not(.Task),.Grid thead th,.modal-header{background:#14265C!important;border-bottom-color:#14265C!important}
.Grid tr.Grid-row:first-child td{border-color:#14265C!important}
.Grid th,.Grid td{border-color:#D3DAE8!important}
.th-callout.alert-primary{background-color:#EDF0F6!important;border-color:#D3DAE8!important}
.th-callout.alert-primary i,.th-callout.alert-primary h4{color:#14265C!important}
.btn-primary{background-color:#14265C!important;border-color:#14265C!important}
.btn-primary:hover,.btn-primary:focus,.btn-primary:active{background-color:#1B4FD8!important;border-color:#1B4FD8!important}
.form-control:focus,.mark-cell.waiting>.form-control{border-color:#1B4FD8!important}
.mark-cell-waiting,.mark-cell-spinner{color:#1B4FD8!important}
.ReactTable .rt-th.rt-resizable-header:not(.-cursor-pointer){color:#14265C!important}
.ReactTable .selected-row{background-color:#EDF0F6!important}
.nav-tabs{border-color:#14265C!important}
.nav-tabs a.active.nav-link{background-color:#14265C!important;border-color:#14265C!important;color:#fff!important}
.nav-tabs .nav-link:not(.disabled):not(.active):hover{color:#1B4FD8!important}
.tab-content{border-color:#14265C!important}
p.title{border-bottom-color:#14265C!important}
a{color:#1B4FD8}
`;

export function MarkingPage() {
  // A teacher who opens this tab came to mark, so default straight to Mark.
  const [active, setActive] = useState(TOOLS[1]);

  return (
    <section className="page on" data-page="marking">
      <Crumb trail={['Mission Marking']} />
      <div className="sec">
        <div className="wrap">
          <div className="sec-h">
            <div className="eyebrow">Browse</div>
            <h2>Mission Marking</h2>
            <p>
              One branch among eight — but the one that closes the loop. A child reads a feature,
              does the mission, gets marked, and rises.
            </p>
          </div>
          <div className="campgrid" data-tools>
            {TOOLS.map((tool) => (
              <button
                key={tool.src}
                className="campcard"
                aria-pressed={active.src === tool.src}
                style={{ ['--c' as string]: tool.color }}
                onClick={() => setActive(tool)}
              >
                <span className="cc-emb">
                  <svg
                    className="emb"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    {tool.path.split(' M').map((seg, i) => (
                      <path key={i} d={i === 0 ? seg : `M${seg}`} />
                    ))}
                  </svg>
                </span>
                <span className="cc-body">
                  <span className="cc-name">{tool.name}</span>
                  <span className="cc-desc">{tool.desc}</span>
                </span>
              </button>
            ))}
          </div>
        </div>
      </div>
      <LiveFrame
        src={active.src}
        title={active.name}
        label={`Live · Base Commander · ${active.name}`}
        openHref={active.src}
        injectCss={BASE_COMMANDER_CSS}
      />
    </section>
  );
}
