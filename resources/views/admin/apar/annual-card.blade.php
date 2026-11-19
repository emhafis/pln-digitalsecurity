<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Inspeksi APAR {{ $apar->apar_code }} - {{ $year }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; }
        h1 { font-size: 13px; margin: 0 0 4px; text-align: center; }
        .sub { text-align: center; margin-bottom: 8px; color: #374151; font-size: 9px; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.meta td { padding: 2px 4px; vertical-align: top; }
        table.meta .label { width: 110px; color: #374151; }
        table.meta .value { font-weight: 600; }
        table.card { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.card th, table.card td { border: 1px solid #374151; padding: 3px 2px; vertical-align: top; }
        table.card th { background: #f3f4f6; font-size: 7.5px; text-align: center; }
        .month { font-weight: 600; text-align: center; }
        .center { text-align: center; }
        .small { font-size: 7.5px; }
        .nowrap { white-space: nowrap; }
        .sign { height: 22px; }
        .muted { color: #6b7280; }
        .check { font-size: 7px; line-height: 1.2; }
    </style>
</head>
<body>
    <h1>KARTU INSPEKSI APAR TAHUNAN</h1>
    <div class="sub">Periode Tahun {{ $year }} (Januari - Desember)</div>

    <table class="meta">
        <tr>
            <td class="label">Kode APAR</td><td class="value">: {{ $apar->apar_code }}</td>
            <td class="label">Jenis / Kapasitas</td><td class="value">: {{ $aparTypeLabel }} / {{ $apar->capacity ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Merek</td><td class="value">: {{ $apar->brand ?? '-' }}</td>
            <td class="label">Nomor Seri</td><td class="value">: {{ $apar->serial_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Unit</td><td class="value">: {{ $apar->unit?->unit_name ?? '-' }}</td>
            <td class="label">Lokasi</td><td class="value">: {{ $apar->placement ?? '-' }}</td>
        </tr>
    </table>

    <table class="card">
        <thead>
            <tr>
                <th style="width:34px;">Bulan</th>
                <th style="width:52px;">Tanggal Inspeksi</th>
                <th>Checklist (10 Item)</th>
                <th style="width:70px;">Hasil / Catatan</th>
                <th style="width:70px;">Nama Petugas</th>
                <th style="width:55px;">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($months as $m)
            @php $rows = $inspectionsByMonth[$m['num']] ?? []; @endphp
            @if (count($rows) === 0)
                <tr>
                    <td class="month">{{ $m['name'] }}</td>
                    <td class="center muted">-</td>
                    <td class="muted small">Belum ada inspeksi</td>
                    <td class="center muted">-</td>
                    <td class="center muted">-</td>
                    <td class="sign"></td>
                </tr>
            @else
                @foreach ($rows as $idx => $insp)
                    <tr>
                        @if ($idx === 0)
                            <td class="month" rowspan="{{ count($rows) }}">{{ $m['name'] }}</td>
                        @endif
                        <td class="center nowrap">{{ $insp['date_label'] }}</td>
                        <td class="check">
                            @foreach ($insp['items'] as $it)
                                <div>{{ $loop->iteration }}. {{ $it['label'] }}: <strong>{{ $it['status'] }}</strong>@if($it['note']) ({{ $it['note'] }})@endif</div>
                            @endforeach
                        </td>
                        <td class="small">
                            <div><strong>{{ $insp['result_label'] }}</strong></div>
                            @if($insp['notes'])<div>{{ $insp['notes'] }}</div>@endif
                        </td>
                        <td class="small">{{ $insp['officer'] }}</td>
                        <td class="sign"></td>
                    </tr>
                @endforeach
            @endif
        @endforeach
        </tbody>
    </table>
    <div style="margin-top:6px;font-size:7.5px;color:#6b7280;">Dicetak: {{ $printedAt }}. Seluruh inspeksi dalam periode ditampilkan sesuai data historis. Kolom tanda tangan disediakan untuk keperluan cetak manual.</div>
</body>
</html>
