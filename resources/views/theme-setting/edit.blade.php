@extends('layouts.app')

@section('title', 'Tema Aplikasi')

@section('content')
@php
    $themeDefaults = [
        'primary' => '#003535',
        'secondary' => '#32D1C3',
        'bg' => '#F8F9FF',
    ];
    $themePresets = [
        [
            'key' => 'stitch',
            'name' => 'Command Teal',
            'desc' => 'Default Stitch untuk admin KopiSop.',
            'primary' => '#003535',
            'secondary' => '#32D1C3',
            'bg' => '#F8F9FF',
        ],
        [
            'key' => 'emerald',
            'name' => 'Emerald Ops',
            'desc' => 'Hijau modern untuk operasional yang segar.',
            'primary' => '#064E3B',
            'secondary' => '#34D399',
            'bg' => '#F3FBF7',
        ],
        [
            'key' => 'cobalt',
            'name' => 'Cobalt Calm',
            'desc' => 'Biru terang untuk dashboard yang rapi.',
            'primary' => '#1D4ED8',
            'secondary' => '#38BDF8',
            'bg' => '#F5F8FF',
        ],
        [
            'key' => 'graphite',
            'name' => 'Graphite Desk',
            'desc' => 'Netral dan serius untuk tampilan kerja.',
            'primary' => '#263238',
            'secondary' => '#94A3B8',
            'bg' => '#F7F9FC',
        ],
        [
            'key' => 'coffee',
            'name' => 'Coffee Warm',
            'desc' => 'Hangat untuk brand cafe bernuansa kopi.',
            'primary' => '#5C4033',
            'secondary' => '#D0A16B',
            'bg' => '#FAF5EF',
        ],
        [
            'key' => 'ruby',
            'name' => 'Ruby Service',
            'desc' => 'Aksen merah muda untuk promo dan layanan.',
            'primary' => '#9F1239',
            'secondary' => '#FB7185',
            'bg' => '#FFF5F7',
        ],
        [
            'key' => 'amber',
            'name' => 'Amber Shift',
            'desc' => 'Kuning hangat untuk shift dan transaksi cepat.',
            'primary' => '#92400E',
            'secondary' => '#F59E0B',
            'bg' => '#FFFBEB',
        ],
        [
            'key' => 'indigo',
            'name' => 'Indigo Fresh',
            'desc' => 'Kontras bersih dengan aksen cyan.',
            'primary' => '#4338CA',
            'secondary' => '#22D3EE',
            'bg' => '#F7F7FF',
        ],
    ];
@endphp

