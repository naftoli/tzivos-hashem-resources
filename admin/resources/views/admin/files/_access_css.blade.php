        .acc { display: flex; flex-direction: column; gap: 10px; }
        .acc-row { display: grid; grid-template-columns: 150px 1fr; gap: 6px 14px; align-items: start; }
        .acc-l { color: #7e8299; font-size: 11.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; padding-top: 7px; }
        .acc-opts { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .acc label { margin: 0; }
        .acc label.chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 11px; border: 1px solid #e4e6ef; border-radius: 15px; background: #fff; font-weight: 500; white-space: nowrap; cursor: pointer; transition: background .12s, border-color .12s, color .12s; }
        .acc label.chip:hover { border-color: #b5d7ff; }
        .acc label.chip:has(input:checked) { background: #e1f0ff; border-color: #b5d7ff; color: #187de4; }
        .acc label.chip:has(input:disabled) { cursor: default; opacity: .75; }
        .acc label.chip input { margin: 0; accent-color: var(--blue); }
        .acc label.date { display: inline-flex; align-items: center; gap: 6px; color: #5e6278; font-weight: 500; white-space: nowrap; }
        .acc input[type=datetime-local] { height: 32px; padding: 0 8px; border: 1px solid #e4e6ef; border-radius: 6px; font: inherit; font-size: 12px; color: var(--ink); background: #fff; }
        .acc input[type=datetime-local]:focus { outline: 0; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(54,153,255,.15); }
        .acc .btn { height: 32px; padding: 0 18px; font-size: 12.5px; }
        .acc .acc-save { margin-left: auto; }
        @media (max-width: 760px) {
            .acc-row { grid-template-columns: 1fr; }
            .acc-l { padding-top: 0; }
            .acc .acc-save { margin-left: 0; }
        }
