<x-app-layout>
    <x-slot name="header"><h2>{{ $report['title'] }}</h2></x-slot>

    @php
        $alignClass = ['start' => '', 'center' => 'text-center', 'end' => 'text-end'];
    @endphp

    <div class="mb-3">
        <a href="{{ route('reports.index') }}" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i> All reports</a>
    </div>

    @if ($report['notice'])
        <div class="alert alert-warning"><i class="bi bi-info-circle me-1"></i> {{ $report['notice'] }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            @include('reports._filters')
        </div>
    </div>

    @if (! $report['ready'])
        <div class="text-muted"><i class="bi bi-arrow-up"></i> {{ $report['prompt'] }}</div>
    @else
        {{-- Summary cards --}}
        @if (count($report['summary']))
            <div class="row g-3 mb-3">
                @foreach ($report['summary'] as $item)
                    <div class="col-6 col-md-4 col-xl">
                        <div class="card h-100"><div class="card-body">
                            <div class="text-muted small">{{ $item['label'] }}</div>
                            <div class="fs-5 fw-semibold {{ ! empty($item['class']) ? 'text-' . $item['class'] : '' }}">{{ $item['value'] }}</div>
                        </div></div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-start mb-3">
                    <div>
                        <div class="fw-semibold">{{ $report['title'] }}</div>
                        @if ($report['subtitle'] !== '')
                            <div class="text-muted small">{{ $report['subtitle'] }}</div>
                        @endif
                        <div class="text-muted small">{{ $paged->total() }} row(s)</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ $links['print'] }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-printer me-1"></i> Print</a>
                        <a href="{{ $links['pdf'] }}" class="btn btn-outline-gold"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
                        <a href="{{ $links['csv'] }}" class="btn btn-outline-dark"><i class="bi bi-filetype-csv me-1"></i> CSV / Excel</a>
                    </div>
                </div>

                @if ($report['note'] !== '')
                    <div class="text-muted small mb-2"><i class="bi bi-info-circle me-1"></i>{{ $report['note'] }}</div>
                @endif

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                @foreach ($report['columns'] as $col)
                                    <th class="text-nowrap {{ $alignClass[$col['align']] }}">{{ $col['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paged as $row)
                                <tr>
                                    @foreach ($row as $i => $cell)
                                        {{-- Pehla column "#" poori list ka number hai, isliye page 2 par 26 se shuru hota hai --}}
                                        <td class="{{ $alignClass[$report['columns'][$i]['align']] }} text-nowrap">
                                            @if ($cell['url'])
                                                <a href="{{ $cell['url'] }}" class="text-decoration-none fw-semibold {{ $cell['class'] ? 'text-' . $cell['class'] : '' }}">{{ $cell['text'] }}</a>
                                            @elseif ($cell['class'])
                                                <span class="fw-semibold text-{{ $cell['class'] }}">{{ $cell['text'] }}</span>
                                            @else
                                                {{ $cell['text'] }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($report['columns']) }}" class="text-center text-muted py-5">
                                    <i class="bi bi-bar-chart-line fs-1 d-block mb-2"></i>
                                    {{ $report['empty'] }}
                                </td></tr>
                            @endforelse
                        </tbody>
                        @if ($report['footer'] && $paged->total() > 0)
                            <tfoot class="table-light">
                                <tr class="fw-semibold">
                                    @foreach ($report['footer'] as $i => $cell)
                                        <td class="{{ $alignClass[$report['columns'][$i]['align']] }} text-nowrap">{{ $cell['text'] }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                <div class="mt-3">{{ $paged->links('pagination::bootstrap-5') }}</div>
            </div>
        </div>
    @endif
</x-app-layout>
