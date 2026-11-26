<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GAMS — Persetujuan Work Order')</title>
    <style>
        :root {
            color-scheme: light;
            --gams-canvas: #f1f5f9;
            --gams-surface: #ffffff;
            --gams-surface-subtle: #f8fafc;
            --gams-navy: #0f2347;
            --gams-border: #e2e8f0;
            --gams-border-strong: #cbd5e1;
            --gams-ink: #0f172a;
            --gams-ink-secondary: #334155;
            --gams-ink-muted: #64748b;
            --gams-brand: #1d4ed8;
            --gams-brand-light: #2563eb;
            --gams-brand-soft: #eff6ff;
            --gams-brand-border: #bfdbfe;
            --gams-ok-bg: #dcfce7;
            --gams-ok-text: #166534;
            --gams-warn-bg: #fef3c7;
            --gams-warn-text: #92400e;
            --gams-err-bg: #fee2e2;
            --gams-err-text: #991b1b;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            padding: 28px 16px 48px;
            background: var(--gams-canvas);
            color: var(--gams-ink);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }
        .gams-shell {
            width: 100%;
            max-width: 720px;
        }
        .gams-card {
            background: var(--gams-surface);
            border: 1px solid var(--gams-border);
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }
        .gams-brand-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px 22px;
            background: var(--gams-navy);
            color: #ffffff;
        }
        .gams-brand-bar.is-danger {
            background: #7f1d1d;
        }
        .gams-logo {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            flex-shrink: 0;
            display: block;
        }
        .gams-brand-bar h1 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            line-height: 1.25;
            color: #ffffff;
        }
        .gams-brand-bar p {
            margin: 2px 0 0;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.78);
        }
        .gams-body {
            padding: 20px 22px 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .gams-banner {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13.5px;
            line-height: 1.5;
        }
        .gams-banner-ok {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .gams-banner-err {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .gams-meta-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }
        .gams-req-no {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            color: var(--gams-brand-light);
            font-variant-numeric: tabular-nums;
        }
        .gams-meta-head h2 {
            margin: 2px 0 0;
            font-size: 19px;
            font-weight: 750;
            color: var(--gams-ink);
            line-height: 1.3;
        }
        .gams-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        .gams-pill-ok { background: var(--gams-ok-bg); color: var(--gams-ok-text); }
        .gams-pill-warn { background: var(--gams-warn-bg); color: var(--gams-warn-text); }
        .gams-pill-err { background: var(--gams-err-bg); color: var(--gams-err-text); }
        .gams-pill-info { background: #dbeafe; color: #1e40af; }
        .gams-pill-muted { background: #f1f5f9; color: #475569; }
        .gams-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 16px;
            padding: 14px 16px;
            background: var(--gams-surface-subtle);
            border: 1px solid var(--gams-border);
            border-radius: 10px;
            font-size: 13px;
        }
        .gams-info-item span {
            display: block;
            font-size: 11.5px;
            color: var(--gams-ink-muted);
            margin-bottom: 2px;
        }
        .gams-info-item strong {
            display: block;
            color: var(--gams-ink);
            font-weight: 650;
            word-break: break-word;
        }
        .gams-info-item small {
            display: block;
            font-size: 11.5px;
            color: var(--gams-ink-muted);
            margin-top: 1px;
        }
        .gams-desc-box {
            margin: 0;
            padding: 12px 14px;
            background: var(--gams-surface-subtle);
            border: 1px solid var(--gams-border);
            border-radius: 10px;
            font-size: 13.5px;
            color: var(--gams-ink-secondary);
            white-space: pre-line;
            word-break: break-word;
        }
        .gams-desc-label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--gams-ink-muted);
            margin-bottom: 4px;
        }
        .gams-section h3 {
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: var(--gams-ink);
        }
        .gams-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--gams-border);
            border-radius: 10px;
        }
        .gams-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            font-variant-numeric: tabular-nums;
        }
        .gams-table th,
        .gams-table td {
            padding: 9px 11px;
            text-align: left;
            border-bottom: 1px solid var(--gams-border);
            vertical-align: top;
        }
        .gams-table th {
            background: var(--gams-surface-subtle);
            font-size: 12px;
            font-weight: 600;
            color: var(--gams-ink-muted);
            white-space: nowrap;
        }
        .gams-table tbody tr:last-child td {
            border-bottom: none;
        }
        .gams-table tfoot td {
            background: var(--gams-surface-subtle);
            border-top: 1px solid var(--gams-border);
            border-bottom: none;
            font-weight: 700;
        }
        .gams-table th.num,
        .gams-table td.num {
            text-align: right;
            white-space: nowrap;
        }
        .gams-sub {
            display: block;
            font-size: 11.5px;
            color: var(--gams-ink-muted);
        }
        .gams-approver-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .gams-approver-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border: 1px solid var(--gams-border);
            border-radius: 8px;
            background: var(--gams-surface);
            font-size: 13px;
        }
        .gams-approver-meta {
            min-width: 0;
        }
        .gams-approver-meta strong {
            color: var(--gams-ink);
        }
        .gams-approver-comment {
            margin: 3px 0 0;
            font-size: 12px;
            color: var(--gams-ink-secondary);
        }
        .gams-photos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
            gap: 10px;
        }
        .gams-photo-link {
            display: block;
            overflow: hidden;
            border-radius: 10px;
            border: 1px solid var(--gams-border);
            background: var(--gams-surface-subtle);
            transition: border-color 0.15s ease, transform 0.15s ease;
        }
        .gams-photo-link:hover {
            border-color: var(--gams-brand-light);
            transform: translateY(-1px);
        }
        .gams-photo-link img {
            width: 100%;
            height: 104px;
            object-fit: cover;
            display: block;
        }
        .gams-doc-links {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .gams-doc-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 8px;
            border: 1px solid var(--gams-brand-border);
            background: var(--gams-brand-soft);
            color: var(--gams-brand);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }
        .gams-doc-btn:hover {
            background: #dbeafe;
        }
        .gams-decision-box {
            padding: 16px;
            border-radius: 12px;
            background: var(--gams-brand-soft);
            border: 1px solid var(--gams-brand-border);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .gams-greeting {
            margin: 0;
            font-size: 13.5px;
            color: #1e3a8a;
        }
        .gams-field-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 4px;
        }
        .gams-textarea {
            width: 100%;
            min-height: 84px;
            padding: 10px 12px;
            border: 1px solid var(--gams-border-strong);
            border-radius: 8px;
            background: var(--gams-surface);
            color: var(--gams-ink);
            font: inherit;
            font-size: 13.5px;
            resize: vertical;
        }
        .gams-textarea:focus {
            outline: none;
            border-color: var(--gams-brand);
            box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.14);
        }
        .gams-decision-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 2px;
        }
        .gams-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-height: 42px;
            padding: 8px 18px;
            border-radius: 10px;
            font: inherit;
            font-size: 14px;
            font-weight: 600;
            border: 0;
            cursor: pointer;
            transition: opacity 0.15s ease, transform 0.15s ease;
        }
        .gams-btn:hover { opacity: 0.92; transform: translateY(-1px); }
        .gams-btn-approve { background: #16a34a; color: #ffffff; }
        .gams-btn-reject { background: #dc2626; color: #ffffff; }
        .gams-done-note {
            padding: 12px 14px;
            border-radius: 10px;
            background: var(--gams-surface-subtle);
            border: 1px solid var(--gams-border);
            font-size: 13.5px;
            color: var(--gams-ink-secondary);
        }
        .gams-footer {
            padding: 12px 22px;
            border-top: 1px solid var(--gams-border);
            background: var(--gams-surface-subtle);
            color: var(--gams-ink-muted);
            font-size: 12px;
            text-align: center;
        }
        @media (max-width: 600px) {
            body { padding: 14px 10px 32px; }
            .gams-brand-bar { padding: 16px; }
            .gams-body { padding: 16px; }
            .gams-info-grid { grid-template-columns: 1fr; }
            .gams-decision-actions { flex-direction: column-reverse; }
            .gams-btn { width: 100%; }
            .gams-approver-row { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <main class="gams-shell">
        @yield('content')
    </main>
</body>
</html>
