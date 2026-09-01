import { LiveFrame } from '@/components/LiveFrame';
import { Crumb } from '@/components/Crumb';

export function CalendarPage() {
  return (
    <section className="page on" data-page="calendar">
      <Crumb trail={['Calendar']} />
      <div className="sec">
        <div className="wrap">
          <div className="sec-h">
            <div className="eyebrow">Browse</div>
            <h2>Calendar</h2>
            <p>The Tzivos Hashem calendar — missions, dates, and campaigns at a glance.</p>
          </div>
        </div>
      </div>
      <LiveFrame
        src="calendar/dist"
        title="Calendar"
        label="Live · Tzivos Hashem Calendar 5787"
        openHref="calendar/dist"
      />
    </section>
  );
}
