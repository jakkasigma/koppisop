<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $setting = \App\Models\StrukSetting::current();
        $profile = \App\Models\CafeProfile::query()->find(1);
        $themePrimary = $setting->theme_primary ?: '#0b6e68';
        $themeSecondary = $setting->theme_secondary ?: '#22c55e';
        $themeBg = $setting->theme_bg ?: '#f3f5f4';
        $appName = config('app.name', 'KopiSop');
        $brandName = trim((string) ($profile?->nama_cafe ?: $appName));
        $brandTagline = trim((string) ($profile?->tagline ?: 'Kelola operasional cafe dengan lebih rapi dan nyaman.'));
        $brandLogo = trim((string) ($profile?->logo_path ?? ''));
    @endphp
    <title>Masuk ke Sistem</title>
    @vite('resources/css/app.css')
    <style>
        :root { --bg:{{ $themeBg }}; --ink:#1f2937; --panel:#fffdf9; --line:#e7d8c0; --accent:{{ $themePrimary }}; --accent-2:{{ $themeSecondary }}; --muted:#6b7280; }
    </style>
</head>
<body class="page-login auth-login-page">
<div class="auth-login-shell">
    <div class="auth-login-card">
        <section class="auth-login-intro">
            <span class="auth-login-badge">Portal Operasional</span>
            <div class="auth-login-brand">
                @if($brandLogo !== '')
                    <div class="auth-login-brand-logo">
                        <img src="{{ asset('storage/' . $brandLogo) }}" alt="Logo {{ $brandName }}">
                    </div>
                @endif
                <div class="auth-login-brand-copy">
                    <h1>Masuk ke {{ $brandName }}</h1>
                    <p class="auth-login-sub">{{ $brandTagline }}</p>
                </div>
            </div>

            <p class="auth-login-copy">Masuk untuk melanjutkan aktivitas harian, memantau toko, dan mengelola operasional sesuai kebutuhanmu.</p>

            <div class="auth-login-summary">
                Masuk untuk mengakses dashboard admin atau operasional kasir sesuai akun yang kamu gunakan.
            </div>

            <div class="auth-login-tip">
                <span class="auth-login-tip-label">Akses cepat</span>
                <strong>Default admin: admin@kopisop.local</strong>
                <span>Password: password</span>
            </div>
        </section>

        <section class="auth-login-form-panel">
            <div class="auth-login-form-head">
                <span class="auth-login-form-badge">{{ $brandName }}</span>
                <h2>Masuk ke sistem</h2>
                <p>Gunakan akun admin atau kasir yang sudah terdaftar untuk melanjutkan.</p>
            </div>

            @if ($errors->any())
                <div class="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('login.submit') }}" class="auth-login-form">
                @csrf

                <div class="auth-login-field">
                    <label for="loginEmail">Email</label>
                    <input id="loginEmail" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="nama@kopisop.local">
                </div>

                <div class="auth-login-field">
                    <label for="loginPassword">Password</label>
                    <input id="loginPassword" type="password" name="password" required placeholder="Masukkan password">
                </div>

                <label class="remember">
                    <input type="checkbox" name="remember" value="1">
                    <span>Ingat saya di perangkat ini</span>
                </label>

                <button type="submit">Masuk ke Sistem</button>
            </form>
        </section>
    </div>
</div>
</body>
</html>

