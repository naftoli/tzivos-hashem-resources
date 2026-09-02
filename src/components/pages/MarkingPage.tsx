import { useState } from 'react';
import { LiveFrame } from '@/components/LiveFrame';
import { Crumb } from '@/components/Crumb';
import markingEmbedCss from '@/assets/marking-embed.css?raw';

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

export function MarkingPage() {
  // A teacher who opens this tab came to mark, so default straight to Mark.
  const [active, setActive] = useState<(typeof TOOLS)[number]>(TOOLS[1]);

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
        injectCss={markingEmbedCss}
      />
    </section>
  );
}
