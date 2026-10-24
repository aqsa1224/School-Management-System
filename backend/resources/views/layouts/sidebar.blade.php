@php
    // Sidebar ke links. Do shartein: (1) route bana ho, (2) is user ke role ko permission ho (config/roles.php).
    // 'can' khali ho to sab roles ko dikhta hai.
    $menu = collect([
        ['route' => 'dashboard',          'match' => 'dashboard',     'icon' => 'speedometer2',   'label' => 'Dashboard',       'can' => []],
        ['route' => 'students.index',     'match' => 'students.*',    'icon' => 'people',         'label' => 'Students',        'can' => ['students.view']],
        ['route' => 'teachers.index',     'match' => 'teachers.*',    'icon' => 'person-badge',   'label' => 'Teachers',        'can' => ['academics.manage']],
        ['route' => 'classes.index',      'match' => 'classes.*',     'icon' => 'building',       'label' => 'Classes',         'can' => ['academics.manage']],
        ['route' => 'subjects.index',     'match' => 'subjects.*',    'icon' => 'book',           'label' => 'Subjects',        'can' => ['academics.manage']],
        ['route' => 'attendances.index',  'match' => 'attendances.*', 'icon' => 'calendar-check', 'label' => 'Attendance',      'can' => ['attendance.manage']],
        ['route' => 'exams.index',        'match' => 'exams.*',       'icon' => 'clipboard-check','label' => 'Exams',           'can' => ['academics.manage']],
        ['route' => 'marks.index',        'match' => 'marks.*',       'icon' => 'journal-text',   'label' => 'Marks / Results', 'can' => ['marks.manage']],
        ['route' => 'fees.index',         'match' => 'fees.*',        'icon' => 'cash-coin',      'label' => 'Fees',            'can' => ['fees.view']],
        ['route' => 'fee-types.index',    'match' => 'fee-types.*',   'icon' => 'tags',           'label' => 'Fee Types',       'can' => ['fee-types.manage']],
        ['route' => 'reports.index',      'match' => 'reports.*',     'icon' => 'bar-chart-line', 'label' => 'Reports',         'can' => ['reports.fees', 'reports.academic']],
        ['route' => 'settings.index',     'match' => 'settings.*',    'icon' => 'gear',           'label' => 'Settings',        'can' => ['settings.manage']],
        ['route' => 'users.index',        'match' => 'users.*',       'icon' => 'person-gear',    'label' => 'Users',           'can' => ['users.manage']],
    ])->filter(fn ($item) => Route::has($item['route'])
        && (empty($item['can']) || auth()->user()->canAny($item['can'])));
@endphp

<div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-labelledby="sidebarLabel">
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="{{ route('dashboard') }}" class="d-flex align-items-center text-decoration-none" id="sidebarLabel">
            @if (! empty($school['logo_url']))
                <img src="{{ $school['logo_url'] }}" alt="" class="me-2" style="height:34px;width:auto;max-width:46px;object-fit:contain">
            @else
                <x-application-logo class="sidebar-logo me-2" />
            @endif
            <span class="sidebar-title">{{ $school['name'] }}</span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Close"></button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            @foreach ($menu as $item)
                <li class="nav-item">
                    <a href="{{ route($item['route']) }}"
                       class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                        <i class="bi bi-{{ $item['icon'] }}"></i>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="sidebar-section">Account</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                    <i class="bi bi-person-circle"></i><span>Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link w-100 text-start border-0 bg-transparent">
                        <i class="bi bi-box-arrow-right"></i><span>Log out</span>
                    </button>
                </form>
            </li>
        </ul>
    </nav>
</div>
