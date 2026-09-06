import { LiveFrame } from '@/components/LiveFrame';
import { Crumb } from '@/components/Crumb';
import calendarEmbedCss from '@/assets/calendar-embed.css?raw';

const CAL_SRC = '/resources/calendar/dist/';

interface CalendarPageProps {
  initialView?: string;
}

export function CalendarPage({ initialView }: CalendarPageProps) {
  const src = initialView ? `${CAL_SRC}?view=${initialView}` : CAL_SRC;
  return (
    <section className="page on" data-page="calendar">
      <Crumb trail={['Calendar']} />
      <div className="sec">
        <div className="wrap">
          <div className="sec-h">
            <div className="eyebrow">Browse</div>
            <h2>Calendar</h2>
            <p>The whole 5787 year — every date and category, filtered and exported the way you need it.</p>
          </div>
        </div>
      </div>
      <LiveFrame
        src={src}
        title="Tzivos Hashem Calendar 5787"
        label="Tzivos Hashem Calendar 5787"
        openHref={src}
        injectCss={calendarEmbedCss}
        fitMode="calendar"
      />
    </section>
  );
}
