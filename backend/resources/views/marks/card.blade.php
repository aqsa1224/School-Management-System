<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Result Card - {{ $student->full_name }} - {{ $exam->name }}</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <style>
        body { background: #f6f4ee; }
        .card-sheet { max-width: 820px; margin: 1.5rem auto; background: #fff; border: 2px solid #d4af37; border-radius: .75rem; padding: 2rem; }
        .card-sheet h1 { font-size: 1.6rem; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .card-sheet { margin: 0; border: 2px solid #000; border-radius: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
@php
    $gradeColor = ['A+' => 'success', 'A' => 'success', 'B' => 'primary', 'C' => 'info', 'D' => 'warning', 'F' => 'danger'];
    $clean = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

<div class="container no-print pt-3" style="max-width:820px">
    <div class="d-flex justify-content-between">
        <a href="{{ url()->previous() }}" class="btn btn-outline-dark"><i class="bi bi-arrow-left"></i> Back</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
    </div>
</div>

<div class="card-sheet">
    <div class="text-center mb-4">
        @if (! empty($school['logo_url']))
            <img src="{{ $school['logo_url'] }}" alt="" style="max-height:72px;max-width:200px;object-fit:contain" class="mb-2">
        @endif
        <h1 class="mb-0">{{ $school['name'] }}</h1>
        @if ($school['address'] || $school['phone'])
            <div class="text-muted small">{{ $school['address'] }}{{ $school['address'] && $school['phone'] ? ' | ' : '' }}{{ $school['phone'] }}</div>
        @endif
        <div class="text-uppercase text-muted">Result Card &mdash; {{ $exam->name }}</div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <div><strong>Name:</strong> {{ $student->full_name }}</div>
            <div><strong>Admission no:</strong> {{ $student->admission_no }}</div>
            <div><strong>Guardian:</strong> {{ $student->guardian_name }}</div>
        </div>
        <div class="col-6 text-end">
            <div><strong>Class:</strong> {{ $student->schoolClass->name }}{{ $student->section ? ' - ' . $student->section->name : '' }}</div>
            <div><strong>Exam:</strong> {{ $exam->name }}</div>
            @if ($exam->start_date)
                <div><strong>Date:</strong> {{ $exam->start_date->format('d M Y') }}{{ $exam->end_date ? ' - ' . $exam->end_date->format('d M Y') : '' }}</div>
            @endif
        </div>
    </div>

    <table class="table table-bordered align-middle">
        <thead class="table-dark">
            <tr><th>Subject</th><th class="text-center">Total</th><th class="text-center">Obtained</th><th class="text-center">%</th><th class="text-center">Grade</th></tr>
        </thead>
        <tbody>
            @foreach ($row['marks']->sortBy(fn ($m) => $m->subject->name) as $m)
                <tr class="{{ $m->percentage < $passMark ? 'table-danger' : '' }}">
                    <td>{{ $m->subject->name }}</td>
                    <td class="text-center">{{ $clean($m->total_marks) }}</td>
                    <td class="text-center">{{ $clean($m->obtained_marks) }}</td>
                    <td class="text-center">{{ $m->percentage }}</td>
                    <td class="text-center">{{ \App\Support\Grading::grade($m->percentage) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="fw-bold">
            <tr>
                <td>Grand total</td>
                <td class="text-center">{{ $clean($row['total']) }}</td>
                <td class="text-center">{{ $clean($row['obtained']) }}</td>
                <td class="text-center">{{ $row['percentage'] }}</td>
                <td class="text-center">{{ $row['grade'] }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="row text-center my-4 g-2">
        <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Result</div><div class="fs-5 fw-bold text-{{ $row['result'] === 'Pass' ? 'success' : 'danger' }}">{{ $row['result'] }}</div></div></div>
        <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Percentage</div><div class="fs-5 fw-bold">{{ $row['percentage'] }}%</div></div></div>
        <div class="col-4"><div class="border rounded p-2"><div class="text-muted small">Position in class</div><div class="fs-5 fw-bold">{{ $row['position'] }} <span class="fs-6 text-muted">/ {{ $ranked }}</span></div></div></div>
    </div>

    <div class="small text-muted">Pass marks: {{ $clean($passMark) }}% har subject me. Grades: A+ 80+, A 70+, B 60+, C 50+, D 40+, F 40 se kam.</div>

    <div class="row mt-5 pt-4">
        <div class="col-6 text-center"><div class="border-top pt-1 mx-4">Class teacher</div></div>
        <div class="col-6 text-center"><div class="border-top pt-1 mx-4">Principal</div></div>
    </div>
</div>
</body>
</html>
