@extends('layouts.app')

@section('title', 'Ambil Jadwal Mandiri')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Jadwal</div>
            <h1>Ambil Jadwal Mandiri</h1>
            <p>Atur pembukaan jadwal mandiri dengan struktur umum, Full Time, dan Part Time.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ route('dashboard.jadwal.index') }}">Kembali ke Jadwal</a>
        </div>
    </div>

    @php
        $enabled = (bool) ($setting->self_schedule_enabled ?? false);
        $open = $enabled && (bool) ($setting->self_schedule_is_open ?? false);
        $statusClass = $enabled ? ($open ? 'ok' : 'warn') : '';
        $statusText = $enabled ? ($open ? 'DIBUKA' : 'DITUTUP') : 'NONAKTIF';
        $pickStart = old('self_schedule_pick_start_date', $setting->self_schedule_pick_start_date ? $setting->self_schedule_pick_start_date->format('Y-m-d') : '');
        $pickEnd = old('self_schedule_pick_end_date', $setting->self_schedule_pick_end_date ? $setting->self_schedule_pick_end_date->format('Y-m-d') : '');
        $openStart = old('self_schedule_open_start_date', $setting->self_schedule_open_start_date ? $setting->self_schedule_open_start_date->format('Y-m-d') : '');
        $openEnd = old('self_schedule_open_end_date', $setting->self_schedule_open_end_date ? $setting->self_schedule_open_end_date->format('Y-m-d') : '');
        $fullTimeWeekLimit = old('self_schedule_max_per_week', (int) ($setting->self_schedule_max_per_week ?? 0)) ?: 'Tanpa batas';
        $fullTimeMonthLimit = old('self_schedule_max_per_month', (int) ($setting->self_schedule_max_per_month ?? 0)) ?: 'Tanpa batas';
        $partTimeWeekLimit = old('self_schedule_part_time_max_per_week', (int) ($setting->self_schedule_part_time_max_per_week ?? 0)) ?: 'Ikut FT';
        $partTimeMonthLimit = old('self_schedule_part_time_max_per_month', (int) ($setting->self_schedule_part_time_max_per_month ?? 0)) ?: 'Ikut FT';
        $periodLabel = ($pickStart && $pickEnd) ? ($pickStart . ' s/d ' . $pickEnd) : 'Belum diatur';
        $registerWindowLabel = ($openStart && $openEnd) ? ($openStart . ' s/d ' . $openEnd) : 'Mengikuti status buka/tutup';
    @endphp

    <div class="schedule-settings-overview">
        <div class="schedule-overview-card is-status">
            <span class="schedule-overview-label">Status</span>
            <strong>{{ $statusText }}</strong>
            <small>{{ $enabled ? 'Fitur siap dipakai karyawan.' : 'Menu staf disembunyikan sampai diaktifkan.' }}</small>
        </div>
        <div class="schedule-overview-card">
            <span class="schedule-overview-label">Periode Jadwal</span>
            <strong>{{ $periodLabel }}</strong>
            <small>Rentang tanggal yang bisa dipilih staf.</small>
        </div>
        <div class="schedule-overview-card is-ft">
            <span class="schedule-overview-label">Full Time</span>
            <strong>3 Shift • 8 Jam</strong>
            <small>Maks. minggu: {{ $fullTimeWeekLimit }} • Bulan: {{ $fullTimeMonthLimit }}</small>
        </div>
        <div class="schedule-overview-card is-pt">
            <span class="schedule-overview-label">Part Time</span>
            <strong>3 Slot • 4,5 Jam</strong>
            <small>Maks. minggu: {{ $partTimeWeekLimit }} • Bulan: {{ $partTimeMonthLimit }}</small>
        </div>
    </div>

    <div class="panel krs-panel">
        <div class="krs-head">
            <div>
                <h2 class="krs-title">Pengaturan Jadwal Mandiri</h2>
                <div class="krs-desc">Sekarang pengaturannya dipisah supaya admin lebih cepat paham: mulai dari aturan umum, lanjut aturan Full Time, lalu slot Part Time.</div>
            </div>
            <span class="krs-status {{ $statusClass }}">{{ $statusText }}</span>
        </div>

        @if (session('success'))
            <div class="alert ok u-mt-12">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert err u-mt-12">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        <form method="post" action="{{ route('dashboard.jadwal.self_schedule.update') }}" class="krs-form">
            @csrf
            <input type="hidden" name="return_bulan" value="{{ old('return_bulan', $returnBulan ?? '') }}">
            <input type="hidden" name="return_mode" value="{{ old('return_mode', $returnMode ?? '') }}">

            <section class="krs-section">
                <div class="krs-section-head">
                    <div>
                        <div class="krs-section-kicker">Langkah 1</div>
                        <h3 class="krs-section-title">Aturan Umum</h3>
                        <p class="krs-section-desc">Bagian ini mengatur apakah fitur aktif, kapan staf boleh mengisi jadwal, dan seberapa lama periode jadwal dibuka.</p>
                    </div>
                    <div class="krs-section-badges">
                        <span class="krs-mini-badge {{ $enabled ? 'ok' : '' }}">{{ $enabled ? 'Fitur Aktif' : 'Fitur Mati' }}</span>
                        <span class="krs-mini-badge {{ $open ? 'ok' : 'warn' }}">{{ $open ? 'Pendaftaran Buka' : 'Pendaftaran Tutup' }}</span>
                    </div>
                </div>

                <div class="krs-grid2">
                    <div class="krs-card">
                        <div>
                            <div class="h">Aktifkan Fitur</div>
                            <div class="p">Tampilkan menu Ambil Jadwal di portal staf.</div>
                        </div>
                        <label class="krs-toggle" aria-label="Aktifkan fitur ambil jadwal">
                            <input id="self_schedule_enabled" type="checkbox" name="self_schedule_enabled" value="1" @checked(old('self_schedule_enabled', $setting->self_schedule_enabled ?? false))>
                            <span class="knob"></span>
                            <span class="state off">OFF</span>
                            <span class="state on">ON</span>
                        </label>
                    </div>
                    <div class="krs-card">
                        <div>
                            <div class="h">Buka Pendaftaran</div>
                            <div class="p">Staf bisa mengambil jadwal jika periode aktif dan fitur dinyalakan.</div>
                        </div>
                        <label class="krs-toggle" aria-label="Buka pendaftaran ambil jadwal">
                            <input id="self_schedule_is_open" type="checkbox" name="self_schedule_is_open" value="1" @checked(old('self_schedule_is_open', $setting->self_schedule_is_open ?? false))>
                            <span class="knob"></span>
                            <span class="state off">OFF</span>
                            <span class="state on">ON</span>
                        </label>
                    </div>
                </div>

                <div class="schedule-mini-grid">
                    <div class="schedule-mini-card">
                        <span class="schedule-mini-label">Periode Jadwal</span>
                        <strong>{{ $periodLabel }}</strong>
                        <small>Tanggal kerja yang bisa dipilih staf.</small>
                    </div>
                    <div class="schedule-mini-card">
                        <span class="schedule-mini-label">Periode Pendaftaran</span>
                        <strong>{{ $registerWindowLabel }}</strong>
                        <small>Kalau kosong, mengikuti tombol buka/tutup.</small>
                    </div>
                    <div class="schedule-mini-card">
                        <span class="schedule-mini-label">Preset Cepat</span>
                        <strong>7 hari / 14 hari</strong>
                        <small>Pakai kalau admin mau isi rentang jadwal lebih cepat.</small>
                    </div>
                </div>

                <div class="krs-grid2">
                    <div class="krs-field">
                        <label for="self_schedule_pick_start_date">Periode Jadwal Mulai</label>
                        <input id="self_schedule_pick_start_date" type="date" name="self_schedule_pick_start_date" value="{{ $pickStart }}">
                    </div>
                    <div class="krs-field">
                        <label for="self_schedule_pick_end_date">Periode Jadwal Selesai</label>
                        <input id="self_schedule_pick_end_date" type="date" name="self_schedule_pick_end_date" value="{{ $pickEnd }}">
                    </div>
                </div>

                <div class="krs-grid2">
                    <div class="krs-field">
                        <label for="self_schedule_open_start_date">Periode Pendaftaran Mulai (opsional)</label>
                        <input id="self_schedule_open_start_date" type="date" name="self_schedule_open_start_date" value="{{ $openStart }}">
                    </div>
                    <div class="krs-field">
                        <label for="self_schedule_open_end_date">Periode Pendaftaran Selesai (opsional)</label>
                        <input id="self_schedule_open_end_date" type="date" name="self_schedule_open_end_date" value="{{ $openEnd }}">
                        <div class="sub">Kalau diisi, staf hanya bisa ambil jadwal saat jendela pendaftaran ini aktif.</div>
                    </div>
                </div>

                <div class="krs-grid2">
                    <div class="krs-field">
                        <label for="self_schedule_pick_preset">Preset Periode</label>
                        <select id="self_schedule_pick_preset" name="self_schedule_pick_preset">
                            <option value="">(Manual)</option>
                            <option value="next_7_days">Buka 7 hari ke depan</option>
                            <option value="next_14_days">Buka 14 hari ke depan</option>
                        </select>
                        <div class="sub">Preset ini mengisi periode jadwal otomatis saat tombol simpan ditekan.</div>
                    </div>
                    <div class="krs-guide-card">
                        <div class="krs-guide-title">Cara baca halaman ini</div>
                        <ul class="krs-guide-list">
                            <li><strong>Aturan Umum</strong> untuk membuka/tutup fitur dan menentukan periode.</li>
                            <li><strong>Full Time</strong> pakai shift biasa S1, S2, S3.</li>
                            <li><strong>Part Time</strong> pakai slot PT-1, PT-2, PT-3 dengan jam khusus.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="krs-section krs-section-ft">
                <div class="krs-section-head">
                    <div>
                        <div class="krs-section-kicker">Langkah 2</div>
                        <h3 class="krs-section-title">Pengaturan Full Time</h3>
                        <p class="krs-section-desc">Semua aturan di bawah ini hanya untuk karyawan Full Time: kuota shift biasa, kuota weekend, dan batas ambil jadwal.</p>
                    </div>
                    <span class="krs-section-pill ft">FT • Shift Reguler</span>
                </div>

                <div class="slot-preview-grid">
                    @foreach(($fullTimeSlotCards ?? []) as $slot)
                        <div class="slot-preview-card is-ft">
                            <div class="slot-preview-top">
                                <span class="slot-preview-code">{{ $slot['code'] }}</span>
                                <span class="slot-preview-duration">{{ $slot['duration'] }}</span>
                            </div>
                            <h4>{{ $slot['title'] }}</h4>
                            <div class="slot-preview-time">{{ $slot['range'] }}</div>
                            <div class="slot-preview-meta">
                                <span>Kuota biasa: {{ $slot['weekday_capacity'] }}</span>
                                <span>Weekend: {{ $slot['weekend_capacity'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="krs-subsection">
                    <div class="krs-hint">Kuota Shift Full Time</div>
                    <div class="krs-grid3">
                        <div class="krs-field">
                            <label for="self_schedule_capacity_shift1">Kuota Shift 1</label>
                            <input id="self_schedule_capacity_shift1" type="number" min="1" step="1" name="self_schedule_capacity_shift1" value="{{ old('self_schedule_capacity_shift1', (int) ($setting->self_schedule_capacity_shift1 ?? 1)) }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_capacity_shift2">Kuota Shift 2</label>
                            <input id="self_schedule_capacity_shift2" type="number" min="1" step="1" name="self_schedule_capacity_shift2" value="{{ old('self_schedule_capacity_shift2', (int) ($setting->self_schedule_capacity_shift2 ?? 1)) }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_capacity_shift3">Kuota Shift 3</label>
                            <input id="self_schedule_capacity_shift3" type="number" min="1" step="1" name="self_schedule_capacity_shift3" value="{{ old('self_schedule_capacity_shift3', (int) ($setting->self_schedule_capacity_shift3 ?? 1)) }}">
                        </div>
                    </div>
                </div>

                <div class="krs-subsection">
                    <div class="krs-hint">Kuota Weekend Full Time</div>
                    <div class="krs-grid3">
                        <div class="krs-field">
                            <label for="self_schedule_capacity_weekend_shift1">Weekend Shift 1</label>
                            <input id="self_schedule_capacity_weekend_shift1" type="number" min="1" step="1" name="self_schedule_capacity_weekend_shift1" placeholder="Kosong = kuota normal" value="{{ old('self_schedule_capacity_weekend_shift1', $setting->self_schedule_capacity_weekend_shift1 ? (int) $setting->self_schedule_capacity_weekend_shift1 : '') }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_capacity_weekend_shift2">Weekend Shift 2</label>
                            <input id="self_schedule_capacity_weekend_shift2" type="number" min="1" step="1" name="self_schedule_capacity_weekend_shift2" placeholder="Kosong = kuota normal" value="{{ old('self_schedule_capacity_weekend_shift2', $setting->self_schedule_capacity_weekend_shift2 ? (int) $setting->self_schedule_capacity_weekend_shift2 : '') }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_capacity_weekend_shift3">Weekend Shift 3</label>
                            <input id="self_schedule_capacity_weekend_shift3" type="number" min="1" step="1" name="self_schedule_capacity_weekend_shift3" placeholder="Kosong = kuota normal" value="{{ old('self_schedule_capacity_weekend_shift3', $setting->self_schedule_capacity_weekend_shift3 ? (int) $setting->self_schedule_capacity_weekend_shift3 : '') }}">
                        </div>
                    </div>
                </div>

                <div class="krs-subsection">
                    <div class="krs-hint">Limit Ambil Jadwal Full Time</div>
                    <div class="krs-grid2">
                        <div class="krs-field">
                            <label for="self_schedule_max_per_week">Maksimal per Minggu</label>
                            <input id="self_schedule_max_per_week" type="number" min="0" step="1" name="self_schedule_max_per_week" placeholder="Kosong = tanpa batas" value="{{ old('self_schedule_max_per_week', (int) ($setting->self_schedule_max_per_week ?? 0)) ?: '' }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_max_per_month">Maksimal per Bulan</label>
                            <input id="self_schedule_max_per_month" type="number" min="0" step="1" name="self_schedule_max_per_month" placeholder="Kosong = tanpa batas" value="{{ old('self_schedule_max_per_month', (int) ($setting->self_schedule_max_per_month ?? 0)) ?: '' }}">
                        </div>
                    </div>
                    <div class="krs-grid2">
                        <div class="krs-field">
                            <label for="self_schedule_min_per_week">Minimal per Minggu</label>
                            <input id="self_schedule_min_per_week" type="number" min="0" step="1" name="self_schedule_min_per_week" placeholder="Kosong = tidak dipakai" value="{{ old('self_schedule_min_per_week', (int) ($setting->self_schedule_min_per_week ?? 0)) ?: '' }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_min_per_month">Minimal per Bulan</label>
                            <input id="self_schedule_min_per_month" type="number" min="0" step="1" name="self_schedule_min_per_month" placeholder="Kosong = tidak dipakai" value="{{ old('self_schedule_min_per_month', (int) ($setting->self_schedule_min_per_month ?? 0)) ?: '' }}">
                        </div>
                    </div>
                </div>
            </section>

            <section class="krs-section krs-section-pt">
                <div class="krs-section-head">
                    <div>
                        <div class="krs-section-kicker">Langkah 3</div>
                        <h3 class="krs-section-title">Pengaturan Part Time</h3>
                        <p class="krs-section-desc">Bagian ini khusus untuk Part Time: jam mulai slot PT, kuota per slot, kuota weekend, dan limit ambil jadwal terpisah.</p>
                    </div>
                    <span class="krs-section-pill pt">PT • Slot 4,5 Jam</span>
                </div>

                <div class="slot-preview-grid">
                    @foreach(($partTimeSlotCards ?? []) as $index => $slot)
                        @php $slotNo = $index + 1; @endphp
                        <div class="slot-preview-card is-pt">
                            <div class="slot-preview-top">
                                <span class="slot-preview-code">{{ $slot['code'] }}</span>
                                <span class="slot-preview-duration">{{ $slot['duration'] }}</span>
                            </div>
                            <h4>{{ $slot['title'] }}</h4>
                            <div class="slot-preview-time" data-slot-preview="pt-{{ $slotNo }}">{{ $slot['range'] }}</div>
                            <div class="slot-preview-meta">
                                <span>Kuota biasa: {{ $slot['weekday_capacity'] }}</span>
                                <span>Weekend: {{ $slot['weekend_capacity'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="krs-subsection">
                    <div class="krs-hint">Jam Slot Part Time</div>
                    <div class="krs-grid3">
                        <div class="krs-field">
                            <label for="part_time_shift1_start_time">Mulai PT-1</label>
                            <input id="part_time_shift1_start_time" type="time" name="part_time_shift1_start_time" value="{{ old('part_time_shift1_start_time', $setting->part_time_shift1_start_time ?? '07:00') }}" data-pt-start="1">
                            <div class="sub">Durasi otomatis 4,5 jam.</div>
                        </div>
                        <div class="krs-field">
                            <label for="part_time_shift2_start_time">Mulai PT-2</label>
                            <input id="part_time_shift2_start_time" type="time" name="part_time_shift2_start_time" value="{{ old('part_time_shift2_start_time', $setting->part_time_shift2_start_time ?? '11:30') }}" data-pt-start="2">
                            <div class="sub">Cocok untuk slot tengah operasional.</div>
                        </div>
                        <div class="krs-field">
                            <label for="part_time_shift3_start_time">Mulai PT-3</label>
                            <input id="part_time_shift3_start_time" type="time" name="part_time_shift3_start_time" value="{{ old('part_time_shift3_start_time', $setting->part_time_shift3_start_time ?? '16:00') }}" data-pt-start="3">
                            <div class="sub">Gunakan kalau toko butuh slot part time sore/malam.</div>
                        </div>
                    </div>
                </div>

                <div class="krs-subsection">
                    <div class="krs-hint">Kuota Slot Part Time</div>
                    <div class="krs-grid3">
                        <div class="krs-field">
                            <label for="self_schedule_part_time_capacity_shift1">Kuota PT-1</label>
                            <input id="self_schedule_part_time_capacity_shift1" type="number" min="1" step="1" name="self_schedule_part_time_capacity_shift1" placeholder="Kosong = ikut kuota FT" value="{{ old('self_schedule_part_time_capacity_shift1', $setting->self_schedule_part_time_capacity_shift1 ? (int) $setting->self_schedule_part_time_capacity_shift1 : '') }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_part_time_capacity_shift2">Kuota PT-2</label>
                            <input id="self_schedule_part_time_capacity_shift2" type="number" min="1" step="1" name="self_schedule_part_time_capacity_shift2" placeholder="Kosong = ikut kuota FT" value="{{ old('self_schedule_part_time_capacity_shift2', $setting->self_schedule_part_time_capacity_shift2 ? (int) $setting->self_schedule_part_time_capacity_shift2 : '') }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_part_time_capacity_shift3">Kuota PT-3</label>
                            <input id="self_schedule_part_time_capacity_shift3" type="number" min="1" step="1" name="self_schedule_part_time_capacity_shift3" placeholder="Kosong = ikut kuota FT" value="{{ old('self_schedule_part_time_capacity_shift3', $setting->self_schedule_part_time_capacity_shift3 ? (int) $setting->self_schedule_part_time_capacity_shift3 : '') }}">
                        </div>
                    </div>
                </div>

                <div class="krs-subsection">
                    <div class="krs-hint">Kuota Weekend Part Time</div>
                    <div class="krs-grid3">
                        <div class="krs-field">
                            <label for="self_schedule_part_time_capacity_weekend_shift1">Weekend PT-1</label>
                            <input id="self_schedule_part_time_capacity_weekend_shift1" type="number" min="1" step="1" name="self_schedule_part_time_capacity_weekend_shift1" placeholder="Kosong = ikut kuota PT normal" value="{{ old('self_schedule_part_time_capacity_weekend_shift1', $setting->self_schedule_part_time_capacity_weekend_shift1 ? (int) $setting->self_schedule_part_time_capacity_weekend_shift1 : '') }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_part_time_capacity_weekend_shift2">Weekend PT-2</label>
                            <input id="self_schedule_part_time_capacity_weekend_shift2" type="number" min="1" step="1" name="self_schedule_part_time_capacity_weekend_shift2" placeholder="Kosong = ikut kuota PT normal" value="{{ old('self_schedule_part_time_capacity_weekend_shift2', $setting->self_schedule_part_time_capacity_weekend_shift2 ? (int) $setting->self_schedule_part_time_capacity_weekend_shift2 : '') }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_part_time_capacity_weekend_shift3">Weekend PT-3</label>
                            <input id="self_schedule_part_time_capacity_weekend_shift3" type="number" min="1" step="1" name="self_schedule_part_time_capacity_weekend_shift3" placeholder="Kosong = ikut kuota PT normal" value="{{ old('self_schedule_part_time_capacity_weekend_shift3', $setting->self_schedule_part_time_capacity_weekend_shift3 ? (int) $setting->self_schedule_part_time_capacity_weekend_shift3 : '') }}">
                        </div>
                    </div>
                </div>

                <div class="krs-subsection">
                    <div class="krs-hint">Limit Ambil Jadwal Part Time</div>
                    <div class="krs-grid2">
                        <div class="krs-field">
                            <label for="self_schedule_part_time_max_per_week">Maksimal PT per Minggu</label>
                            <input id="self_schedule_part_time_max_per_week" type="number" min="0" step="1" name="self_schedule_part_time_max_per_week" placeholder="Kosong = ikut aturan FT" value="{{ old('self_schedule_part_time_max_per_week', (int) ($setting->self_schedule_part_time_max_per_week ?? 0)) ?: '' }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_part_time_max_per_month">Maksimal PT per Bulan</label>
                            <input id="self_schedule_part_time_max_per_month" type="number" min="0" step="1" name="self_schedule_part_time_max_per_month" placeholder="Kosong = ikut aturan FT" value="{{ old('self_schedule_part_time_max_per_month', (int) ($setting->self_schedule_part_time_max_per_month ?? 0)) ?: '' }}">
                        </div>
                    </div>
                    <div class="krs-grid2">
                        <div class="krs-field">
                            <label for="self_schedule_part_time_min_per_week">Minimal PT per Minggu</label>
                            <input id="self_schedule_part_time_min_per_week" type="number" min="0" step="1" name="self_schedule_part_time_min_per_week" placeholder="Kosong = tidak dipakai" value="{{ old('self_schedule_part_time_min_per_week', (int) ($setting->self_schedule_part_time_min_per_week ?? 0)) ?: '' }}">
                        </div>
                        <div class="krs-field">
                            <label for="self_schedule_part_time_min_per_month">Minimal PT per Bulan</label>
                            <input id="self_schedule_part_time_min_per_month" type="number" min="0" step="1" name="self_schedule_part_time_min_per_month" placeholder="Kosong = tidak dipakai" value="{{ old('self_schedule_part_time_min_per_month', (int) ($setting->self_schedule_part_time_min_per_month ?? 0)) ?: '' }}">
                        </div>
                    </div>
                </div>
            </section>

            <section class="krs-section">
                <div class="krs-section-head">
                    <div>
                        <div class="krs-section-kicker">Langkah 4</div>
                        <h3 class="krs-section-title">Pembatalan & Penyesuaian</h3>
                        <p class="krs-section-desc">Bagian terakhir ini mengatur apakah staf boleh membatalkan jadwal dan berapa minimal H- untuk pembatalan atau tukar jadwal.</p>
                    </div>
                </div>

                <div class="krs-grid2">
                    <div class="krs-card">
                        <div>
                            <div class="h">Izinkan Batal Jadwal</div>
                            <div class="p">Kalau aktif, staf boleh membatalkan jadwal yang masih akan datang.</div>
                        </div>
                        <label class="krs-toggle" aria-label="Izinkan pembatalan jadwal">
                            <input id="self_schedule_allow_cancel" type="checkbox" name="self_schedule_allow_cancel" value="1" @checked(old('self_schedule_allow_cancel', $setting->self_schedule_allow_cancel ?? false))>
                            <span class="knob"></span>
                            <span class="state off">OFF</span>
                            <span class="state on">ON</span>
                        </label>
                    </div>
                    <div class="krs-field">
                        <label for="self_schedule_cancel_min_days_before">Minimal H- (hari) untuk Batal</label>
                        <input id="self_schedule_cancel_min_days_before" type="number" min="0" step="1" name="self_schedule_cancel_min_days_before" value="{{ old('self_schedule_cancel_min_days_before', (int) ($setting->self_schedule_cancel_min_days_before ?? 0)) }}">
                        <div class="sub">Contoh: isi <strong>1</strong> jika pembatalan hanya boleh dilakukan minimal H-1. Aturan ini juga dipakai untuk tukar jadwal.</div>
                    </div>
                </div>
            </section>

            <div class="krs-actions">
                <button class="btn-primary" type="submit">Simpan Pengaturan</button>
                <a class="btn-neutral" href="{{ route('dashboard.jadwal.index', array_filter(['bulan' => ($returnBulan ?? null), 'mode' => ($returnMode ?? null)])) }}">Kembali ke Jadwal</a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const durationMinutes = 270;
        const updatePreview = (slotNo, value) => {
            const preview = document.querySelector(`[data-slot-preview="pt-${slotNo}"]`);
            if (!preview || !value) return;
            const parts = value.split(':');
            if (parts.length !== 2) return;

            const start = new Date();
            start.setHours(Number(parts[0]), Number(parts[1]), 0, 0);

            const end = new Date(start.getTime() + durationMinutes * 60 * 1000);
            const pad = (number) => String(number).padStart(2, '0');
            preview.textContent = `${pad(start.getHours())}:${pad(start.getMinutes())} - ${pad(end.getHours())}:${pad(end.getMinutes())}`;
        };

        document.querySelectorAll('[data-pt-start]').forEach((input) => {
            const slotNo = input.getAttribute('data-pt-start');
            const sync = () => updatePreview(slotNo, input.value);
            input.addEventListener('change', sync);
            input.addEventListener('input', sync);
            sync();
        });
    });
</script>
@endsection
