@extends('layouts.app')

@section('title', 'Absensi via Kasir')

@section('content')
@php
    $karyawanCount = (int) $karyawan->count();
@endphp
<div class="container">
    <div class="hero flow-hero">
        <div class="flow-hero-head">
            <div>
                <span class="flow-badge">Absensi Kasir</span>
                <h1>Absensi via Kasir</h1>
                <p class="hero-sub">Pilih nama karyawan yang sedang shift, verifikasi PIN, lalu ambil selfie langsung dari kamera.</p>
            </div>
            <div class="flow-stats">
                <div class="flow-stat">
                    <span>Tanggal</span>
                    <strong>{{ $today }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Shift aktif</span>
                    <strong>{{ $shiftNo ? 'S' . (int) $shiftNo : '-' }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Karyawan shift</span>
                    <strong>{{ number_format($karyawanCount, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>
        <div class="flow-steps">
            <span class="flow-step active">1. Pilih Karyawan</span>
            <span class="flow-step">2. Verifikasi PIN</span>
            <span class="flow-step">3. Ambil Selfie</span>
        </div>
        <div class="nav">
            @include('partials.top-nav-links')
        </div>
    </div>

    <div class="absen-mini-grid">
        <div class="absen-mini-card">
            <span class="absen-mini-label">Verifikasi</span>
            <strong>Nama dan PIN harus cocok</strong>
            <small>Absensi tidak akan disimpan kalau karyawan yang dipilih dan PIN tidak sesuai.</small>
        </div>
        <div class="absen-mini-card accent">
            <span class="absen-mini-label">Selfie wajib</span>
            <strong>Gunakan kamera langsung</strong>
            <small>Mode kasir memakai kamera realtime agar proses verifikasi lebih aman.</small>
        </div>
    </div>

    <div class="panel form-panel">
        <div class="flow-panel-head">
            <div>
                <h2 class="flow-panel-title">Form absensi masuk</h2>
                <p class="flow-panel-sub">Isi data berikut dengan benar supaya admin mudah memverifikasi absensi yang masuk dari perangkat kasir.</p>
            </div>
        </div>

        <div class="sub-meta-row">
            <p class="sub">Tanggal: <strong>{{ $today }}</strong></p>
            <p class="sub">Shift aktif: <strong>{{ $shiftNo ? 'S' . (int) $shiftNo : '-' }}</strong></p>
        </div>
        @if ($errors->any())
            <div class="alert err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
        @if (session('success'))
            <div class="alert ok">{{ session('success') }}</div>
        @endif

        <form method="post" action="{{ route('kasir.absen.store') }}" class="form-grid">
            @csrf
            <div class="full">
                <label>Karyawan</label>
                <select name="id_karyawan" required @disabled($karyawan->isEmpty())>
                    <option value="">{{ $karyawan->isEmpty() ? 'Tidak ada karyawan shift ini' : 'Pilih karyawan' }}</option>
                    @foreach ($karyawan as $item)
                        <option value="{{ $item->id_karyawan }}" @selected((int) old('id_karyawan') === (int) $item->id_karyawan)>
                            {{ $item->nama_karyawan }}{{ $item->jabatan ? ' - ' . $item->jabatan : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="full">
                <label>PIN Karyawan</label>
                <input type="password" name="pin" inputmode="numeric" maxlength="8" placeholder="4-8 angka" required>
            </div>

            <div class="full">
                <label>Catatan (opsional)</label>
                <textarea name="catatan" rows="3">{{ old('catatan') }}</textarea>
            </div>

            <div class="note full">
                Mode kasir wajib selfie langsung dari kamera (tidak bisa ambil dari galeri).
            </div>

            <div class="full">
                <div class="camera-panel">
                    <div class="camera-panel-head">
                        <div>
                            <label>Selfie (Wajib)</label>
                            <p class="camera-panel-sub">Ambil foto langsung dari kamera perangkat kasir sebelum menyimpan absensi.</p>
                        </div>
                        <button class="btn-primary cam-open" type="button" id="camOpenBtn">Ambil Foto</button>
                    </div>
                    <div id="selfiePreview" class="preview"><img alt="Preview selfie"></div>
                    <input type="hidden" id="selfie_source" name="selfie_source" value="">
                    <input id="selfie" type="file" name="selfie" accept="image/*" class="is-hidden">
                    <div class="modal" id="camModal" hidden>
                        <div class="modal-backdrop" data-close-modal="camModal"></div>
                        <div class="modal-card">
                            <div class="modal-head">
                                <div class="modal-title">Ambil Foto</div>
                                <button type="button" class="modal-close" data-close-modal="camModal">Tutup</button>
                            </div>
                            <div class="cam" id="camBox">
                                <div class="row">
                                    <button class="btn-neutral" type="button" id="camStartBtn">Buka Kamera</button>
                                    <button class="btn-neutral" type="button" id="camRetakeBtn" style="display:none;">Ulangi</button>
                                    <span class="pill cam-mode-pill" id="camModePill">Mode: Kamera</span>
                                </div>
                                <div class="meta" id="camMeta">
                                    Foto akan diambil langsung dari kamera untuk mengurangi risiko pakai gambar galeri.
                                </div>
                                <div class="cam-row">
                                    <div>
                                        <video id="camVideo" playsinline autoplay muted style="display:none;"></video>
                                        <canvas id="camCanvas" style="display:none;"></canvas>
                                    </div>
                                    <div class="cam-action">
                                        <button type="button" id="camShotBtn" class="cam-icon-btn primary" disabled aria-label="Ambil Foto"></button>
                                        <div class="cam-icon-label">Ambil</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="actions full">
                <button class="btn-primary" type="submit" @disabled($karyawan->isEmpty())>Simpan Absen</button>
                <a class="btn-neutral" href="{{ route('kasir.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const selfieInput = document.getElementById('selfie');
    const selfiePreview = document.getElementById('selfiePreview');
    const selfieSource = document.getElementById('selfie_source');
    if (selfieInput && selfiePreview) {
        const submitBtn = document.querySelector('button[type="submit"].btn-primary');
        const img = selfiePreview.querySelector('img');
        const showPreviewFile = (f) => {
            if (!f || !img) {
                selfiePreview.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                return;
            }
            const url = URL.createObjectURL(f);
            img.src = url;
            selfiePreview.style.display = 'block';
            if (submitBtn) submitBtn.disabled = false;
        };
        selfieInput.addEventListener('change', () => {
            const f = selfieInput.files && selfieInput.files[0] ? selfieInput.files[0] : null;
            showPreviewFile(f);
        });

        const camOpenBtn = document.getElementById('camOpenBtn');
        const camModal = document.getElementById('camModal');
        const camBox = document.getElementById('camBox');
        const camStartBtn = document.getElementById('camStartBtn');
        const camShotBtn = document.getElementById('camShotBtn');
        const camRetakeBtn = document.getElementById('camRetakeBtn');
        const camVideo = document.getElementById('camVideo');
        const camCanvas = document.getElementById('camCanvas');
        const camMeta = document.getElementById('camMeta');
        const camModePill = document.getElementById('camModePill');
        const closeModal = () => camModal?.setAttribute('hidden', 'hidden');
        const openModal = () => camModal?.removeAttribute('hidden');

        let stream = null;
        const stopStream = () => {
            if (!stream) return;
            try {
                stream.getTracks().forEach(t => t.stop());
            } catch (_) {}
            stream = null;
        };

        const setMode = (modeText) => {
            if (camModePill) camModePill.textContent = `Mode: ${modeText}`;
        };

        const disableRealtimeCamera = () => {
            setMode('Butuh HTTPS');
            if (camMeta) camMeta.textContent = 'Kamera realtime hanya bisa dipakai di HTTPS atau localhost (secure origin).';
            if (camStartBtn) camStartBtn.disabled = true;
            if (camShotBtn) camShotBtn.disabled = true;
            if (camRetakeBtn) camRetakeBtn.style.display = 'none';
        };

        const canRealtimeCamera = () => {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        };

        if (camModal && camOpenBtn) {
            camOpenBtn.addEventListener('click', () => {
                openModal();
                setTimeout(() => camStartBtn?.click(), 50);
            });
            camModal.querySelectorAll('.modal-close').forEach((btn) => {
                btn.addEventListener('click', closeModal);
            });
        }

        if (camBox && camStartBtn && camShotBtn && camVideo && camCanvas) {
            if (!window.isSecureContext || !canRealtimeCamera()) {
                disableRealtimeCamera();
            }

            camStartBtn.addEventListener('click', async () => {
                if (!window.isSecureContext || !canRealtimeCamera()) {
                    disableRealtimeCamera();
                    return;
                }
                stopStream();
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'user' },
                        audio: false,
                    });
                    camVideo.srcObject = stream;
                    camVideo.style.display = 'block';
                    camCanvas.style.display = 'none';
                    camRetakeBtn.style.display = 'none';
                    camShotBtn.disabled = false;
                    setMode('Kamera');
                } catch (e) {
                    disableRealtimeCamera();
                }
            });

            camShotBtn.addEventListener('click', async () => {
                if (!camVideo.videoWidth || !camVideo.videoHeight) return;
                const w = camVideo.videoWidth;
                const h = camVideo.videoHeight;
                camCanvas.width = w;
                camCanvas.height = h;
                const ctx = camCanvas.getContext('2d');
                ctx.drawImage(camVideo, 0, 0, w, h);

                camCanvas.style.display = 'none';
                camVideo.style.display = 'none';
                camRetakeBtn.style.display = 'inline-flex';
                camShotBtn.disabled = true;

                camCanvas.toBlob((blob) => {
                    if (!blob) return;
                    const file = new File([blob], `selfie-${Date.now()}.jpg`, { type: 'image/jpeg' });
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    selfieInput.files = dt.files;
                    if (selfieSource) selfieSource.value = 'camera';
                    showPreviewFile(file);
                }, 'image/jpeg', 0.85);

                stopStream();
                closeModal();
            });

            camRetakeBtn.addEventListener('click', () => {
                try { selfieInput.value = ''; } catch (_) {}
                if (selfieSource) selfieSource.value = '';
                selfiePreview.style.display = 'none';
                if (submitBtn) submitBtn.disabled = true;
                camShotBtn.disabled = true;
                camRetakeBtn.style.display = 'none';
                camStartBtn.click();
            });

            window.addEventListener('beforeunload', () => stopStream());
        }
    }
})();
</script>
@endsection
