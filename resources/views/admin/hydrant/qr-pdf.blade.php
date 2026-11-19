<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QR Code Hydrant - {{ $hydrant->hydrant_code }}</title>
    <style>
        @page { margin: 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .card { max-width: 420px; margin: 0 auto; padding: 18px; border: 1px dashed #94a3b8; border-radius: 10px; }
        .brand { margin-bottom: 12px; color: #0c3743; font-size: 14px; font-weight: bold; text-align: center; text-transform: uppercase; letter-spacing: .4px; }
        .qr { text-align: center; margin: 10px 0 14px; }
        .qr img { width: 220px; height: 220px; }
        table.meta { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.meta td { padding: 3px 0; vertical-align: top; }
        table.meta td.label { width: 110px; color: #6b7280; }
        table.meta td.value { color: #111827; font-weight: 600; }
        .uuid { margin-top: 10px; font-size: 9px; color: #6b7280; word-break: break-all; text-align: center; }
        .footer { margin-top: 10px; font-size: 9px; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">PLN Digital Security - QR Hydrant</div>
        <div class="qr">
            <img src="{{ $qrDataUri }}" alt="QR {{ $hydrant->hydrant_code }}">
        </div>
        <table class="meta">
            <tr><td class="label">Kode Hydrant</td><td class="value">: {{ $hydrant->hydrant_code }}</td></tr>
            <tr><td class="label">Nama</td><td class="value">: {{ $hydrant->name }}</td></tr>
            <tr><td class="label">Jenis</td><td class="value">: {{ $hydrantTypeLabel }}</td></tr>
            <tr><td class="label">Unit</td><td class="value">: {{ $hydrant->unit?->unit_name ?? '-' }}</td></tr>
            <tr><td class="label">Lokasi</td><td class="value">: {{ $hydrant->placement ?? '-' }}</td></tr>
        </table>
        <div class="uuid">Kode Unik (UUID): {{ $hydrant->qr_code }}</div>
        <div class="footer">Pindai QR untuk membuka detail Hydrant. Dicetak: {{ $printedAt }}</div>
    </div>
</body>
</html>
