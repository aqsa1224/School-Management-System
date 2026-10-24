<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $school['name'] }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <div class="auth-wrap">
        <a href="{{ url('/') }}" class="auth-brand text-decoration-none">
            @if (! empty($school['logo_url']))
                <img src="{{ $school['logo_url'] }}" alt="" style="height:72px;width:auto;max-width:200px;object-fit:contain">
            @else
                <x-application-logo class="auth-logo" />
            @endif
            <div class="auth-title">{{ $school['name'] }}</div>
        </a>

        <div class="card auth-card">
            <div class="card-body p-4">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
