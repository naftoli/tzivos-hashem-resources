        /* ---- switches ---- */
        .switch { flex-shrink: 0; width: 44px; height: 24px; border: 0; padding: 2px; border-radius: 999px; background: #d5d9e4; cursor: pointer; transition: background .2s; }
        .switch:focus-visible, .wk-tile:focus-visible, .sw-open:focus-visible { outline: 0; box-shadow: 0 0 0 3px rgba(99,102,241,.3); }
        .switch[aria-checked="true"] { background: var(--fx-on); }
        .switch .knob { display: block; width: 20px; height: 20px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(15,23,42,.25); transition: transform .2s ease; }
        .switch[aria-checked="true"] .knob { transform: translateX(20px); }
        .switch.switch-lg { width: 54px; height: 30px; }
        .switch.switch-lg .knob { width: 26px; height: 26px; }
        .switch.switch-lg[aria-checked="true"] .knob { transform: translateX(24px); }

        .sw-view[hidden] { display: none !important; }
        .sw-status:empty { display: none; }
        .sw-status { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px; background: #eef0f6; color: #5e6278; }
        .sw-status[data-s="saved"] { background: var(--fx-on-soft); color: var(--fx-on-ink); }
        .sw-status[data-s="error"] { background: #ffe2e5; color: #d1213d; }

        /* status pill with a dot */
        .sw-pill { display: inline-flex; align-items: center; gap: 6px; height: 22px; padding: 0 10px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: #eef0f6; color: #7e8299; white-space: nowrap; }
        .sw-pill::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #b5b5c3; }
        .sw-pill.on { background: var(--fx-on-soft); color: var(--fx-on-ink); }
        .sw-pill.on::before { background: var(--fx-on); box-shadow: 0 0 0 3px rgba(20,184,166,.18); }

        /* week coverage bar */
        .sw-cover { display: flex; align-items: center; gap: 10px; min-width: 0; }
        .sw-bar { flex: 1; height: 6px; border-radius: 999px; background: #eceef5; overflow: hidden; min-width: 60px; }
        .sw-bar > span { display: block; height: 100%; width: 0; border-radius: 999px; background: linear-gradient(90deg, var(--fx-accent), var(--fx-on)); transition: width .3s ease; }
        .sw-sub { color: #7e8299; font-size: 12px; font-weight: 500; white-space: nowrap; }
        .is-off .sw-bar > span { background: #c8ccd8; }

        .sw-name { font-weight: 600; color: var(--fx-ink); font-size: 15px; }
        .sw-open { display: flex; align-items: center; gap: 10px; border: 0; background: transparent; text-align: left; padding: 0; cursor: pointer; font: inherit; border-radius: 6px; }
        .sw-open:hover .sw-name { color: var(--fx-accent); }
        .sw-chev { color: #b5b5c3; font-size: 20px; line-height: 1; transition: transform .15s, color .15s; }
        .sw-open:hover .sw-chev { color: var(--fx-accent); transform: translateX(3px); }
        .sw-ico { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 10px; background: var(--fx-accent-soft); color: var(--fx-accent); flex-shrink: 0; }
        .sw-ico svg, .sw-full-icon svg { width: 20px; height: 20px; }

        /* Full: featured banner */
        .sw-full { position: relative; display: flex; align-items: center; gap: 20px; padding: 22px 24px; border-radius: 14px; background: linear-gradient(135deg, #ffffff 0%, #f4f5ff 100%); border: 1px solid #e3e5f6; box-shadow: var(--fx-shadow); overflow: hidden; }
        .sw-full::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: linear-gradient(180deg, var(--fx-accent), var(--fx-on)); }
        .sw-full-icon { display: flex; align-items: center; justify-content: center; width: 54px; height: 54px; border-radius: 14px; background: var(--fx-navy); color: #fff; flex-shrink: 0; box-shadow: 0 6px 16px rgba(21,24,40,.25); }
        .sw-full-icon svg { width: 26px; height: 26px; }
        .sw-full-main { flex: 1; display: flex; flex-direction: column; gap: 10px; min-width: 0; }
        .sw-full .sw-name { font-size: 18px; }
        .sw-full .sw-cover { max-width: 420px; }
        .sw-full-side { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; }
        .sw-link { border: 0; background: transparent; color: var(--fx-accent); font: inherit; font-weight: 600; cursor: pointer; padding: 2px 0; }
        .sw-link:hover { text-decoration: underline; }

        /* features: card grid */
        .sw-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; margin-top: 18px; }
        .sw-card { position: relative; display: flex; flex-direction: column; gap: 12px; padding: 18px 18px 16px; border-radius: 14px; background: #fff; border: 1px solid #eceef5; box-shadow: var(--fx-shadow); transition: transform .15s, box-shadow .15s, border-color .15s; overflow: hidden; }
        .sw-card::before { content: ''; position: absolute; left: 0; right: 0; top: 0; height: 3px; background: linear-gradient(90deg, var(--fx-accent), var(--fx-on)); }
        .sw-card:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(21,24,40,.09); border-color: #e0e3f0; }
        .sw-card-top { display: flex; align-items: center; justify-content: space-between; }
        .sw-card .sw-open { justify-content: space-between; width: 100%; }
        .sw-card .sw-pill { align-self: flex-start; }
        .sw-card.is-off::before, .sw-full.is-off::before { background: #d5d9e4; }
        .sw-card.is-off .sw-ico { background: #f1f2f6; color: #a1a5b7; }
        .sw-card.is-off .sw-name, .sw-full.is-off .sw-name { color: #7e8299; }

        /* detail view and Choose weeks */
        .sw-detail { background: #fff; border-radius: 14px; border: 1px solid #eceef5; box-shadow: var(--fx-shadow); }
        .sw-bar-head { position: sticky; top: 70px; z-index: 5; display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 14px 20px; background: rgba(255,255,255,.96); backdrop-filter: blur(6px); border-bottom: 1px solid #eceef5; border-radius: 14px 14px 0 0; }
        .sw-bar-head h3 { margin: 0; font-size: 17px; font-weight: 600; color: var(--fx-ink); }
        .sw-bar-head .btn { height: 34px; padding: 0 14px; font-size: 12.5px; }
        .sw-head-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
        .sw-all { font-weight: 600; color: var(--fx-ink); background: #f4f5fa; padding: 6px 8px 6px 14px; border-radius: 999px; }
        .sw-split { display: grid; grid-template-columns: minmax(240px, 1fr) minmax(0, 2fr); gap: 18px; padding: 20px; }
        .sw-split.one { grid-template-columns: minmax(240px, 520px); }
        .sw-panel { display: flex; flex-direction: column; gap: 14px; align-items: flex-start; padding: 18px; border-radius: 12px; background: #f8f9fc; border: 1px solid #eef0f6; margin: 0; }
        .sw-panel .sw-cover { width: 100%; }
        .sw-panel-title { font-size: 11.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #7e8299; }
        .sw-who { align-items: stretch; }
        #swWeekList { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; padding: 20px; }
        .wk-tile { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 12px; border: 1.5px solid #e4e6ef; background: #fff; text-align: left; font: inherit; color: #5e6278; cursor: pointer; transition: border-color .15s, background .15s, box-shadow .15s, color .15s; }
        .wk-tile:hover { border-color: #c7caf5; box-shadow: 0 4px 14px rgba(21,24,40,.06); }
        .wk-tick { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 7px; border: 1.5px solid #d5d9e4; color: transparent; flex-shrink: 0; transition: background .15s, border-color .15s, color .15s; }
        .wk-tick svg { width: 15px; height: 15px; }
        .wk-name { font-weight: 500; line-height: 1.35; }
        .wk-tile[aria-checked="true"] { border-color: var(--fx-on); background: var(--fx-on-soft); color: var(--fx-ink); }
        .wk-tile[aria-checked="true"] .wk-tick { background: var(--fx-on); border-color: var(--fx-on); color: #fff; }
        @media (max-width: 900px) {
            .sw-split { grid-template-columns: 1fr; }
            .sw-full { flex-wrap: wrap; }
            .sw-full-side { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
        }
        .sw-panel > .btn { background: var(--fx-navy); border-radius: 8px; height: 36px; }
        .sw-panel > .btn:hover { background: #262b52; }

.sw-card-foot { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-top: 0; padding-top: 10px; border-top: 1px solid #f0f1f6; }
.sw-card-foot .fx-pill { margin-right: auto; }
