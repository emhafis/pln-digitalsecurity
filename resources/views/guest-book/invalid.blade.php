<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tautan Tidak Valid</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f9fafb;margin:0;padding:40px 16px;color:#111827}
        .card{max-width:420px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;text-align:center}
        h1{font-size:18px;margin:0 0 8px} p{margin:0 0 8px;color:#4b5563;font-size:14px} .muted{color:#6b7280;font-size:12px}
    </style>
</head>
<body>
    <div class="card">
        <h1>Tautan Tidak Valid</h1>
        <p>{{ $message ?? 'QR Code tidak valid atau tidak dapat digunakan.' }}</p>
        <p class="muted">Silakan scan ulang QR yang tersedia di lokasi unit.</p>
    </div>
</body>
</html>
