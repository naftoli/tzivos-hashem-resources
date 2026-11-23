<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Resources Admin')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #fff; color: #212121; }
        .wrap { display: flex; min-height: 100vh; }
        .aside { flex: 0 0 40%; background: #1b283f; display: flex; align-items: center; justify-content: center; }
        .aside img { width: 55%; max-width: 280px; height: auto; }
        .content { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .card { width: 100%; max-width: 380px; }
        h1 { font-size: 1.75rem; margin: 0 0 1.5rem; }
        label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: .35rem; }
        input[type=email], input[type=password] { width: 100%; padding: .75rem .9rem; border: 1px solid #e5eaee; border-radius: 6px; background: #f3f6f9; font-size: 1rem; }
        .field { margin-bottom: 1.1rem; }
        .row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; font-size: .9rem; }
        button { width: 100%; padding: .8rem; border: 0; border-radius: 6px; background: #3699ff; color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #187de4; }
        a { color: #3699ff; text-decoration: none; }
        .error { background: #ffe2e5; color: #a1192b; padding: .7rem .9rem; border-radius: 6px; margin-bottom: 1rem; font-size: .9rem; }
        .footer { margin-top: 1.5rem; text-align: center; font-size: .9rem; }
        @media (max-width: 800px) { .aside { display: none; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="aside"><img src="{{ asset('site-preview/assets/f90a774f9a601cdd.svg') }}" alt="Tzivos Hashem"></div>
    <div class="content"><div class="card">@yield('content')</div></div>
</div>
</body>
</html>