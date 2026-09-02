import { useNavigate } from 'react-router-dom';

interface CrumbProps {
  /** Trail segments after "Resources", e.g. ['Calendar'] or ['Campaign', 'Hiskashrus']. */
  trail: string[];
}

/**
 * Ports the `.crumb` breadcrumb bar markup that the extracted page fragments already
 * carry inline (see e.g. campaign.json's `<div class="crumb">...</div>`), for the
 * hand-written pages (CalendarPage, MarkingPage) that don't come from an extracted
 * HTML fragment and so need it built from scratch.
 */
export function Crumb({ trail }: CrumbProps) {
  const navigate = useNavigate();

  return (
    <div className="crumb">
      <div className="wrap">
        <button className="back" onClick={() => navigate('/home')}>
          &#8592; Back<span className="bn"> to Resources</span>
        </button>
        <span className="csep" />
        <button onClick={() => navigate('/home')}>Resources</button>
        {trail.map((label, i) => (
          <span key={i} style={{ display: 'contents' }}>
            <span className="sep">&#8250;</span>
            <span>{label}</span>
          </span>
        ))}
      </div>
    </div>
  );
}
