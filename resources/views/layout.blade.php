<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title')</title>
    <style>
        /* No build step and no asset pipeline. This page is opened from a mail
           client by somebody who wanted to turn something off; it has to render
           on the first byte, everywhere, forever. */
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #18181b; }
        .card { max-width: 560px; margin: 8vh auto; background: #fff; border-radius: 12px; padding: 40px 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 22px; margin: 0 0 8px; }
        .lede { color: #52525b; line-height: 1.6; margin: 0 0 4px; font-size: 15px; }
        .muted { font-size: 14px; color: #71717a; margin: 0 0 4px; }

        .block { margin-top: 32px; }
        .block h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #71717a; margin: 0 0 4px; }
        .block .hint { font-size: 13px; color: #a1a1aa; line-height: 1.5; margin: 0 0 10px; }

        .list { list-style: none; margin: 0; padding: 0; border-top: 1px solid #e4e4e7; }
        .row { border-bottom: 1px solid #e4e4e7; padding: 12px 2px; }
        .row label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
        .row.is-blocked label, .row.is-locked label { cursor: not-allowed; }
        .name { font-size: 15px; color: #18181b; }
        .row.is-blocked .name, .row.is-locked .name { color: #a1a1aa; }
        .desc, .blocked, .error { display: block; font-size: 13px; line-height: 1.5; margin: 4px 0 0 26px; }
        .desc { color: #71717a; }
        .blocked { color: #a1a1aa; }
        .error { color: #b91c1c; }

        /* The matrix. A table, because it is one: types down, channels across. */
        table.matrix { width: 100%; border-collapse: collapse; font-size: 14px; }
        table.matrix th { text-align: left; font-weight: 500; color: #71717a; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; padding: 0 0 8px; border-bottom: 1px solid #e4e4e7; }
        table.matrix th.ch { text-align: center; width: 76px; }
        table.matrix td { padding: 11px 0; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        table.matrix td.ch { text-align: center; }
        table.matrix tr.is-required td, table.matrix tr.is-blocked td { color: #a1a1aa; }
        .why { display: block; font-size: 12px; color: #a1a1aa; line-height: 1.5; margin-top: 3px; }
        .why.is-error { color: #b91c1c; }

        .choices { list-style: none; margin: 0; padding: 0; border-top: 1px solid #e4e4e7; }
        .choices li { border-bottom: 1px solid #e4e4e7; padding: 11px 2px; }
        .choices label { display: flex; align-items: baseline; gap: 10px; cursor: pointer; font-size: 15px; }
        .choices .desc { margin: 0; }

        .notice { background: #f4f4f5; border-radius: 8px; padding: 10px 12px; font-size: 14px; color: #3f3f46; margin: 0 0 16px; }
        .warn { background: #fffbeb; border-radius: 8px; padding: 10px 12px; font-size: 14px; color: #854d0e; line-height: 1.5; margin: 16px 0 0; }
        .errors { list-style: none; margin: 0 0 16px; padding: 10px 12px; background: #fef2f2; border-radius: 8px; color: #b91c1c; font-size: 14px; line-height: 1.5; }

        .btn { margin-top: 22px; width: 100%; padding: 11px 16px; border: 0; border-radius: 8px; background: #18181b; color: #fff; font-size: 15px; cursor: pointer; font-family: inherit; }
        .btn-quiet { background: transparent; color: #b91c1c; border: 1px solid #e4e4e7; }
        .all { margin-top: 32px; padding-top: 8px; border-top: 1px solid #e4e4e7; }
        input[type=email] { width: 100%; box-sizing: border-box; padding: 11px 12px; border: 1px solid #d4d4d8; border-radius: 8px; font-size: 15px; font-family: inherit; }
        .foot { margin-top: 28px; font-size: 12px; color: #a1a1aa; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
    </div>
</body>
</html>
