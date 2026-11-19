<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar QR Code Titik Patroli</title>
    <style>
        @page {
            margin: 15mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-family: "Plus Jakarta Sans", sans-serif;
            font-size: 9px;
        }

        .header {
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0c3743;
        }

        .header h1 {
            margin: 0 0 5px;
            color: #111827;
            font-size: 18px;
            line-height: 1.25;
        }

        .header p {
            margin: 2px 0;
            color: #4b5563;
        }

        .header strong {
            color: #111827;
        }

        table.cards {
            width: 100%;
            border-collapse: collapse;
        }

        table.cards tr {
            page-break-inside: avoid;
        }

        td.card-cell {
            width: 50%;
            padding: 0 4px 8px;
            vertical-align: top;
        }

        .card {
            padding: 15px;
            border: 1px dashed #94a3b8;
            border-radius: 8px;
            background: #ffffff;
            page-break-inside: avoid;
        }

        .brand {
            margin-bottom: 10px;
            color: #0c3743;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .qr {
            margin: 0 auto 10px;
            text-align: center;
        }

        .qr img {
            display: inline-block;
            width: 160px;
            height: 160px;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        table.meta td {
            padding: 2px 0;
            vertical-align: top;
        }

        table.meta td.label {
            width: 42px;
            color: #6b7280;
        }

        table.meta td.value {
            color: #111827;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Daftar QR Code Titik Patroli</h1>
        <p>Pos Jaga: <strong>{{ $securityPost->name }}</strong></p>
        <p>Tanggal cetak: {{ $printedAt }}</p>
    </div>

    <table class="cards">
        @foreach ($points->chunk(2) as $chunk)
            <tr>
                @foreach ($chunk as $point)
                    <td class="card-cell">
                        <div class="card">
                            <div class="brand">PLN Digital Security</div>
                            <div class="qr">
                                <img src="{{ $point['data_uri'] }}" width="160" height="160" alt="QR Code {{ $point['name'] }}">
                            </div>
                            <table class="meta">
                                <tr>
                                    <td class="label">Unit</td>
                                    <td class="value">: {{ $securityPost->unit?->unit_name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Pos</td>
                                    <td class="value">: {{ $securityPost->name }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Titik</td>
                                    <td class="value">: {{ $point['name'] }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                @endforeach

                @if ($chunk->count() < 2)
                    <td class="card-cell"></td>
                @endif
            </tr>
        @endforeach
    </table>
</body>
</html>
