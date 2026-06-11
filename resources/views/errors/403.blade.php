<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak</title>
    @vite('resources/css/app.css')
    <style>
        :root { --accent:#0b6e68; }
    </style>
</head>
<body class="page-errors-403">
<div class="wrap">
    <div class="card">
        <h1>403</h1>
        <p>Akses ditolak. Akun kamu tidak punya izin untuk membuka halaman ini.</p>
        <p>Jika kamu login sebagai <code>kasir</code>, halaman admin memang akan ditutup.</p>
        <div class="actions">
            <a class="primary" href="{{ route('kasir.index') }}">Ke Kasir</a>
            <a href="javascript:history.back()">Kembali</a>
        </div>
    </div>
</div>
</body>
</html>

