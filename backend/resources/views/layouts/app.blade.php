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
<body>
<div class="app-shell">

    @include('layouts.sidebar')

    <div class="app-main">
        {{-- Top bar --}}
        <header class="topbar">
            <button class="btn btn-outline-dark d-lg-none me-2" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Open menu">
                <i class="bi bi-list"></i>
            </button>

            <div class="topbar-title flex-grow-1">
                @isset($header)
                    <div class="page-header">{{ $header }}</div>
                @endisset
            </div>

            <div class="dropdown">
                <button class="btn btn-light border d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar me-2">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                    <span class="d-none d-sm-inline">{{ Auth::user()->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-muted">{{ Auth::user()->role_label }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        {{-- Page content --}}
        <main class="app-content">
            <x-alerts />
            {{ $slot }}
        </main>

        <footer class="app-footer">
            &copy; {{ date('Y') }} {{ $school['name'] }}
        </footer>
    </div>
</div>

@stack('scripts')
</body>
</html>
