<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $school['name'] }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <div class="auth-wrap text-center">
        @if (! empty($school['logo_url']))
            <img src="{{ $school['logo_url'] }}" alt="" style="height:96px;width:auto;max-width:240px;object-fit:contain">
        @else
            <x-application-logo class="auth-logo" />
        @endif
        <h1 class="auth-title display-6 mt-3">{{ $school['name'] }}</h1>
        <p class="text-secondary mb-4">Students, teachers, attendance, results and fees &mdash; all in one place.</p>

        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg px-5">Go to Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-5">Log in</a>
        @endauth
    </div>
</body>
</html>
