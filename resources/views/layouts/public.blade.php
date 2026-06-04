<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'HomPim Play - Approval')</title>
    <link rel="icon" href="{{ asset('hompimplay_icon.png') }}">
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; padding: 24px; background: #f8fafc; color: #0f172a; display: grid; place-items: center; }
        .shell { width: min(100%, 620px); }
        .brand { display: flex; align-items: center; gap: 12px; margin: 0 auto 16px; width: fit-content; color: #334155; }
        .brand-mark { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 14px; background: #0f766e; color: white; font-weight: 800; }
        .brand-title { margin: 0; font-size: 16px; font-weight: 750; }
        .brand-subtitle { margin: 2px 0 0; color: #64748b; font-size: 12px; }
        .card { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 20px; background: white; box-shadow: 0 18px 50px rgba(15, 23, 42, .09); }
        .card-header { padding: 24px; background: linear-gradient(135deg, #0f766e, #0f4c81); color: white; }
        .card-header.success { background: linear-gradient(135deg, #15803d, #0f766e); }
        .card-header.danger { background: linear-gradient(135deg, #b91c1c, #9f1239); }
        .card-header.neutral { background: linear-gradient(135deg, #475569, #334155); }
        .eyebrow { margin: 0 0 6px; color: rgba(255, 255, 255, .8); font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 0; font-size: 24px; line-height: 1.2; }
        .header-text { margin: 8px 0 0; color: rgba(255, 255, 255, .84); font-size: 14px; }
        .card-body { padding: 24px; }
        .employee { margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px solid #e2e8f0; }
        .label { margin: 0 0 4px; color: #64748b; font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
        .value { margin: 0; color: #0f172a; font-size: 15px; font-weight: 700; }
        .details { display: grid; gap: 14px; }
        .detail { padding: 14px; border: 1px solid #e2e8f0; border-radius: 14px; background: #f8fafc; }
        .notice { margin: 18px 0; padding: 14px; border: 1px solid #fde68a; border-radius: 14px; background: #fffbeb; color: #92400e; font-size: 13px; line-height: 1.55; }
        .message { color: #475569; font-size: 15px; line-height: 1.7; text-align: center; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .button { width: 100%; border: 0; border-radius: 12px; padding: 13px 16px; color: white; cursor: pointer; font-size: 14px; font-weight: 750; transition: transform .15s, opacity .15s; }
        .button:hover { opacity: .92; transform: translateY(-1px); }
        .button.reject { background: #dc2626; }
        .button.approve { background: #0f766e; }
        .card-footer { padding: 14px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 12px; line-height: 1.6; text-align: center; }
        @media (max-width: 520px) { body { padding: 14px; } .card-body, .card-header { padding: 20px; } h1 { font-size: 21px; } }
    </style>
</head>
<body>
    <main class="shell">
        <div class="brand">
            <div class="brand-mark">HP</div>
            <div>
                <p class="brand-title">HomPim Play HRIS</p>
                <p class="brand-subtitle">Portal persetujuan pengajuan</p>
            </div>
        </div>
        @yield('content')
    </main>
</body>
</html>
