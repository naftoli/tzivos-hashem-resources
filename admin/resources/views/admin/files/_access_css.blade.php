        .acc { display: flex; flex-direction: column; gap: 12px; }
        .acc-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
        .acc-group { display: flex; flex-direction: column; gap: 10px; padding: 14px; border-radius: 12px; background: #f8f9fc; border: 1px solid #eef0f6; min-width: 0; }
        .acc-l { color: #7e8299; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
        .acc-opts { display: flex; flex-wrap: wrap; gap: 6px; }
        .acc label { margin: 0; }
        .acc label.chip { display: inline-flex; align-items: center; gap: 7px; height: 30px; padding: 0 12px 0 9px; border: 1px solid #e1e4ee; border-radius: 999px; background: #fff; color: #5e6278; font-weight: 500; font-size: 12.5px; white-space: nowrap; cursor: pointer; transition: background .15s, border-color .15s, color .15s, box-shadow .15s; }
        .acc label.chip:hover { border-color: #c7caf5; box-shadow: 0 2px 8px rgba(21,24,40,.05); }
        .acc label.chip input { appearance: none; -webkit-appearance: none; width: 15px; height: 15px; margin: 0; border-radius: 50%; border: 1.5px solid #c8ccd8; background: #fff; display: inline-block; flex-shrink: 0; cursor: pointer; transition: background .15s, border-color .15s; }
        .acc label.chip input:checked { border-color: #6366f1; background: #6366f1 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E") center / 10px no-repeat; }
        .acc label.chip:has(input:checked) { background: #eef0ff; border-color: #c7caf5; color: #3f3fb8; }
        .acc label.chip:has(input:disabled) { cursor: default; opacity: .7; }
        .acc label.chip input:disabled { cursor: default; }
        .acc label.chip input:focus-visible { box-shadow: 0 0 0 3px rgba(99,102,241,.3); outline: 0; }
        .acc-when { display: flex; flex-wrap: wrap; gap: 8px 14px; }
        .acc label.date { display: inline-flex; align-items: center; gap: 8px; color: #5e6278; font-weight: 500; font-size: 12.5px; white-space: nowrap; }
        .acc label.date span { min-width: 34px; }
        .acc input[type=datetime-local] { height: 34px; padding: 0 10px; border: 1px solid #e1e4ee; border-radius: 8px; font: inherit; font-size: 12.5px; color: #3f4254; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .acc input[type=datetime-local]:focus { outline: 0; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.15); }
        .acc-foot { display: flex; justify-content: flex-end; }
        .acc .acc-save { height: 36px; padding: 0 22px; font-size: 13px; border-radius: 8px; background: linear-gradient(135deg, #6366f1, #4f46e5); box-shadow: 0 4px 12px rgba(79,70,229,.25); transition: transform .12s, box-shadow .12s; }
        .acc .acc-save:hover { background: linear-gradient(135deg, #5458e8, #4338ca); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(79,70,229,.3); }
