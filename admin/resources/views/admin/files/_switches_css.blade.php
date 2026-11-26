        .switch { flex-shrink: 0; width: 48px; height: 28px; border: 0; padding: 2px; border-radius: 999px; background: #d4cec3; cursor: pointer; }
        .switch[aria-checked="true"] { background: #1e6b54; }
        .switch .knob { display: block; width: 24px; height: 24px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .18s ease; }
        .switch[aria-checked="true"] .knob { transform: translateX(20px); }
        .sw-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid var(--line); min-height: 44px; }
        .sw-row:last-child { border-bottom: 0; }
        .sw-open { flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 8px; border: 0; background: transparent; text-align: left; padding: 6px 10px 6px 0; cursor: pointer; font: inherit; }
        .sw-open:hover .sw-name { color: var(--blue); }
        .sw-name { display: block; font-weight: 600; color: #181c32; font-size: 14px; }
        .sw-sub { display: block; color: #7e8299; font-size: 12px; margin-top: 2px; }
        .sw-chev { color: #b5b5c3; font-size: 18px; }
        .sw-nav { display: flex; align-items: center; gap: 10px; margin: 6px 0 8px; }
        .sw-nav h3 { margin: 0; font-size: 15px; }
        .sw-who { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
        .sw-all { background: #f3f6f9; border-radius: 8px; padding: 8px 10px; border-bottom: 0; margin-bottom: 4px; }
        #swWeekList { max-height: 60vh; overflow: auto; }
