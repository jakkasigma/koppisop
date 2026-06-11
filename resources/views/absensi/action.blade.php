@extends('layouts.staff')

@php
    $focusIsCheckIn = ($focusMode ?? 'checkin') === 'checkin';
@endphp

@section('title', $focusIsCheckIn ? 'Absen Masuk' : 'Absen Pulang')

@section('content')
<div class="container app-shell">
    @php
        $nama = (string) ($staffKaryawan->nama_karyawan ?? session('staff_karyawan_name', 'Karyawan'));
        $jabatan = trim((string) ($staffKaryawan->jabatan ?? 'Staff')) ?: 'Staff';
        $tipeKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentTypeLabel')) ? $staffKaryawan->employmentTypeLabel() : 'Full Time';
        $durasiKerja = ($staffKaryawan && method_exists($staffKaryawan, 'employmentDurationLabel')) ? $staffKaryawan->employmentDurationLabel() : '8 jam';
        $windowClass = (string) ($windowInfo['class'] ?? 'warn');
        $alreadyMasuk = !empty($alreadyMasuk);
        $alreadyPulang = !empty($alreadyPulang);
        $blockTodayForm = !empty($blockTodayForm);
        $pendingCorrection = $pendingCorrection ?? null;
        $statusClass = (string) ($absenInfo['statusClass'] ?? ($alreadyMasuk ? 'ok' : 'warn'));
        $statusLabel = $alreadyMasuk
            ? ((string) ($absenInfo['statusLabel'] ?? 'Sudah Absen'))
            : ((string) ($windowInfo['label'] ?? 'Menunggu Waktu Absen'));
        $shiftCode = (string) ($windowInfo['shiftCode'] ?? '');
        $shiftLabel = isset($windowInfo['shiftNo']) && (int) ($windowInfo['shiftNo'] ?? 0) > 0
            ? (($shiftCode !== '' ? $shiftCode : ('Shift ' . (int) $windowInfo['shiftNo'])))
            : 'Belum ada shift hari ini';
        $windowRange = isset($windowInfo['openAt'], $windowInfo['closeAt'])
            ? ($windowInfo['openAt'] . ' - ' . $windowInfo['closeAt'])
            : 'Menyesuaikan jadwal hari ini';
        $requirementLabel = collect([
            !empty($requireSelfie) ? 'selfie kamera' : null,
            !empty($requireGeofence) ? 'lokasi aktif' : null,
        ])->filter()->implode(' dan ');
        $pageTitle = $blockTodayForm ? 'Koreksi Absen' : ($focusIsCheckIn ? 'Absen Masuk' : 'Absen Pulang');
        $pageSubtitle = $blockTodayForm
            ? 'Selesaikan koreksi jam pulang sebelumnya supaya absensi hari ini kembali normal.'
            : ($focusIsCheckIn
                ? 'Halaman ini khusus untuk clock-in, selfie, dan lokasi supaya proses absen lebih fokus.'
                : 'Halaman ini khusus untuk clock-out supaya proses selesai kerja terasa lebih ringkas.');
        $summaryStatus = $blockTodayForm
            ? ((string) ($pendingCorrection['stateLabel'] ?? 'Perlu Koreksi'))
            : ($focusIsCheckIn
                ? ($alreadyMasuk ? 'Masuk Sudah Tercatat' : 'Siap Clock-in')
                : ($alreadyPulang ? 'Pulang Sudah Tercatat' : ($alreadyMasuk ? 'Siap Clock-out' : 'Belum Clock-in')));
    @endphp

    <div class="staff-mobile-page-screen">
        <section class="staff-mobile-page-stage">
            @include('staff.partials.mobile-page-header', [
                'pageTitle' => $pageTitle,
                'pageMark' => 'AB',
                'staffName' => $nama,
                'greetingTitle' => $pageTitle,
                'greetingSubtitle' => $pageSubtitle,
                'employmentLabel' => $tipeKerja,
                'employmentMeta' => $jabatan . ' • ' . $durasiKerja,
            ])

            <article class="staff-mobile-page-summary-card">
                <div class="staff-mobile-page-summary-topline">
                    <div class="staff-mobile-page-summary-period">
                        <span class="staff-mobile-page-summary-label">Status Sekarang</span>
                        <strong>{{ $summaryStatus }}</strong>
                    </div>
                    <span class="staff-mobile-page-pill">{{ $shiftCode !== '' ? $shiftCode : 'SHIFT' }}</span>
                </div>

                <div class="staff-mobile-page-summary-stats">
                    <article>
                        <span>Shift</span>
                        <strong>{{ $shiftLabel }}</strong>
                    </article>
                    <article>
                        <span>Window</span>
                        <strong>{{ $windowRange }}</strong>
                    </article>
                    <article>
                        <span>Masuk</span>
                        <strong>{{ (string) ($absenInfo['masuk'] ?? '-') }}</strong>
                    </article>
                    <article>
                        <span>Pulang</span>
                        <strong>{{ (string) ($absenInfo['pulang'] ?? '-') }}</strong>
                    </article>
                </div>

                <div class="staff-mobile-page-summary-actions">
                    <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali ke Ringkasan</a>
                </div>
            </article>
        </section>

        <div class="panel attendance-panel">
            @if(session('success'))
                <div class="alert ok">{{ session('success') }}</div>
            @endif

            @if($errors->has('absensi'))
                <div class="alert ok">{{ $errors->first('absensi') }}</div>
            @endif

            @if($alreadyMasuk && !$alreadyPulang)
                <div class="alert ok">Absen masuk hari ini sudah tercatat. Kalau shift atau jam kerjamu sudah selesai, lanjutkan dengan absen pulang.</div>
            @elseif($alreadyMasuk && $alreadyPulang)
                <div class="alert ok">Absen masuk dan pulang hari ini sudah lengkap. Data ini tinggal menunggu verifikasi admin.</div>
            @endif

            @if($blockTodayForm && $pendingCorrection)
                <div class="alert err">Masih ada absensi {{ $pendingCorrection['tanggalLabel'] }} yang belum selesai. Selesaikan koreksi jam pulang dulu sebelum absen hari ini.</div>
            @endif

            @php
                $otherErrors = [];
                foreach ($errors->all() as $message) {
                    if ($message === $errors->first('absensi')) {
                        continue;
                    }
                    $otherErrors[] = $message;
                }
            @endphp

            @if(!empty($otherErrors))
                <div class="alert err">
                    @foreach($otherErrors as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="attendance-section-head">
                <div>
                    <h2 class="attendance-section-title">
                        @if($blockTodayForm)
                            Koreksi Jam Pulang
                        @elseif($focusIsCheckIn)
                            Form Absen Masuk
                        @else
                            Form Absen Pulang
                        @endif
                    </h2>
                    <div class="attendance-section-sub">
                        @if($blockTodayForm)
                            Isi jam pulang yang benar supaya admin bisa meninjau absensimu.
                        @elseif($focusIsCheckIn)
                            Lengkapi data yang dibutuhkan sebelum clock-in.
                        @else
                            Selesaikan kerja hari ini dari halaman yang lebih fokus dan ringkas.
                        @endif
                    </div>
                </div>
                <div class="attendance-badges">
                    <span class="pill {{ $blockTodayForm ? ($pendingCorrection['stateClass'] ?? 'warn') : ($alreadyMasuk ? 'ok' : $statusClass) }}">{{ $blockTodayForm ? ($pendingCorrection['stateLabel'] ?? 'Koreksi') : $statusLabel }}</span>
                    @if(isset($windowInfo['shiftNo']) && (int) ($windowInfo['shiftNo'] ?? 0) > 0)
                        <span class="pill gray">{{ $shiftCode !== '' ? $shiftCode : ('Shift ' . (int) $windowInfo['shiftNo']) }}</span>
                    @endif
                </div>
            </div>

            @if($blockTodayForm && $pendingCorrection)
                <div class="attendance-form-grid">
                    <div class="attendance-card">
                        <div class="attendance-card-title">Tanggal</div>
                        <div class="attendance-karyawan-name">{{ $pendingCorrection['tanggalLabel'] }}</div>
                    </div>
                    <div class="attendance-card">
                        <div class="attendance-card-title">Shift</div>
                        <div class="attendance-karyawan-name">{{ $pendingCorrection['shiftLabel'] }}</div>
                    </div>
                    <div class="attendance-card">
                        <div class="attendance-card-title">Jam Masuk</div>
                        <div class="attendance-karyawan-name">{{ $pendingCorrection['masuk'] }}</div>
                    </div>
                    <div class="attendance-card full">
                        <div class="attendance-card-title">Status Koreksi</div>
                        <div class="attendance-card-sub">{{ $pendingCorrection['note'] }}</div>
                        @if(!empty($pendingCorrection['requestedPulangLabel']))
                            <div class="attendance-card-sub">Usulan terakhir: {{ $pendingCorrection['requestedPulangLabel'] }}</div>
                        @endif
                        @if(!empty($pendingCorrection['reviewNote']))
                            <div class="attendance-card-sub">Catatan admin: {{ $pendingCorrection['reviewNote'] }}</div>
                        @endif
                        <div class="attendance-card-sub">Batas ajukan koreksi mandiri: {{ $pendingCorrection['deadlineLabel'] }}</div>
                    </div>
                </div>

                @if(($pendingCorrection['stateKey'] ?? '') === 'requested')
                    <div class="attendance-card full">
                        <div class="attendance-card-head">
                            <div>
                                <div class="attendance-card-title">Menunggu Tinjauan Admin</div>
                                <div class="attendance-card-sub">Usulan jam pulangmu sudah masuk. Sekarang tinggal tunggu admin menyetujui atau memberi koreksi manual.</div>
                            </div>
                            <span class="pill gray">Pending</span>
                        </div>
                        <div class="attendance-card-sub">Kamu bisa cek status terbaru lewat menu Pesan.</div>
                    </div>
                    <div class="actions">
                        <a class="btn-primary" href="{{ route('staff.messages.index') }}">Lihat Pesan</a>
                        <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali</a>
                    </div>
                @elseif($pendingCorrection['canRequest'])
                    <form id="attendance-correction-form" method="post" action="{{ route('absen.checkout_correction.request', ['absensi' => $pendingCorrection['id']]) }}">
                        @csrf
                        <div class="attendance-form-grid">
                            <div class="attendance-card">
                                <div class="attendance-card-head">
                                    <div>
                                        <div class="attendance-card-title">Jam Pulang yang Benar</div>
                                        <div class="attendance-card-sub">Isi jam pulang sesuai kondisi sebenarnya. Sistem akan minta persetujuan admin.</div>
                                    </div>
                                    <span class="pill gray">H+1</span>
                                </div>
                                <input type="datetime-local" name="requested_pulang" value="{{ $pendingCorrection['requestedPulangInput'] }}" required>
                            </div>
                            <div class="attendance-card full">
                                <div class="attendance-card-head">
                                    <div>
                                        <div class="attendance-card-title">Alasan / Catatan</div>
                                        <div class="attendance-card-sub">Bantu admin memahami kenapa jam pulangnya perlu dikoreksi.</div>
                                    </div>
                                </div>
                                <textarea name="request_note" rows="4" placeholder="Contoh: lupa klik absen pulang saat tutup toko" required>{{ $pendingCorrection['requestNote'] }}</textarea>
                            </div>
                        </div>
                        <div class="actions">
                            <button class="btn-primary" type="submit">Ajukan Koreksi</button>
                            <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali</a>
                        </div>
                    </form>
                @else
                    <div class="attendance-card full">
                        <div class="attendance-card-head">
                            <div>
                                <div class="attendance-card-title">Perlu Bantuan Admin</div>
                                <div class="attendance-card-sub">Batas koreksi mandiri sudah lewat. Admin perlu mengisi jam pulang secara manual dari dashboard.</div>
                            </div>
                            <span class="pill gray">Admin</span>
                        </div>
                        <div class="attendance-card-sub">Buka menu Pesan kalau perlu mengingatkan admin untuk meninjau absensi ini.</div>
                    </div>
                    <div class="actions">
                        <a class="btn-primary" href="{{ route('staff.messages.index') }}">Hubungi via Pesan</a>
                        <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali</a>
                    </div>
                @endif
            @elseif($focusIsCheckIn)
                @if(!$alreadyMasuk)
                    <div id="attendance-checkin-form"></div>
                    <form id="absenForm" method="post" action="{{ route('absen.masuk') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="attendance-form-grid">
                            @if(isset($staffKaryawan) && $staffKaryawan)
                                <input type="hidden" name="id_karyawan" value="{{ (int) $staffKaryawan->id_karyawan }}">
                                <div class="attendance-card full">
                                    <div class="attendance-card-head">
                                        <div>
                                            <div class="attendance-card-title">Karyawan</div>
                                            <div class="attendance-card-sub">Absensi akan dicatat untuk akun staf yang sedang login.</div>
                                        </div>
                                        <span class="pill gray">{{ $staffKaryawan->jabatan ?? 'Staff' }}</span>
                                    </div>
                                    <div class="attendance-karyawan-name">{{ $staffKaryawan->nama_karyawan }}</div>
                                </div>
                            @endif

                            <div class="attendance-card">
                                <div class="attendance-card-head">
                                    <div>
                                        <div class="attendance-card-title">Catatan</div>
                                        <div class="attendance-card-sub">Opsional, tapi berguna kalau ada hal yang ingin dijelaskan ke admin.</div>
                                    </div>
                                </div>
                                <input id="catatan" name="catatan" value="{{ old('catatan') }}" placeholder="Contoh: datang 10 menit lebih awal">
                            </div>

                            @if(!empty($requireSelfie))
                                <div class="attendance-card full">
                                    <div class="attendance-card-head">
                                        <div>
                                            <div class="attendance-card-title">Selfie</div>
                                            <div class="attendance-card-sub">Ambil foto langsung dari kamera sebelum mengirim absensi.</div>
                                        </div>
                                        <span class="pill gray">Wajib</span>
                                    </div>

                                    <div class="attendance-camera-wrap">
                                        <button class="btn-primary cam-open" type="button" id="camOpenBtn">Ambil Foto</button>
                                        <div id="selfiePreview" class="preview"><img alt="Preview selfie"></div>
                                    </div>

                                    <input type="hidden" id="selfie_source" name="selfie_source" value="">
                                    <input id="selfie" type="file" name="selfie" accept="image/*" hidden>

                                    <div class="modal" id="camModal" hidden>
                                        <div class="modal-backdrop"></div>
                                        <div class="modal-card">
                                            <div class="modal-head">
                                                <div>
                                                    <div class="modal-title">Ambil Foto</div>
                                                    <div class="modal-sub">Pastikan wajah terlihat jelas sebelum menyimpan foto.</div>
                                                </div>
                                                <button type="button" class="modal-close">Tutup</button>
                                            </div>
                                            <div class="cam" id="camBox">
                                                <video id="camVideo" playsinline autoplay muted hidden></video>
                                                <canvas id="camCanvas" hidden></canvas>
                                                <div class="modal-camera-note" id="camMeta">Foto akan langsung diambil dari kamera perangkat ini.</div>
                                                <div class="camera-actions portal">
                                                    <button class="btn-neutral" type="button" id="camStartBtn">Buka Kamera</button>
                                                    <button class="btn-primary" type="button" id="camShotBtn" disabled>Ambil Foto</button>
                                                    <button class="btn-neutral" type="button" id="camRetakeBtn" hidden>Ulangi</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(!empty($requireGeofence))
                                <div class="attendance-card full">
                                    <div class="attendance-card-head">
                                        <div>
                                            <div class="attendance-card-title">Lokasi</div>
                                            <div class="attendance-card-sub">Pastikan GPS aktif supaya lokasi bisa dikirim bersama absensi.</div>
                                        </div>
                                        <div class="attendance-badges">
                                            <span class="pill gray">Radius {{ (int) ($geoRadiusM ?? 150) }}m</span>
                                            <span class="pill gray">Akurasi maks {{ (int) ($geoMaxAccuracyM ?? 80) }}m</span>
                                        </div>
                                    </div>

                                    <input type="hidden" id="geo_lat" name="geo_lat" value="{{ old('geo_lat') }}">
                                    <input type="hidden" id="geo_lng" name="geo_lng" value="{{ old('geo_lng') }}">
                                    <input type="hidden" id="geo_accuracy_m" name="geo_accuracy_m" value="{{ old('geo_accuracy_m') }}">

                                    <div class="attendance-location-actions">
                                        <button class="btn-neutral" type="button" id="geoBtn">Refresh Lokasi</button>
                                    </div>
                                    <div id="geoStatus" class="geo-status"></div>
                                </div>
                            @endif
                        </div>

                        <div class="actions">
                            <button id="submitBtn" class="btn-primary" type="submit">Absen Masuk</button>
                            <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali</a>
                        </div>
                    </form>
                @elseif(!$alreadyPulang)
                    <div class="attendance-card full">
                        <div class="attendance-card-head">
                            <div>
                                <div class="attendance-card-title">Absen Masuk Sudah Tercatat</div>
                                <div class="attendance-card-sub">Kamu sudah clock-in hari ini. Sekarang tinggal lanjut ke halaman absen pulang saat shift selesai.</div>
                            </div>
                            <span class="pill gray">Lanjut</span>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="btn-primary" href="{{ route('absen.pulang.page') }}">Buka Absen Pulang</a>
                        <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali</a>
                    </div>
                @else
                    <div class="attendance-card full">
                        <div class="attendance-card-head">
                            <div>
                                <div class="attendance-card-title">Absensi Hari Ini Lengkap</div>
                                <div class="attendance-card-sub">Jam masuk dan pulang sudah tercatat. Tinggal menunggu verifikasi admin.</div>
                            </div>
                            <span class="pill gray">Selesai</span>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali ke Ringkasan</a>
                    </div>
                @endif
            @else
                @if(!$alreadyMasuk)
                    <div class="attendance-card full">
                        <div class="attendance-card-head">
                            <div>
                                <div class="attendance-card-title">Belum Bisa Absen Pulang</div>
                                <div class="attendance-card-sub">Absen pulang baru aktif setelah kamu melakukan absen masuk hari ini.</div>
                            </div>
                            <span class="pill gray">Clock-in dulu</span>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="btn-primary" href="{{ route('absen.masuk.page') }}">Buka Absen Masuk</a>
                        <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali</a>
                    </div>
                @elseif(!$alreadyPulang)
                    <form id="attendance-checkout-form" method="post" action="{{ route('absen.pulang') }}">
                        @csrf
                        <div class="attendance-card full">
                            <div class="attendance-card-head">
                                <div>
                                    <div class="attendance-card-title">Absen Pulang</div>
                                    <div class="attendance-card-sub">Sekali tekan saja untuk mencatat jam selesai kerja hari ini. Tidak perlu isi lokasi lagi.</div>
                                </div>
                                <span class="pill gray">Praktis</span>
                            </div>
                            <div class="attendance-card-sub">Setelah absen pulang, durasi kerja harian akan dipakai untuk perhitungan gaji part time secara per jam.</div>
                        </div>
                        <div class="actions">
                            <button class="btn-primary" type="submit">Absen Pulang</button>
                            <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali</a>
                        </div>
                    </form>
                @else
                    <div class="attendance-card full">
                        <div class="attendance-card-head">
                            <div>
                                <div class="attendance-card-title">Absen Pulang Sudah Tercatat</div>
                                <div class="attendance-card-sub">Shift hari ini sudah selesai dan datanya sudah lengkap.</div>
                            </div>
                            <span class="pill gray">Selesai</span>
                        </div>
                    </div>
                    <div class="actions">
                        <a class="btn-neutral" href="{{ route('absen.form') }}">Kembali ke Ringkasan</a>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection

@if($focusIsCheckIn && !$blockTodayForm && !$alreadyMasuk)
@section('scripts')
<script>
(() => {
    const form = document.getElementById('absenForm');
    const submitBtn = document.getElementById('submitBtn');
    if (!form || !submitBtn) return;

    const alreadyAbsen = @json(!empty($alreadyAbsen));
    if (alreadyAbsen) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '.65';
        submitBtn.style.cursor = 'not-allowed';
    }

    const selfieInput = document.getElementById('selfie');
    const selfiePreview = document.getElementById('selfiePreview');
    const geoBtn = document.getElementById('geoBtn');
    const geoLat = document.getElementById('geo_lat');
    const geoLng = document.getElementById('geo_lng');
    const geoAcc = document.getElementById('geo_accuracy_m');
    const geoStatus = document.getElementById('geoStatus');
    const selfieSource = document.getElementById('selfie_source');

    const setGeoStatus = (text, cls) => {
        if (!geoStatus) return;
        geoStatus.textContent = text || '';
        geoStatus.className = 'geo-status' + (cls ? ' ' + cls : '');
    };

    if (selfieInput && selfiePreview) {
        const img = selfiePreview.querySelector('img');
        const showPreviewFile = (file) => {
            if (!file || !img) {
                selfiePreview.style.display = 'none';
                return;
            }
            const url = URL.createObjectURL(file);
            img.src = url;
            selfiePreview.style.display = 'block';
        };

        selfieInput.addEventListener('change', () => {
            const file = selfieInput.files && selfieInput.files[0] ? selfieInput.files[0] : null;
            showPreviewFile(file);
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
        const closeModalBtn = camModal?.querySelector('.modal-close');

        const closeModal = () => camModal?.setAttribute('hidden', 'hidden');
        const openModal = () => camModal?.removeAttribute('hidden');

        let stream = null;
        const stopStream = () => {
            if (!stream) return;
            try {
                stream.getTracks().forEach(track => track.stop());
            } catch (_) {}
            stream = null;
        };

        const disableRealtimeCamera = () => {
            if (camMeta) camMeta.textContent = 'Kamera realtime hanya bisa dipakai di browser yang mendukung akses kamera perangkat.';
            if (camStartBtn) camStartBtn.disabled = true;
            if (camShotBtn) camShotBtn.disabled = true;
            if (camRetakeBtn) camRetakeBtn.hidden = true;
        };

        const canRealtimeCamera = () => {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        };

        if (camModal && camOpenBtn) {
            camOpenBtn.addEventListener('click', () => {
                openModal();
                setTimeout(() => camStartBtn?.click(), 50);
            });
            closeModalBtn?.addEventListener('click', closeModal);
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
                    camVideo.hidden = false;
                    camCanvas.hidden = true;
                    camRetakeBtn.hidden = true;
                    camShotBtn.disabled = false;
                } catch (error) {
                    disableRealtimeCamera();
                }
            });

            camShotBtn.addEventListener('click', async () => {
                if (!camVideo.videoWidth || !camVideo.videoHeight) return;

                const width = camVideo.videoWidth;
                const height = camVideo.videoHeight;
                camCanvas.width = width;
                camCanvas.height = height;
                const context = camCanvas.getContext('2d');
                context.drawImage(camVideo, 0, 0, width, height);

                camCanvas.hidden = true;
                camVideo.hidden = true;
                camRetakeBtn.hidden = false;
                camShotBtn.disabled = true;

                camCanvas.toBlob((blob) => {
                    if (!blob) return;
                    const file = new File([blob], `selfie-${Date.now()}.jpg`, { type: 'image/jpeg' });
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    selfieInput.files = dataTransfer.files;
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
                camShotBtn.disabled = true;
                camRetakeBtn.hidden = true;
                camStartBtn.click();
            });

            window.addEventListener('beforeunload', () => stopStream());
        }
    }

    if (geoLat && geoLng && geoAcc && geoStatus) {
        const requireGeo = @json(!empty($requireGeofence));

        const setSubmitEnabled = (enabled) => {
            if (!submitBtn) return;
            submitBtn.disabled = !enabled;
            submitBtn.style.opacity = enabled ? '1' : '.7';
            submitBtn.style.cursor = enabled ? 'pointer' : 'not-allowed';
        };

        const hasGeo = () => {
            return !!(geoLat.value && geoLng.value);
        };

        if (requireGeo) {
            setSubmitEnabled(hasGeo());
        }

        if (!window.isSecureContext) {
            if (geoBtn) {
                geoBtn.disabled = true;
                geoBtn.textContent = 'Butuh akses lokasi aman';
            }
            setGeoStatus('GPS/geolocation hanya bisa dipakai di browser yang mendukung akses lokasi aman.', 'err');
            if (requireGeo) setSubmitEnabled(false);
        }

        const requestGeo = () => {
            setGeoStatus('Mengambil lokasi...', '');
            if (!window.isSecureContext) {
                setGeoStatus('GPS/geolocation hanya bisa dipakai di browser yang mendukung akses lokasi aman.', 'err');
                if (requireGeo) setSubmitEnabled(false);
                return;
            }
            if (!navigator.geolocation) {
                setGeoStatus('Browser tidak mendukung GPS.', 'err');
                if (requireGeo) setSubmitEnabled(false);
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    geoLat.value = String(position.coords.latitude || '');
                    geoLng.value = String(position.coords.longitude || '');
                    geoAcc.value = String(Math.round(position.coords.accuracy || 0));
                    setGeoStatus(`Lokasi siap  -  akurasi ${geoAcc.value}m`, 'ok');
                    if (requireGeo) setSubmitEnabled(true);
                },
                () => {
                    setGeoStatus('Gagal mengambil lokasi. Aktifkan GPS dan izin lokasi lalu coba lagi.', 'err');
                    if (requireGeo) setSubmitEnabled(false);
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        };

        geoBtn?.addEventListener('click', requestGeo);

        if (requireGeo) {
            if (!hasGeo()) {
                setTimeout(requestGeo, 250);
            } else {
                setGeoStatus(`Lokasi siap  -  akurasi ${geoAcc.value || '-'}m`, 'ok');
                setSubmitEnabled(true);
            }
        }
    }

    form.addEventListener('submit', (event) => {
        const requireGeo = @json(!empty($requireGeofence));
        const requireSelfie = @json(!empty($requireSelfie));

        if (requireGeo && geoLat && geoLng) {
            if (!geoLat.value || !geoLng.value) {
                event.preventDefault();
                setGeoStatus('Lokasi wajib diambil sebelum absen.', 'err');
            }
        }

        if (requireSelfie && selfieInput) {
            const file = selfieInput.files && selfieInput.files[0] ? selfieInput.files[0] : null;
            if (!file) {
                event.preventDefault();
                alert('Selfie wajib diambil sebelum absen.');
            }
            if (selfieSource && selfieSource.value !== 'camera') {
                event.preventDefault();
                alert('Selfie harus diambil langsung dari kamera.');
            }
        }
    });
})();
</script>
@endsection
@endif
