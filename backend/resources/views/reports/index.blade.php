<x-app-layout>
    <x-slot name="header"><h2>Reports</h2></x-slot>

    @foreach ($groups as $title => $items)
        <h5 class="mb-3 {{ $loop->first ? '' : 'mt-4' }}">{{ $title }}</h5>

        <div class="row g-3">
            @foreach ($items as $item)
                <div class="col-sm-6 col-lg-4">
                    <a href="{{ route($item['route']) }}" class="card h-100 text-decoration-none text-body">
                        <div class="card-body d-flex">
                            <div class="me-3 text-gold fs-2"><i class="bi bi-{{ $item['icon'] }}"></i></div>
                            <div>
                                <div class="fw-semibold fs-5">{{ $item['title'] }}</div>
                                <div class="text-muted small">{{ $item['text'] }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endforeach
</x-app-layout>
