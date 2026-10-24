<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: 14px; }
        body { font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif; font-size: 10px; color: #111; background: #f3f1ea; margin: 0; }
        .sheet { margin: 14px auto; max-width: {{ $landscape ? '1100px' : '780px' }}; background: #fff; border: 2px solid #d4af37; padding: 12px 18px; }
        .head { text-align: center; border-bottom: 2px solid #d4af37; padding-bottom: 6px; margin-bottom: 8px; }
        .head .school { font-size: 18px; font-weight: bold; }
        .head .sub { color: #555; font-size: 10px; }
        .title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 1px; margin: 2px 0 2px; text-transform: uppercase; }
        .subtitle { text-align: center; color: #444; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        table.summary td { border: 1px solid #bbb; padding: 4px 8px; width: auto; }
        table.box { margin-top: 8px; }
        table.box th { background: #111; color: #fff; text-align: left; padding: 4px 5px; border: 1px solid #111; }
        table.box td { border: 1px solid #999; padding: 3px 5px; vertical-align: top; }
        table.box tr.total td { background: #f1ead0; font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        .muted { color: #666; }
        .strong { font-weight: bold; }
        .t-success { color: #198754; font-weight: bold; }
        .t-danger { color: #dc3545; font-weight: bold; }
        .t-warning { color: #b8860b; font-weight: bold; }
        .t-secondary { color: #6c6c6c; }
        .note { color: #666; margin-top: 6px; }
        .foot { margin-top: 10px; color: #666; font-size: 9px; }
        .toolbar { margin: 14px auto 0; max-width: {{ $landscape ? '1100px' : '780px' }}; }
        .toolbar a, .toolbar button { display: inline-block; font: inherit; font-size: 13px; padding: 7px 14px; border: 1px solid #111; background: #fff; color: #111; text-decoration: none; cursor: pointer; margin-right: 6px; }
        .toolbar button.primary { background: #d4af37; border-color: #d4af37; font-weight: bold; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        /* PDF: page ki poori chaurai lo, safed background */
        body.pdf { background: #fff; }
        body.pdf .sheet { max-width: none; margin: 0; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0 auto; max-width: none; } }
    </style>
</head>
<body class="{{ $pdf ? 'pdf' : '' }}">
@php
    $align = ['start' => '', 'center' => 'center', 'end' => 'right'];
@endphp

@unless ($pdf)
    <div class="toolbar">
        <a href="{{ $backUrl }}">&larr; Back</a>
        <button type="button" class="primary" onclick="window.print()">Print</button>
        <a href="{{ $pdfUrl }}">Download PDF</a>
    </div>
@endunless

<div class="sheet">
    <div class="head">
        @if (! empty($school['logo_src']))
            <div style="margin-bottom:4px"><img src="{{ $school['logo_src'] }}" alt="" style="max-height:44px;max-width:140px"></div>
        @endif
        <div class="school">{{ $school['name'] }}</div>
        @if ($school['address'] || $school['phone'])
            <div class="sub">{{ $school['address'] }}{{ $school['address'] && $school['phone'] ? ' | ' : '' }}{{ $school['phone'] }}</div>
        @endif
    </div>

    <div class="title">{{ $report['title'] }}</div>
    @if ($report['subtitle'] !== '')
        <div class="subtitle">{{ $report['subtitle'] }}</div>
    @endif

    @if (count($report['summary']))
        <table class="summary"><tr>
            @foreach ($report['summary'] as $item)
                <td>
                    <div class="muted">{{ $item['label'] }}</div>
                    <div class="strong {{ ! empty($item['class']) ? 't-' . $item['class'] : '' }}">{{ $item['value'] }}</div>
                </td>
            @endforeach
        </tr></table>
    @endif

    <table class="box">
        <thead>
            <tr>
                @foreach ($report['columns'] as $col)
                    <th class="{{ $align[$col['align']] }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($row as $i => $cell)
                        <td class="{{ $align[$report['columns'][$i]['align']] }}">
                            @if ($cell['class'])
                                <span class="t-{{ $cell['class'] }}">{{ $cell['text'] }}</span>
                            @else
                                {{ $cell['text'] }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}" class="center muted">{{ $report['empty'] }}</td></tr>
            @endforelse

            @if ($report['footer'] && count($report['rows']))
                <tr class="total">
                    @foreach ($report['footer'] as $i => $cell)
                        <td class="{{ $align[$report['columns'][$i]['align']] }}">{{ $cell['text'] }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    @if ($report['note'] !== '')
        <div class="note">{{ $report['note'] }}</div>
    @endif

    <div class="foot">
        Printed on {{ now()->format('d M Y, h:i A') }}@auth by {{ auth()->user()->name }}@endauth
    </div>
</div>
</body>
</html>
