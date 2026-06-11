<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>401 - Belum Login</title>
    @vite('resources/css/app.css')
    <style>
        :root { --accent:#0b6e68; }
    </style>
</head>
<body class="page-errors-401">
<div class="wrap">
    <div class="card">
        <h1>401</h1>
        <p>Kamu belum login. Silakan masuk terlebih dahulu.</p>
        <div class="actions">
            <a class="primary" href="{{ route('login') }}">Ke Login</a>
            <a href="{{ url('/') }}">Kembali ke Beranda</a>
        </div>
    </div>
</div>
</body>
</html>