<div class="container theme-setting-page">
    <div class="admin-page-head theme-page-head">
        <div>
            <span class="admin-page-kicker">Pengaturan Sistem</span>
            <h1>Tema Aplikasi</h1>
            <p>Ubah warna admin, sidebar, tombol, dan aksen panel supaya semua menu terasa satu tema.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <form method="post" action="{{ route('theme_setting.update') }}" class="theme-builder">
        @csrf
        @method('PUT')

        <section class="panel theme-builder-panel">
            <div class="theme-section-head">
                <div>
                    <h2>Preset Warna</h2>
                    <p>Pilih cepat dari beberapa gaya, lalu haluskan lagi lewat kode HEX bila perlu.</p>
                </div>
                <select id="theme_preset" class="theme-preset-select" aria-label="Preset tema">
                    <option value="">Manual</option>
                    @foreach($themePresets as $preset)
                        <option value="{{ $preset['key'] }}">{{ $preset['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="theme-preset-grid">
                @foreach($themePresets as $preset)
                    <button
                        type="button"
                        class="theme-preset-tile"
                        data-preset="{{ $preset['key'] }}"
                        aria-label="Pilih preset {{ $preset['name'] }}"
                    >
                        <span class="theme-preset-swatches" aria-hidden="true">
                            <span style="background: {{ $preset['primary'] }}"></span>
                            <span style="background: {{ $preset['secondary'] }}"></span>
                            <span style="background: {{ $preset['bg'] }}"></span>
                        </span>
                        <strong>{{ $preset['name'] }}</strong>
                        <small>{{ $preset['desc'] }}</small>
                    </button>
                @endforeach
            </div>

            <div class="theme-fields">
                <label>
                    <span>Warna Utama</span>
                    <input id="theme_primary" type="text" name="theme_primary" value="{{ old('theme_primary', $setting->theme_primary ?? '') }}" placeholder="{{ $themeDefaults['primary'] }}">
                </label>
                <label>
                    <span>Warna Sekunder</span>
                    <input id="theme_secondary" type="text" name="theme_secondary" value="{{ old('theme_secondary', $setting->theme_secondary ?? '') }}" placeholder="{{ $themeDefaults['secondary'] }}">
                </label>
                <label>
                    <span>Warna Latar</span>
                    <input id="theme_bg" type="text" name="theme_bg" value="{{ old('theme_bg', $setting->theme_bg ?? '') }}" placeholder="{{ $themeDefaults['bg'] }}">
                </label>
            </div>

            <div class="theme-note">
                Kosongkan semua field untuk kembali ke default Command Teal. Format warna yang diterima adalah HEX seperti #003535.
            </div>

            <div class="actions theme-actions">
                <button class="btn-primary" type="submit">Simpan Tema</button>
                <button class="btn-neutral" type="button" id="themeResetDefault">Pakai Default</button>
            </div>
        </section>

        <aside class="panel theme-preview-card">
            <div class="theme-section-head compact">
                <div>
                    <h2>Preview</h2>
                    <p>Gambaran cepat untuk sidebar, kartu, tabel, dan tombol admin.</p>
                </div>
            </div>

            <div class="theme-live-preview" id="themePreview">
                <div class="theme-preview-sidebar">
                    <div class="theme-preview-brand">
                        <span class="material-symbols-outlined" aria-hidden="true">coffee</span>
                        <strong>KopiSop</strong>
                    </div>
                    <div class="theme-preview-nav active">Dashboard</div>
                    <div class="theme-preview-nav">Kasir Admin</div>
                    <div class="theme-preview-nav">Transaksi</div>
                </div>
                <div class="theme-preview-main">
                    <div class="theme-preview-topbar">
                        <span>Live View</span>
                        <button type="button">Inbox</button>
                    </div>
                    <div class="theme-preview-content">
                        <div class="theme-preview-card-mini">
                            <span>Omzet Hari Ini</span>
                            <strong>Rp 4.250.000</strong>
                        </div>
                        <div class="theme-preview-card-mini accent">
                            <span>Shift Aktif</span>
                            <strong>3 Kasir</strong>
                        </div>
                        <div class="theme-preview-table">
                            <div>Produk</div>
                            <div>Total</div>
                            <div>Es Kopi Susu</div>
                            <div>42</div>
                            <div>Americano</div>
                            <div>18</div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </form>
</div>

<script>
    (function () {
        var defaults = @json($themeDefaults);
        var presets = @json(collect($themePresets)->keyBy('key'));
        var primary = document.getElementById('theme_primary');
        var secondary = document.getElementById('theme_secondary');
        var bg = document.getElementById('theme_bg');
        var select = document.getElementById('theme_preset');
        var preview = document.getElementById('themePreview');
        var reset = document.getElementById('themeResetDefault');
        var tiles = document.querySelectorAll('.theme-preset-tile');

        function normalizeHex(value, fallback) {
            var raw = String(value || '').trim();
            if (raw === '') return fallback;
            if (raw.charAt(0) !== '#') raw = '#' + raw;
            if (/^#[0-9a-fA-F]{6}$/.test(raw)) return raw.toUpperCase();
            return fallback;
        }

        function applyColors(colors) {
            primary.value = String(colors.primary || defaults.primary).toUpperCase();
            secondary.value = String(colors.secondary || defaults.secondary).toUpperCase();
            bg.value = String(colors.bg || defaults.bg).toUpperCase();
            updatePreview();
        }

        function markActive(key) {
            tiles.forEach(function (tile) {
                tile.classList.toggle('active', tile.dataset.preset === key);
            });
            if (select) select.value = key || '';
        }

        function updatePreview() {
            if (!preview) return;
            preview.style.setProperty('--theme-preview-primary', normalizeHex(primary.value, defaults.primary));
            preview.style.setProperty('--theme-preview-secondary', normalizeHex(secondary.value, defaults.secondary));
            preview.style.setProperty('--theme-preview-bg', normalizeHex(bg.value, defaults.bg));
        }

        window.applyThemePreset = function (key) {
            if (!key || !presets[key]) {
                markActive('');
                updatePreview();
                return;
            }
            applyColors(presets[key]);
            markActive(key);
        };

        tiles.forEach(function (tile) {
            tile.addEventListener('click', function () {
                window.applyThemePreset(tile.dataset.preset);
            });
        });

        [primary, secondary, bg].forEach(function (input) {
            input.addEventListener('input', function () {
                markActive('');
                updatePreview();
            });
        });

        if (select) {
            select.addEventListener('change', function () {
                window.applyThemePreset(select.value);
            });
        }

        if (reset) {
            reset.addEventListener('click', function () {
                applyColors(defaults);
                markActive('stitch');
            });
        }

        updatePreview();
    })();
</script>
@endsection
