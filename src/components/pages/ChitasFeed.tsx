import { useEffect, useState } from 'react';

const ENDPOINT = '/mobile/news/ajax/getPosts.php';

interface ChitasPost {
  title?: string;
  content?: string;
  posted?: string;
}

function fmtDate(s: string | undefined): string {
  const m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return '';
  const d = new Date(+m[1], +m[2] - 1, +m[3]);
  return d.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

/**
 * Live replacement for `#chFeed` on the Daily Chitas page — same fetch as the
 * monolith's news script, rendered as React instead of innerHTML patching.
 */
export function ChitasFeed() {
  const [posts, setPosts] = useState<ChitasPost[] | null>(null);
  const [error, setError] = useState(false);
  const [empty, setEmpty] = useState(false);
  const [open, setOpen] = useState(false);
  const [overflows, setOverflows] = useState(false);
  const [bodyEl, setBodyEl] = useState<HTMLDivElement | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'page=1&limit=1',
    })
      .then((r) => {
        if (!r.ok) throw new Error(String(r.status));
        return r.text();
      })
      .then((txt) => {
        if (cancelled) return;
        const parsed: { data?: ChitasPost[] } = JSON.parse(txt) || {};
        const list = (parsed.data || []).slice(0, 1);
        if (!list.length) {
          setEmpty(true);
          setPosts([]);
          return;
        }
        setPosts(list);
      })
      .catch(() => {
        if (!cancelled) setError(true);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!bodyEl) return;
    const doesOverflow = bodyEl.scrollHeight > bodyEl.clientHeight + 4;
    setOverflows(doesOverflow);
    if (doesOverflow) setOpen(true);
  }, [bodyEl, posts]);

  if (error) {
    return (
      <div className="chfeed" id="chFeed">
        <div className="note" id="chFeedMsg">
          <b>Couldn’t load</b>The feed didn’t answer —{' '}
          <a href="/mobile/news/" target="_blank" rel="noopener">
            open the news page
          </a>{' '}
          instead.
        </div>
      </div>
    );
  }

  if (empty) {
    return (
      <div className="chfeed" id="chFeed">
        <div className="note" id="chFeedMsg">
          <b>Nothing posted yet</b>The feed has no posts right now.
        </div>
      </div>
    );
  }

  if (!posts) {
    return (
      <div className="chfeed" id="chFeed">
        <div className="note" id="chFeedMsg">
          <b>Loading</b>Pulling today’s Chitas from the news feed…
        </div>
      </div>
    );
  }

  const post = posts[0];
  const dt = fmtDate(post.posted);
  const body = String(post.content || '').replace(/http:\/\//g, 'https://');

  return (
    <div className="chfeed" id="chFeed">
      <div className="chfeed-list" id="chFeedList">
        <article className="chpost">
          <h3>{post.title}</h3>
          {dt ? <span className="chdate">{dt}</span> : null}
          <div
            ref={setBodyEl}
            className={`chbody${open ? ' open' : ''}`}
            dangerouslySetInnerHTML={{ __html: body }}
          />
          {overflows && (
            <button type="button" className="chmore" onClick={() => setOpen((v) => !v)}>
              {open ? 'Show less' : 'Show more'}
            </button>
          )}
        </article>
      </div>
    </div>
  );
}
