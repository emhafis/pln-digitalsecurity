<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Kartu Inspeksi APAR - {{ $apar->apar_code }}</title>

    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            font-size: 8pt;
            line-height: 1.15;
        }

        /* PAGE */
        .page {
            width: 190mm;
            min-height: 277mm;
            margin: 10mm auto;
            padding: 0;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* HEADER / KOP SURAT */
        .header {
            width: 100%;
            display: table;
            table-layout: fixed;
            border-bottom: 2px solid #000;
            padding-bottom: 4px;
            margin-bottom: 10px;
        }

        .header-logo {
            display: table-cell;
            width: 15%;
            vertical-align: middle;
            text-align: center;
            padding-right: 8px;
        }

        .header-logo img {
            width: 55px;
            height: auto;
        }

        .header-text {
            display: table-cell;
            width: 85%;
            vertical-align: middle;
        }

        .org-name {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 1px;
            text-transform: uppercase;
        }

        .org-unit {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 1px;
        }

        .org-address {
            font-size: 7pt;
            color: #444;
            margin-top: 2px;
            margin-bottom: 2px;
        }

        .header h1 {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 4px;
        }

        /* INFORMASI APAR */
        .apar-info {
            width: 100%;
            margin-bottom: 10px;
        }

        .apar-info table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .apar-info td {
            padding: 1px 3px;
            font-size: 7.5pt;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .apar-info .label {
            width: 18%;
            font-weight: bold;
        }

        .apar-info .value {
            width: 32%;
        }

        /* CHECKLIST TABLE */
        .checklist-table {
            width: 190mm;
            max-width: 190mm;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
        }

        .checklist-table th,
        .checklist-table td {
            border: 1px solid #000;
            padding: 0;
            text-align: center;
            vertical-align: middle;
            font-size: 7.5pt;
            line-height: 1;
            overflow: hidden;
        }

        .checklist-table th {
            background-color: #f3f3f3;
            font-weight: bold;
        }

        /* SPACER ROW */
        .spacer-row td {
            height: 0;
            line-height: 0;
            padding: 0 !important;
            border: none !important;
            font-size: 0;
        }

        /* HEADER WAKTU */
        .waktu-header {
            height: 12mm;
            font-size: 7.5pt;
            font-weight: bold;
        }

        /* KOLOM BULAN */
        .month-name {
            text-align: left !important;
            padding: 0 2px !important;
            font-size: 7.5pt !important;
            font-weight: bold;
            white-space: nowrap;
        }

        /* KOLOM TANGGAL */
        .date-cell {
            padding: 0 !important;
            font-size: 7pt !important;
            white-space: nowrap;
        }

        /* KOLOM KETERANGAN */
        .keterangan-col {
            text-align: center !important;
            padding: 0 1px !important;
            font-size: 7.5pt !important;
            line-height: 1.1;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* KOLOM DIPERIKSA OLEH */
        .inspector-col {
            padding: 0 1px !important;
            font-size: 7.5pt !important;
            line-height: 1.1;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* HEADER ITEM CHECKLIST */
        .item-header {
            height: 30mm;
            padding: 0 !important;
            position: relative;
            overflow: hidden;
            text-align: center;
            vertical-align: middle;
        }

        /* TEXT ITEM VERTIKAL */
        .item-header-inner {
            position: absolute;
            width: 28mm;
            height: 4.5mm;
            top: 50%;
            left: 50%;
            margin-left: -14mm;
            margin-top: -2.25mm;
            transform: rotate(-90deg);
            white-space: nowrap;
            text-align: center;
            font-size: 5.5pt;
            font-weight: bold;
            line-height: 4.5mm;
        }

        /* ITEM CHECKLIST */
        .item-col {
            padding: 0 !important;
            height: 9mm;
            font-size: 8pt !important;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
        }

        /* CHECK SYMBOL */
        .check-good,
        .check-bad {
            display: block;
            width: 100%;
            text-align: center;
            font-size: 8pt;
            font-weight: bold;
            line-height: 9mm;
        }

        /* LEGEND */
        .legend {
            width: 100%;
            margin-top: 4px;
            margin-bottom: 8px;
            font-size: 7pt;
        }

        .legend strong {
            margin-right: 5px;
        }

        .legend span {
            margin-right: 5px;
        }

        /* CATATAN */
        .notes {
            width: 100%;
            border: 1px solid #000;
            min-height: 25mm;
            padding: 4px;
            margin-bottom: 10px;
        }

        .notes .title {
            font-size: 7.5pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .notes-content {
            min-height: 18mm;
        }

        /* SIGNATURE SECTION */
        .signature-section {
            width: 100%;
            display: table;
            table-layout: fixed;
            margin-top: 12px;
            font-size: 7.5pt;
            line-height: 1.15;
        }

        .sig-left,
        .sig-right {
            display: table-cell;
            vertical-align: top;
        }

        .sig-left {
            width: 50%;
        }

        .sig-right {
            width: 50%;
            text-align: center;
        }

        .sig-title {
            margin-bottom: 1mm;
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .sig-position {
            font-size: 7.5pt;
            text-transform: uppercase;
        }

        .sig-space {
            height: 19mm;
        }

        .sig-name {
            font-size: 8pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        /* PDF SAFETY */
        table {
            page-break-inside: auto;
        }

        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        thead {
            display: table-header-group;
        }

        tbody {
            display: table-row-group;
        }
    </style>
</head>

<body>
    <div class="page">

        {{-- HEADER --}}
        @php
            $unitParts = [];
            $current = $apar->unit;
            while ($current) {
                $unitParts[] = strtoupper($current->unit_name);
                $current = $current->parent;
            }
            $unitParts = array_reverse($unitParts);
            $unitPath = implode(' | ', $unitParts);

            $logoPlnPath = public_path('images/logo-pln.png');
            $logoPln = file_exists($logoPlnPath)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPlnPath))
                : '';
        @endphp

        <div class="header">
            <div class="header-logo">
                @if ($logoPln)
                    <img src="{{ $logoPln }}" alt="Logo PLN">
                @endif
            </div>
            <div class="header-text">
                <div class="org-name">PT PLN (PERSERO)</div>
                <div class="org-unit">{{ $unitPath }}</div>
                <div class="org-address">Jl. Tgk. Moh. Daud Beureueh No.172, Banda Aceh</div>
                <h1>Checklist Inspeksi APAR</h1>
            </div>
        </div>

        {{-- INFORMASI APAR --}}
        <div class="apar-info">
            <table>
                <tr>
                    <td class="label">Tahun</td>
                    <td class="value">: {{ $year }}</td>
                    <td class="label">Kode APAR</td>
                    <td class="value">: {{ $apar->apar_code }}</td>
                </tr>
                <tr>
                    <td class="label">Lokasi APAR</td>
                    <td class="value">: {{ $apar->placement }}</td>
                    <td class="label">Jenis APAR</td>
                    <td class="value">: {{ $apar->typeName() }}</td>
                </tr>
                <tr>
                    <td class="label">Unit</td>
                    <td class="value">: {{ $apar->unit->unit_name ?? '-' }}</td>
                    <td class="label">Kapasitas</td>
                    <td class="value">: {{ $apar->capacity }}</td>
                </tr>
                @if ($apar->serial_number)
                    <tr>
                        <td class="label">Merk</td>
                        <td class="value">: {{ $apar->brand }}</td>
                        <td class="label">No. Seri</td>
                        <td class="value">: {{ $apar->serial_number }}</td>
                    </tr>
                @endif
            </table>
        </div>

        {{-- DATA BULAN --}}
        @php
            $months = [
                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
                '04' => 'April', '05' => 'Mei', '06' => 'Juni',
                '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
                '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
            ];

            $itemCount = count($checklistItems);
            $fixedWidth = 18 + 20 + 35 + 28;
            $checklistWidth = $itemCount > 0 ? round((190 - $fixedWidth) / $itemCount, 2) : 0;

            $bulanPct      = round(18 / 190 * 100, 3);
            $tanggalPct    = round(20 / 190 * 100, 3);
            $keteranganPct = round(35 / 190 * 100, 3);
            $diperiksaPct  = round(28 / 190 * 100, 3);
            $checklistWidthPct = $itemCount > 0
                ? round((100 - $bulanPct - $tanggalPct - $keteranganPct - $diperiksaPct) / $itemCount, 3)
                : 0;
        @endphp

        {{-- CHECKLIST TABLE --}}
        <table class="checklist-table">
            <colgroup>
                <col style="width: {{ $bulanPct }}%;">
                <col style="width: {{ $tanggalPct }}%;">
                @foreach ($checklistItems as $item)
                    <col style="width: {{ $checklistWidthPct }}%;">
                @endforeach
                <col style="width: {{ $keteranganPct }}%;">
                <col style="width: {{ $diperiksaPct }}%;">
            </colgroup>

            <thead>
                {{-- Spacer row untuk mengunci lebar kolom di dompdf --}}
                <tr class="spacer-row">
                    <td style="width: {{ $bulanPct }}%;"></td>
                    <td style="width: {{ $tanggalPct }}%;"></td>
                    @foreach ($checklistItems as $item)
                        <td style="width: {{ $checklistWidthPct }}%;"></td>
                    @endforeach
                    <td style="width: {{ $keteranganPct }}%;"></td>
                    <td style="width: {{ $diperiksaPct }}%;"></td>
                </tr>

                {{-- Baris Header Pertama --}}
                <tr>
                    <th class="waktu-header" colspan="2">WAKTU</th>
                    <th class="waktu-header" colspan="{{ $itemCount }}">ITEM PEMERIKSAAN</th>
                    <th class="waktu-header keterangan-col" rowspan="2">KETERANGAN</th>
                    <th class="waktu-header inspector-col" rowspan="2">DIPERIKSA<br>OLEH</th>
                </tr>

                {{-- Baris Header Kedua --}}
                <tr>
                    <th>BULAN</th>
                    <th>TANGGAL</th>
                    @foreach ($checklistItems as $item)
                        <th class="item-header">
                            <div class="item-header-inner">
                                {{ strtoupper(str_replace('_', ' ', $item['name'])) }}
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach ($months as $num => $name)
                    @php
                        $inspection = $inspections->get($num);
                    @endphp

                    <tr>
                        <td class="month-name">{{ $name }}</td>

                        <td class="date-cell">
                            @if ($inspection && $inspection->inspected_at)
                                {{ $inspection->inspected_at->format('d-m-Y') }}
                            @endif
                        </td>

                        @if ($inspection)
                            @foreach ($checklistItems as $item)
                                @php
                                    $itemResult = $inspection->items->firstWhere('item_name', $item['name']);
                                @endphp

                                <td class="item-col">
                                    @if ($itemResult)
                                        @if ($itemResult->is_good)
                                            <span class="check-good" style="color: #16a34a;">v</span>
                                        @else
                                            <span class="check-bad" style="color: #dc2626;">x</span>
                                        @endif
                                    @endif
                                </td>
                            @endforeach

                            <td class="keterangan-col">
                                {{ Str::limit($inspection->notes ?? '', 50) }}
                            </td>

                            <td class="inspector-col">
                                {{ $inspection->security->full_name ?? '-' }}
                            </td>
                        @else
                            @foreach ($checklistItems as $item)
                                <td class="item-col"></td>
                            @endforeach
                            <td class="keterangan-col"></td>
                            <td class="inspector-col"></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- LEGEND --}}
        <div class="legend">
            <strong>Keterangan:</strong>
            <span><span style="color: #16a34a; font-weight: bold;">v</span> = Baik</span>
            <span><span style="color: #dc2626; font-weight: bold;">x</span> = Tidak</span>
        </div>

        {{-- CATATAN --}}
        <div class="notes">
            <div class="title">Catatan:</div>
            <div class="notes-content"></div>
        </div>

        {{-- SIGNATURE SECTION --}}
        <div class="signature-section">
            <div class="sig-left"></div>
            <div class="sig-right">
                <div class="sig-title">Mengetahui,</div>
                <div class="sig-space"></div>
                <div class="sig-name">________________________</div>
            </div>
        </div>

    </div>
</body>

</html>
