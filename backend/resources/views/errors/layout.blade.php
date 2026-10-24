<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') - {{ config('app.name') }}</title>
    {{-- Jaan-boojh ke Vite/Bootstrap ke baghair: error page tab bhi chalna chahiye jab CSS build na ho --}}
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px;
               font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
               background: radial-gradient(circle at top, #1a1a1a 0%, #0b0b0b 70%); color: #e8e8e8; }
        .box { max-width: 480px; width: 100%; text-align: center; background: #fff; color: #222; border-radius: 12px;
               border-top: 4px solid #d4af37; padding: 36px 28px; }
        .code { font-size: 64px; font-weight: 800; color: #d4af37; line-height: 1; }
        h1 { font-size: 22px; margin: 12px 0 8px; }
        p { color: #555; line-height: 1.5; margin: 0 0 22px; }
        a.btn { display: inline-block; padding: 10px 22px; border-radius: 8px; background: #d4af37; color: #111;
                text-decoration: none; font-weight: 600; margin: 0 4px; }
        a.btn.light { background: #fff; border: 1px solid #111; }
        .school { color: #999; font-size: 13px; margin-top: 22px; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        @auth
            <a class="btn" href="{{ url('/dashboard') }}">Dashboard</a>
            <a class="btn light" href="javascript:history.back()">Wapas jayein</a>
        @else
            <a class="btn" href="{{ url('/login') }}">Login</a>
        @endauth
        <div class="school">{{ config('app.name') }}</div>
    </div>
</body>
</html>
