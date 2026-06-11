@extends('layouts.app')

@section('title', 'Master Karyawan')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Master Karyawan</h1>
            <p>Kelola akun staf, cek kelengkapan profil, dan buka detail portal mereka dengan cepat.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('dashboard.absensi') }}">Absensi</a>
            <button class="btn-primary" type="button" id="openKaryawanModal">+ Karyawan</button>
        </div>
    </div>

    @php
        $total = $karyawan->count();
        $aktif = $karyawan->filter(fn ($k) => (int) ($k->is_active ?? 1) === 1)->count();
        $nonaktif = $total - $aktif;
        $punyaPin = $karyawan->filter(fn ($k) => !empty($k->pin_digest))->count();
        $belumPin = $total - $punyaPin;
        $fullTime = $karyawan->filter(fn ($k) => method_exists($k, 'employmentTypeValue') && $k->employmentTypeValue() === \App\Models\Karyawan::EMPLOYMENT_FULL_TIME)->count();
        $partTime = $total - $fullTime;
        $profilLengkap = $karyawan->filter(function ($k) {
            return trim((string) ($k->no_telepon ?? '')) !== ''
                && trim((string) ($k->alamat ?? '')) !== ''
                && trim((string) ($k->foto_profil_path ?? '')) !== '';
        })->count();
        $profilButuhUpdate = $total - $profilLengkap;
    @endphp

    <div class="kpi-grid">
        <div class="kpi gray">
            <div class="label">Total Karyawan</div>
            <div class="value">{{ $total }}</div>
            <div class="sub">Jumlah karyawan yang terdaftar di sistem.</div>
        </div>
        <div class="kpi">
            <div class="label">Aktif</div>
            <div class="value">{{ $aktif }}</div>
            <div class="sub">Bisa dipakai untuk jadwal/absensi.</div>
        </div>
        <div class="kpi red">
            <div class="label">Nonaktif</div>
            <div class="value">{{ $nonaktif }}</div>
            <div class="sub">Tidak muncul untuk publik dan tidak bisa login portal.</div>
        </div>
        <div class="kpi blue">
            <div class="label">PIN Diset</div>
            <div class="value">{{ $punyaPin }}</div>
            <div class="sub">Belum diset: {{ $belumPin }}.</div>
        </div>
        <div class="kpi">
            <div class="label">Full Time</div>
            <div class="value">{{ $fullTime }}</div>
            <div class="sub">Durasi kerja 8 jam per shift.</div>
        </div>
        <div class="kpi gray">
            <div class="label">Part Time</div>
            <div class="value">{{ $partTime }}</div>
            <div class="sub">Durasi kerja 4,5 jam per shift.</div>
        </div>
        <div class="kpi blue">
            <div class="label">Profil Siap</div>
            <div class="value">{{ $profilLengkap }}</div>
            <div class="sub">Masih perlu dilengkapi: {{ $profilButuhUpdate }} akun.</div>
        </div>
    </div>
    <div class="panel karyawan-master-panel">
        @if(session('success')) <div class="alert ok">{{ session('success') }}</div> @endif
        @if($errors->any()) <div class="alert err">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div> @endif

        <div class="toolbar">
            <div class="left">
                <input id="karyawan-search" class="search" type="search" placeholder="Cari nama / jabatan / telepon..." autocomplete="off">
            </div>
            <div class="right">
                <button class="chip active" type="button" data-filter="all">Semua</button>
                <button class="chip" type="button" data-filter="aktif">Aktif</button>
                <button class="chip" type="button" data-filter="nonaktif">Nonaktif</button>
                <button class="chip" type="button" data-filter="fulltime">Full Time</button>
                <button class="chip" type="button" data-filter="parttime">Part Time</button>
                <button class="chip" type="button" data-filter="pin">Ada PIN</button>
                <button class="chip" type="button" data-filter="nopin">Belum PIN</button>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Nama &amp; Profil</th><th>Telepon</th><th>Tipe Kerja</th><th>Skema Gaji</th><th>Status</th><th>PIN</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($karyawan as $item)
                    @php
                        $employmentType = method_exists($item, 'employmentTypeValue') ? $item->employmentTypeValue() : \App\Models\Karyawan::EMPLOYMENT_FULL_TIME;
                        $photoUrl = method_exists($item, 'profilePhotoUrl') ? $item->profilePhotoUrl() : null;
                        $jabatan = trim((string) ($item->jabatan ?? ''));
                        $telepon = trim((string) ($item->no_telepon ?? ''));
                        $alamat = trim((string) ($item->alamat ?? ''));
                        $initials = collect(explode(' ', (string) ($item->nama_karyawan ?? '')))
                            ->filter()
                            ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
                            ->take(2)
                            ->implode('');
                        $profilReady = $telepon !== '' && $alamat !== '' && trim((string) ($item->foto_profil_path ?? '')) !== '';
                    @endphp
                    <tr
                        data-row
                        data-name="{{ strtolower((string) ($item->nama_karyawan ?? '')) }}"
                        data-jabatan="{{ strtolower((string) ($item->jabatan ?? '')) }}"
                        data-telepon="{{ strtolower((string) ($item->no_telepon ?? '')) }}"
                        data-employment="{{ $employmentType }}"
                        data-salary="{{ strtolower((string) (method_exists($item, 'salarySchemeLabel') ? $item->salarySchemeLabel() : 'Bulanan')) }}"
                        data-active="{{ (int) ($item->is_active ?? 1) === 1 ? '1' : '0' }}"
                        data-pin="{{ !empty($item->pin_digest) ? '1' : '0' }}"
                    >
                        <td>{{ $item->id_karyawan }}</td>
                        <td class="karyawan-person-cell">
                            <div class="karyawan-person">
                                <div class="karyawan-avatar">
                                    @if($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="{{ $item->nama_karyawan }}">
                                    @else
                                        <span>{{ $initials !== '' ? $initials : 'NA' }}</span>
                                    @endif
                                </div>
                                <div class="karyawan-person-main">
                                    <div class="karyawan-person-name">{{ $item->nama_karyawan }}</div>
                                    <div class="karyawan-person-role">{{ $jabatan !== '' ? $jabatan : 'Jabatan belum diisi' }}</div>
                                    <div class="karyawan-person-tags">
                                        @if($profilReady)
                                            <span class="karyawan-mini-tag ok">Profil lengkap</span>
                                        @else
                                            <span class="karyawan-mini-tag warn">Perlu dilengkapi</span>
                                        @endif
                                        <span class="karyawan-mini-tag">{{ $photoUrl ? 'Ada foto' : 'Belum ada foto' }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="karyawan-contact">{{ $telepon !== '' ? $telepon : 'Belum diisi' }}</div>
                            <div class="pin-help u-mt-2">{{ $alamat !== '' ? \Illuminate\Support\Str::limit($alamat, 40) : 'Alamat belum diisi' }}</div>
                        </td>
                        <td>
                            <span class="pill gray">{{ method_exists($item, 'employmentTypeLabel') ? $item->employmentTypeLabel() : 'Full Time' }}</span>
                            <div class="pin-help u-mt-2">{{ method_exists($item, 'employmentDurationLabel') ? $item->employmentDurationLabel() : '8 jam' }}</div>
                        </td>
                        <td>
                            <span class="pill neu">{{ method_exists($item, 'salarySchemeLabel') ? $item->salarySchemeLabel() : 'Bulanan' }}</span>
                            <div class="pin-help u-mt-2">{{ method_exists($item, 'baseSalaryLabel') ? $item->baseSalaryLabel() : 'Rp 0 / bulan' }}</div>
                        </td>
                        <td>
                            @if((int) ($item->is_active ?? 1) === 1)
                                <span class="pill ok">Aktif</span>
                            @else
                                <span class="pill off">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($item->pin_digest))
                                <div class="pin-toggle" data-pinwrap data-id="{{ (int) $item->id_karyawan }}">
                                    <button class="pill ok" type="button" data-pinbtn>Ada</button>
                                </div>
                            @else
                                <span class="pill neu">Belum</span>
                            @endif
                        </td>
                        <td>
                            <div class="aksi">
                                @if((int) ($item->is_active ?? 1) === 1)
                                    <form class="inline" method="post" action="{{ route('karyawan.active.update', $item) }}" onsubmit="return confirm('Nonaktifkan karyawan ini?')">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_active" value="0">
                                        <button class="btn-neutral btn-soft-red" type="submit">Nonaktifkan</button>
                                    </form>
                                @else
                                    <form class="inline" method="post" action="{{ route('karyawan.active.update', $item) }}" onsubmit="return confirm('Aktifkan kembali karyawan ini?')">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="is_active" value="1">
                                        <button class="btn-neutral btn-soft-green" type="submit">Aktifkan</button>
                                    </form>
                                @endif
                                <a class="btn-primary" href="{{ route('karyawan.show', $item) }}">Profil</a>
                                <a class="btn-neutral" href="{{ route('karyawan.edit', $item) }}">Edit</a>
                                <form class="inline" method="post" action="{{ route('karyawan.destroy', $item) }}" onsubmit="return confirm('Hapus karyawan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn-danger" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">Belum ada data karyawan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="karyawanModal" aria-hidden="true" data-open="{{ old('nama_karyawan') ? '1' : '0' }}">
    <div class="modal-card" role="dialog" aria-modal="true" aria-label="Tambah Karyawan">
        <div class="modal-head">
            <div>
                <div class="modal-kicker">Profil & Akses</div>
                <h3 class="modal-title">Tambah Karyawan</h3>
                <p class="modal-sub">Tambahkan data petugas kasir dan staf operasional.</p>
            </div>
            <button class="icon-btn" type="button" data-karyawan-close aria-label="Tutup popup tambah karyawan">×</button>
        </div>
        <div class="modal-badge-row">
            <span class="modal-badge ok">Full Time • 8 jam</span>
            <span class="modal-badge blue">Part Time • 4,5 jam</span>
            <span class="modal-badge neutral">PIN wajib untuk portal staf</span>
        </div>
        <form method="post" action="{{ route('karyawan.store') }}" class="pin-grid">
            @csrf
            <div class="modal-section full">
                <div class="modal-section-head">
                    <div>
                        <h4>Identitas Dasar</h4>
                        <p>Isi nama, peran kerja, dan nomor yang akan dipakai untuk kontak portal staf.</p>
                    </div>
                </div>
                <div class="modal-section-grid">
                    <div class="field full">
                        <label>Nama Karyawan</label>
                        <input type="text" name="nama_karyawan" value="{{ old('nama_karyawan') }}" required>
                    </div>
                    <div class="field">
                        <label>Jabatan</label>
                        <input type="text" name="jabatan" value="{{ old('jabatan') }}" placeholder="Kasir / Barista / Kitchen">
                    </div>
                    <div class="field">
                        <label>No Telepon</label>
                        <input type="text" name="no_telepon" value="{{ old('no_telepon') }}" placeholder="08xxxxxxxxxx">
                        <div class="pin-help">Disarankan isi angka saja supaya login portal lebih mudah.</div>
                    </div>
                </div>
            </div>
            <div class="modal-section">
                <div class="modal-section-head">
                    <div>
                        <h4>Skema Kerja</h4>
                        <p>Pilih tipe kerja lalu isi nominal gaji yang sesuai.</p>
                    </div>
                </div>
                <div class="modal-section-grid single">
                    <div class="field">
                        <label>Tipe Kerja</label>
                        <select name="employment_type" required>
                            @foreach(\App\Models\Karyawan::employmentTypeOptions() as $value => $option)
                                <option value="{{ $value }}" @selected(old('employment_type', \App\Models\Karyawan::EMPLOYMENT_FULL_TIME) === $value)>
                                    {{ $option['label'] }} ({{ $option['duration_label'] }})
                                </option>
                            @endforeach
                        </select>
                        <div class="pin-help">Full Time bekerja 8 jam per shift, Part Time 4,5 jam per shift.</div>
                    </div>
                    <div class="field">
                        <label>Gaji Bulanan</label>
                        <input type="number" min="0" step="1000" name="monthly_salary" value="{{ old('monthly_salary') }}" placeholder="Contoh: 2800000">
                        <div class="pin-help">Isi untuk Full Time. Dipakai sebagai gaji tetap per bulan.</div>
                    </div>
                    <div class="field">
                        <label>Tarif per Jam</label>
                        <input type="number" min="0" step="500" name="hourly_rate" value="{{ old('hourly_rate') }}" placeholder="Contoh: 20000">
                        <div class="pin-help">Isi untuk Part Time. Slip dihitung dari jam kerja.</div>
                    </div>
                </div>
            </div>
            <div class="modal-section">
                <div class="modal-section-head">
                    <div>
                        <h4>Akses Portal</h4>
                        <p>PIN dipakai untuk login portal staf dan absensi harian.</p>
                    </div>
                </div>
                <div class="modal-section-grid single">
                    <div class="field">
                        <label>PIN Portal (4-8 angka)</label>
                        <input type="password" inputmode="numeric" name="pin" value="{{ old('pin') }}" placeholder="4-8 angka" required>
                        <div class="pin-help">PIN wajib. Digunakan untuk login portal dan absensi.</div>
                    </div>
                    <div class="field">
                        <label>Status</label>
                        <div class="checkline">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1') === '1')>
                            <div>
                                <div class="check-title">Karyawan Aktif</div>
                                <div class="pin-help u-mt-2">Jika nonaktif, tidak muncul di absensi/jadwal publik dan tidak bisa login portal.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pin-actions full">
                <button class="btn-primary" type="submit">Simpan</button>
                <button class="btn-neutral" type="button" id="cancelKaryawanModal" data-karyawan-close>Batal</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function () {
    function byId(id) { return document.getElementById(id); }

    function applyKaryawanFilters() {
        var searchEl = byId('karyawan-search');
        var q = ((searchEl && searchEl.value) ? searchEl.value : '').toLowerCase().trim();

        var activeChip = document.querySelector('.chip.active');
        var activeFilter = (activeChip && activeChip.getAttribute('data-filter')) ? activeChip.getAttribute('data-filter') : 'all';

        var rows = document.querySelectorAll('tr[data-row]');
        for (var i = 0; i < rows.length; i++) {
            var tr = rows[i];
            var name = tr.getAttribute('data-name') || '';
            var jabatan = tr.getAttribute('data-jabatan') || '';
            var telepon = tr.getAttribute('data-telepon') || '';
            var employment = tr.getAttribute('data-employment') || '';
            var isActive = tr.getAttribute('data-active') === '1';
            var hasPin = tr.getAttribute('data-pin') === '1';

            var salary = tr.getAttribute('data-salary') || '';
            var haystack = (name + ' ' + jabatan + ' ' + telepon + ' ' + employment + ' ' + salary);
            var matchText = q === '' || haystack.indexOf(q) !== -1;

            var matchFilter = true;
            if (activeFilter === 'aktif') matchFilter = isActive;
            if (activeFilter === 'nonaktif') matchFilter = !isActive;
            if (activeFilter === 'fulltime') matchFilter = employment === 'full_time';
            if (activeFilter === 'parttime') matchFilter = employment === 'part_time';
            if (activeFilter === 'pin') matchFilter = hasPin;
            if (activeFilter === 'nopin') matchFilter = !hasPin;

            tr.style.display = (matchText && matchFilter) ? '' : 'none';
        }
    }

    function init() {
        var search = byId('karyawan-search');
        if (search && search.addEventListener) {
            search.addEventListener('input', applyKaryawanFilters);
        }

        function copyText(text) {
            if (!text) return;
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function (resolve, reject) {
                try {
                    var tmp = document.createElement('input');
                    tmp.value = text;
                    document.body.appendChild(tmp);
                    tmp.select();
                    document.execCommand('copy');
                    document.body.removeChild(tmp);
                    resolve();
                } catch (e) { reject(e); }
            });
        }

        function hidePin(wrap) {
            if (!wrap) return;
            var btn = wrap.querySelector('[data-pinbtn]');
            if (btn) {
                btn.textContent = 'Ada';
                btn.disabled = false;
                btn.classList.remove('pin-value');
                btn.classList.remove('mid');
                btn.classList.add('ok');
                btn.removeAttribute('data-pinvalue');
                btn.title = '';
            }
            wrap.removeAttribute('data-open');
            if (wrap._pinTimer) {
                clearTimeout(wrap._pinTimer);
                wrap._pinTimer = null;
            }
        }

        function showPin(wrap, pin) {
            if (!wrap) return;
            var btn = wrap.querySelector('[data-pinbtn]');

            hidePin(wrap);

            wrap.setAttribute('data-open', '1');
            if (btn) {
                btn.textContent = String(pin || '');
                btn.classList.add('pin-value');
                btn.classList.remove('mid');
                btn.classList.add('ok');
                btn.setAttribute('data-pinvalue', String(pin || ''));
                btn.title = 'Klik lagi untuk salin PIN';
            }

            wrap._pinTimer = setTimeout(function () {
                hidePin(wrap);
            }, 5000);
        }

        function fetchPin(karyawanId) {
            var url = '/karyawan/' + encodeURIComponent(String(karyawanId)) + '/pin';
            return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                .then(function (r) {
                    var ct = (r.headers && r.headers.get) ? (r.headers.get('content-type') || '') : '';
                    if (ct.indexOf('application/json') === -1) {
                        return { ok: false, data: { ok: false, message: 'Sesi login tidak terbaca. Silakan refresh halaman lalu coba lagi.' } };
                    }
                    return r.json().then(function (j) { return { ok: r.ok, data: j }; });
                });
        }

        var pinWraps = document.querySelectorAll('[data-pinwrap]');
        for (var i = 0; i < pinWraps.length; i++) {
            (function (wrap) {
                var btn = wrap.querySelector('[data-pinbtn]');
                if (!btn) return;

                btn.addEventListener('click', function () {
                    if (wrap.getAttribute('data-open') === '1') {
                        // When PIN is currently shown, clicking again copies it then hides.
                        var pv = btn.getAttribute('data-pinvalue') || '';
                        copyText(pv).then(function () {
                            // no-op; keep UI calm
                        }).catch(function () {});
                        hidePin(wrap);
                        return;
                    }

                    var id = wrap.getAttribute('data-id');
                    if (!id) return;

                    btn.disabled = true;
                    btn.classList.add('is-loading');
                    fetchPin(id).then(function (res) {
                        btn.disabled = false;
                        btn.classList.remove('is-loading');
                        if (!res || !res.data || res.data.ok !== true) {
                            btn.textContent = 'Ada';
                            alert((res && res.data && res.data.message) ? res.data.message : 'PIN tidak bisa ditampilkan.');
                            return;
                        }
                        showPin(wrap, res.data.pin);
                    }).catch(function () {
                        btn.disabled = false;
                        btn.classList.remove('is-loading');
                        btn.textContent = 'Ada';
                        alert('Gagal memuat PIN. Coba lagi.');
                    });
                });
            })(pinWraps[i]);
        }

        var chips = document.querySelectorAll('.chip[data-filter]');
        for (var i = 0; i < chips.length; i++) {
            (function (btn) {
                btn.addEventListener('click', function () {
                    for (var j = 0; j < chips.length; j++) chips[j].classList.remove('active');
                    btn.classList.add('active');
                    applyKaryawanFilters();
                });
            })(chips[i]);
        }

        applyKaryawanFilters();
    }

    const openBtn = document.getElementById('openKaryawanModal');
    const modal = document.getElementById('karyawanModal');
    const closeButtons = document.querySelectorAll('[data-karyawan-close]');
    if (openBtn && modal) {
        const open = () => { modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); };
        const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); };
        openBtn.addEventListener('click', open);
        closeButtons.forEach((button) => button.addEventListener('click', close));
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        if (modal.dataset.open === '1') open();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
@endsection


