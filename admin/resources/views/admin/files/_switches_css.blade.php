        .switch { flex-shrink: 0; width: 46px; height: 26px; border: 0; padding: 2px; border-radius: 999px; background: #d6d8e1; cursor: pointer; transition: background .18s; }
        .switch:focus-visible { outline: 0; box-shadow: 0 0 0 3px rgba(54,153,255,.3); }
        .switch[aria-checked="true"] { background: #1bc5bd; }
        .switch .knob { display: block; width: 22px; height: 22px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .18s ease; }
        .switch[aria-checked="true"] .knob { transform: translateX(20px); }
        #swList { display: flex; flex-direction: column; gap: 8px; }
        .sw-row { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 10px 16px; min-height: 52px; border: 1px solid var(--line); border-radius: 10px; background: #fff; transition: border-color .15s, box-shadow .15s, background .15s; }
        #swList .sw-row:hover { border-color: #dfe3ee; box-shadow: 0 4px 18px rgba(82,63,105,.06); }
        #swList .sw-row:first-child { background: #fafbfd; }
        .sw-row.is-off .sw-name { color: #7e8299; }
        .sw-open { flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 8px; border: 0; background: transparent; text-align: left; padding: 4px 8px 4px 0; cursor: pointer; font: inherit; }
        .sw-open:hover .sw-name { color: var(--blue); }
        .sw-open:hover .sw-chev { color: var(--blue); transform: translateX(2px); }
        .sw-name { display: block; font-weight: 600; color: #181c32; font-size: 14px; }
        .sw-sub { display: block; color: #7e8299; font-size: 12px; margin-top: 3px; }
        .sw-state { display: inline-block; padding: 1px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; }
        .sw-state.on { background: #c9f7f5; color: #0b8a83; }
        .sw-state.off { background: #f3f6f9; color: #7e8299; }
        .sw-chev { color: #b5b5c3; font-size: 20px; transition: transform .15s, color .15s; }
        .sw-view:not(#swList) { display: flex; flex-direction: column; gap: 10px; }
        .sw-view[hidden] { display: none !important; }
        .sw-nav { display: flex; align-items: center; gap: 12px; padding-bottom: 12px; margin-bottom: 2px; border-bottom: 1px solid var(--line); }
        .sw-nav h3 { margin: 0; font-size: 16px; font-weight: 600; color: #181c32; }
        .sw-nav .btn { height: 32px; padding: 0 14px; font-size: 12.5px; }
        .sw-who { margin-top: 4px; padding: 16px; border: 1px solid var(--line); border-radius: 10px; background: #fafbfd; }
        .sw-all { background: #f3f6f9; border-color: #f3f6f9; }
        #swWeekList { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 8px; max-height: 60vh; overflow: auto; padding: 2px; }
        #swWeekList .sw-row { min-height: 46px; padding: 8px 14px; }
        #swStatus:not(:empty) { background: #f3f6f9; color: #5e6278; padding: 3px 10px; border-radius: 6px; }
