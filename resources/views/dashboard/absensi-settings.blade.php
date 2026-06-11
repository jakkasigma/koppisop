@extends('layouts.app')

@section('title', 'Pengaturan Absensi')

@section('content')
@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    />
@endonce
<div class="container">
    <div class="admin-page-head">
    <div>
        <h1>Pengaturan Absensi</h1>
        <p>Atur selfie, geofence, jam shift, dan toleransi telat untuk absensi karyawan.</p>
        
    </div>

    @if ($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif

    <div class="panel">
        <div class="panel-head">
            <div>
                <h2>Pengaturan Utama</h2>
                <div class="panel-sub">Absensi</div>
            </div>
            <a class="btn-neutral" href="{{ route('dashboard.absensi') }}">Kembali ke Absensi</a>
        </div>

        <form method="post" action="{{ route('dashboard.absensi.settings.update') }}" class="form-grid">
            @csrf
            <div class="field-card">
                <div class="mini-title">Keamanan & Aturan Absen</div>
                <div class="switch-list">
                    <div class="switch-item">
                        <div>
                            <label for="absensi_require_selfie">Wajib Selfie</label>
                            <div class="switch-help">Jika aktif, karyawan harus selfie saat absen masuk.</div>
                        </div>
                        <input id="absensi_require_selfie" type="checkbox" name="absensi_require_selfie" value="1" @checked(old('absensi_require_selfie', $setting->absensi_require_selfie ?? false))>
                    </div>
                    <div class="switch-item">
                        <div>
                            <label for="absensi_require_geofence">Wajib Geofence</label>
                            <div class="switch-help">Jika aktif, absensi hanya bisa dilakukan di radius lokasi cafe.</div>
                        </div>
                        <input id="absensi_require_geofence" type="checkbox" name="absensi_require_geofence" value="1" @checked(old('absensi_require_geofence', $setting->absensi_require_geofence ?? false))>
                    </div>
                </div>
                <div class="hint">Absensi di portal karyawan tetap mewajibkan login. Aktifkan Geofence + Selfie untuk meminimalisir fake absen.</div>
            </div>

            <div class="field-card">
                <div class="mini-title">Jam Mulai & Window Absen</div>
                <div class="split-2">
                    <div>
                        <label for="shift1_start_time">Shift 1 Mulai</label>
                        <input id="shift1_start_time" type="time" name="shift1_start_time" value="{{ old('shift1_start_time', $setting->shift1_start_time ?? '07:00') }}">
                    </div>
                    <div>
                        <label for="shift2_start_time">Shift 2 Mulai</label>
                        <input id="shift2_start_time" type="time" name="shift2_start_time" value="{{ old('shift2_start_time', $setting->shift2_start_time ?? '15:00') }}">
                    </div>
                    <div>
                        <label for="shift3_start_time">Shift 3 Mulai</label>
                        <input id="shift3_start_time" type="time" name="shift3_start_time" value="{{ old('shift3_start_time', $setting->shift3_start_time ?? '23:00') }}">
                    </div>
                    <div>
                        <label for="absensi_late_tolerance_minutes">Toleransi Telat (Menit)</label>
                        <input id="absensi_late_tolerance_minutes" type="number" min="0" step="1" name="absensi_late_tolerance_minutes" value="{{ old('absensi_late_tolerance_minutes', (int) ($setting->absensi_late_tolerance_minutes ?? 10)) }}">
                    </div>
                    <div>
                        <label for="absensi_checkin_before_minutes">Boleh Absen Sebelum Shift (Menit)</label>
                        <input id="absensi_checkin_before_minutes" type="number" min="0" step="1" name="absensi_checkin_before_minutes" value="{{ old('absensi_checkin_before_minutes', (int) ($setting->absensi_checkin_before_minutes ?? 30)) }}">
                    </div>
                    <div>
                        <label for="absensi_checkin_after_minutes">Boleh Absen Setelah Shift (Menit)</label>
                        <input id="absensi_checkin_after_minutes" type="number" min="0" step="1" name="absensi_checkin_after_minutes" value="{{ old('absensi_checkin_after_minutes', (int) ($setting->absensi_checkin_after_minutes ?? 60)) }}">
                    </div>
                </div>
                <div class="hint">Absen hanya dibuka mulai X menit sebelum jam shift sampai Y menit setelah jam shift. Contoh: Shift 1 07:00, sebelum 30m berarti buka 06:30. Setelah 60m berarti tutup 08:00.</div>
            </div>

            <div class="field-card">
                <div class="mini-title">Geofence (Lokasi Cafe)</div>
                <div class="split-2">
                    <div>
                        <label for="absensi_geo_lat">Latitude</label>
                        <input id="absensi_geo_lat" type="text" name="absensi_geo_lat" value="{{ old('absensi_geo_lat', $setting->absensi_geo_lat) }}" placeholder="-6.2000000">
                    </div>
                    <div>
                        <label for="absensi_geo_lng">Longitude</label>
                        <input id="absensi_geo_lng" type="text" name="absensi_geo_lng" value="{{ old('absensi_geo_lng', $setting->absensi_geo_lng) }}" placeholder="106.8166667">
                    </div>
                    <div>
                        <label for="absensi_geo_radius_m">Radius (Meter)</label>
                        <input id="absensi_geo_radius_m" type="number" min="10" step="1" name="absensi_geo_radius_m" value="{{ old('absensi_geo_radius_m', (int) ($setting->absensi_geo_radius_m ?? 150)) }}">
                    </div>
                    <div id="geo-accuracy-wrap">
                        <label for="absensi_geo_max_accuracy_m">Max Akurasi GPS (Meter)</label>
                        <input id="absensi_geo_max_accuracy_m" type="number" min="5" step="1" name="absensi_geo_max_accuracy_m" value="{{ old('absensi_geo_max_accuracy_m', (int) ($setting->absensi_geo_max_accuracy_m ?? 80)) }}">
                        <div class="hint">Jika akurasi GPS HP lebih besar dari nilai ini, absensi ditolak (lokasi dianggap kurang akurat).</div>
                    </div>
                </div>

                <div class="geo-inline">
                    <div class="geo-readout" id="geo-readout">Lat/Lng: -</div>
                    <button class="btn-neutral" type="button" id="geo-open">Pilih Lokasi</button>
                </div>
                <div class="geo-map-note">
                    Klik <b>Pilih Lokasi</b> untuk buka peta. Kamu bisa klik peta atau geser pin untuk mengisi Latitude/Longitude.
                </div>

                <div id="geo-modal" class="modal-backdrop" aria-hidden="true">
                    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Pilih Lokasi Cafe">
                        <div class="modal-head">
                            <div>
                                <p class="modal-title">Pilih Lokasi Cafe</p>
                                <p class="modal-sub">
                                    Gunakan peta untuk mengisi koordinat geofence. Jika akses bukan HTTPS/localhost, tombol GPS akan nonaktif (aturan browser).
                                </p>
                                <div class="geo-readout u-mt-6" id="geo-readout-modal">Lat/Lng: -</div>
                            </div>
                        </div>
                        <div class="modal-body">
                            <div class="geo-tools">
                                <div class="u-flex u-gap-8 u-flex-wrap">
                                    <button class="btn-neutral" type="button" id="geo-use-current">Gunakan Lokasi Saat Ini</button>
                                    <button class="btn-neutral" type="button" id="geo-center">Center</button>
                                </div>
                                <div class="geo-map-note u-mt-0">
                                    Klik peta atau geser pin. Radius mengikuti input <b>Radius (Meter)</b>.
                                </div>
                            </div>
                            <div
                                id="geo-map"
                                class="geo-map"
                                data-lat="{{ old('absensi_geo_lat', $setting->absensi_geo_lat) }}"
                                data-lng="{{ old('absensi_geo_lng', $setting->absensi_geo_lng) }}"
                                data-radius="{{ old('absensi_geo_radius_m', (int) ($setting->absensi_geo_radius_m ?? 150)) }}"
                            ></div>
                            <div class="geo-map-note">
                                Tips: setelah pilih lokasi, kamu bisa tutup popup lalu klik <b>Simpan</b>.
                            </div>
                            <div class="modal-actions">
                                <button class="btn-neutral" type="button" id="geo-save">Simpan Lokasi</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="actions u-flex u-gap-10 u-justify-end u-flex-wrap">
                <button class="btn-primary" type="submit">Simpan Pengaturan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
    ></script>
    <script>
        (function () {
            var mapEl = document.getElementById('geo-map');
            if (!mapEl || typeof window.L === 'undefined') return;

            var modalEl = document.getElementById('geo-modal');
            var btnOpen = document.getElementById('geo-open');
            var btnSave = document.getElementById('geo-save');
            var geofenceToggle = document.getElementById('absensi_require_geofence');
            var accuracyWrap = document.getElementById('geo-accuracy-wrap');

            var latInput = document.getElementById('absensi_geo_lat');
            var lngInput = document.getElementById('absensi_geo_lng');
            var radiusInput = document.getElementById('absensi_geo_radius_m');
            var readoutEl = document.getElementById('geo-readout');
            var readoutElModal = document.getElementById('geo-readout-modal');
            var btnUseCurrent = document.getElementById('geo-use-current');
            var btnCenter = document.getElementById('geo-center');

            function showToast(message, sub) {
                var existing = document.getElementById('geo-toast');
                if (!existing) {
                    existing = document.createElement('div');
                    existing.id = 'geo-toast';
                    existing.className = 'toast';
                    document.body.appendChild(existing);
                }
                existing.innerHTML = '';
                var strong = document.createElement('div');
                strong.textContent = message || 'Tersimpan.';
                existing.appendChild(strong);
                if (sub) {
                    var small = document.createElement('small');
                    small.textContent = sub;
                    existing.appendChild(small);
                }
                existing.classList.add('show');
                clearTimeout(existing.__t);
                existing.__t = setTimeout(function () {
                    existing.classList.remove('show');
                }, 3200);
            }

            function parseNum(v) {
                if (v === null || v === undefined) return null;
                var s = String(v).trim();
                if (!s) return null;
                s = s.replace(',', '.');
                var n = parseFloat(s);
                return Number.isFinite(n) ? n : null;
            }

            function clamp(n, min, max) {
                return Math.min(max, Math.max(min, n));
            }

            function readInitialLatLng() {
                var lat = parseNum(latInput && latInput.value ? latInput.value : null);
                var lng = parseNum(lngInput && lngInput.value ? lngInput.value : null);
                if (lat !== null && lng !== null) return { lat: lat, lng: lng };

                lat = parseNum(mapEl.dataset.lat);
                lng = parseNum(mapEl.dataset.lng);
                if (lat !== null && lng !== null) return { lat: lat, lng: lng };

                return { lat: -6.2000000, lng: 106.8166667 };
            }

            var map = null;
            var marker = null;
            var circle = null;
            var mapInited = false;

            function updateReadout() {
                var latVal = (latInput && latInput.value) ? latInput.value : '';
                var lngVal = (lngInput && lngInput.value) ? lngInput.value : '';
                if (!latVal || !lngVal) {
                    if (readoutEl) readoutEl.textContent = 'Lat/Lng: -';
                    if (readoutElModal) readoutElModal.textContent = 'Lat/Lng: -';
                    return;
                }
                var text = 'Lat/Lng: ' + latVal + ', ' + lngVal;
                if (readoutEl) readoutEl.textContent = text;
                if (readoutElModal) readoutElModal.textContent = text;
            }

            function setLatLng(lat, lng, shouldPan) {
                lat = clamp(lat, -90, 90);
                lng = clamp(lng, -180, 180);
                if (marker) marker.setLatLng([lat, lng]);
                if (circle) circle.setLatLng([lat, lng]);

                if (latInput) latInput.value = lat.toFixed(7);
                if (lngInput) lngInput.value = lng.toFixed(7);

                mapEl.classList.remove('is-empty');
                updateReadout();

                if (shouldPan && map) map.panTo([lat, lng]);
            }

            function initMapIfNeeded() {
                if (mapInited) return;
                mapInited = true;

                var initial = readInitialLatLng();
                var hasInitial = initial && initial.lat !== null && initial.lng !== null;
                mapEl.classList.toggle('is-empty', !hasInitial);

                map = L.map(mapEl, { zoomControl: true });
                map.setView([initial.lat, initial.lng], hasInitial ? 16 : 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                marker = L.marker([initial.lat, initial.lng], { draggable: true }).addTo(map);
                var radius = parseInt(radiusInput && radiusInput.value ? radiusInput.value : (mapEl.dataset.radius || '150'), 10);
                if (!Number.isFinite(radius) || radius <= 0) radius = 150;

                circle = L.circle([initial.lat, initial.lng], {
                    radius: radius,
                    color: 'var(--accent)',
                    weight: 2,
                    fillColor: 'var(--accent)',
                    fillOpacity: 0.12
                }).addTo(map);

                marker.on('dragend', function () {
                    var p = marker.getLatLng();
                    setLatLng(p.lat, p.lng, false);
                });

                map.on('click', function (e) {
                    setLatLng(e.latlng.lat, e.latlng.lng, false);
                });

                if (radiusInput) {
                    radiusInput.addEventListener('input', function () {
                        if (!circle) return;
                        var r = parseInt(radiusInput.value || '0', 10);
                        if (!Number.isFinite(r) || r <= 0) return;
                        circle.setRadius(r);
                    });
                }
            }

            function syncFromInputs() {
                if (!latInput || !lngInput) return;
                var lat = parseNum(latInput.value);
                var lng = parseNum(lngInput.value);
                if (lat === null || lng === null) {
                    updateReadout();
                    return;
                }
                setLatLng(lat, lng, true);
            }

            if (latInput) latInput.addEventListener('change', syncFromInputs);
            if (lngInput) lngInput.addEventListener('change', syncFromInputs);

            if (btnCenter) {
                btnCenter.addEventListener('click', function () {
                    initMapIfNeeded();
                    if (!map || !marker) return;
                    var p = marker.getLatLng();
                    map.setView([p.lat, p.lng], Math.max(map.getZoom(), 16));
                });
            }

            if (btnUseCurrent) {
                if (!window.isSecureContext) {
                    btnUseCurrent.disabled = true;
                    btnUseCurrent.title = 'Fitur ini butuh HTTPS atau localhost (secure origin).';
                    btnUseCurrent.textContent = 'Lokasi (butuh HTTPS)';
                }
                btnUseCurrent.addEventListener('click', function () {
                    if (!window.isSecureContext) {
                        alert('Browser hanya mengizinkan GPS/geolocation di HTTPS atau localhost (secure origin).');
                        return;
                    }
                    if (!navigator.geolocation) {
                        alert('Browser ini tidak mendukung GPS/geolocation.');
                        return;
                    }
                    btnUseCurrent.disabled = true;
                    btnUseCurrent.textContent = 'Mengambil lokasi...';

                    navigator.geolocation.getCurrentPosition(function (pos) {
                        btnUseCurrent.disabled = false;
                        btnUseCurrent.textContent = 'Gunakan Lokasi Saat Ini';

                        initMapIfNeeded();
                        setLatLng(pos.coords.latitude, pos.coords.longitude, true);
                        if (map) map.setView([pos.coords.latitude, pos.coords.longitude], 17);
                    }, function (err) {
                        btnUseCurrent.disabled = false;
                        btnUseCurrent.textContent = 'Gunakan Lokasi Saat Ini';

                        var msg = err && err.message ? err.message : 'Gagal mengambil lokasi.';
                        alert(msg);
                    }, {
                        enableHighAccuracy: true,
                        timeout: 12000,
                        maximumAge: 0
                    });
                });
            }

            function openModal() {
                if (!modalEl) return;
                modalEl.classList.add('show');
                modalEl.setAttribute('aria-hidden', 'false');

                initMapIfNeeded();
                syncFromInputs();
                var invalidate = function () { if (map) map.invalidateSize(true); };
                invalidate();
                requestAnimationFrame(invalidate);
                setTimeout(invalidate, 80);
                setTimeout(invalidate, 240);
                setTimeout(invalidate, 600);
            }

            function closeModal() {
                if (!modalEl) return;
                modalEl.classList.remove('show');
                modalEl.setAttribute('aria-hidden', 'true');
            }

            if (btnOpen) btnOpen.addEventListener('click', openModal);
            if (btnSave) {
                btnSave.addEventListener('click', function () {
                    closeModal();
                    showToast('Lokasi terisi.', 'Klik "Simpan Pengaturan" di bawah untuk menyimpan ke sistem.');
                });
            }
            if (modalEl) {
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && modalEl.classList.contains('show')) closeModal();
                });
                window.addEventListener('resize', function () {
                    if (modalEl.classList.contains('show') && map) map.invalidateSize(true);
                });
            }

            updateReadout();

            function syncGeofenceUI() {
                if (!accuracyWrap || !geofenceToggle) return;
                var enabled = !!geofenceToggle.checked;
                accuracyWrap.classList.toggle('is-hidden', !enabled);
            }
            if (geofenceToggle) {
                geofenceToggle.addEventListener('change', syncGeofenceUI);
            }
            syncGeofenceUI();
        })();
    </script>
@endsection
