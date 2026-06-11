@extends('layouts.app')

@section('title', 'Ruang Kerja Admin')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <h1>Ruang Kerja Admin</h1>
        <p>Pengaturan persiapan operasional sebelum kasir digunakan.</p>
    </div>

    @if ($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="workspace-grid">
        <div class="panel">
            <h2 class="panel-title">Setelan Operasional</h2>
            <p class="panel-desc">Atur jam reset harian dan metode pembayaran yang diizinkan di kasir.</p>

            <form method="post" action="{{ route('dashboard.workspace.update') }}" class="form-grid">
                @csrf
                <div class="field-card">
                    <div class="mini-title">Operasional Harian</div>
                    <label for="operasional_reset_hour">Jam Reset Operasional</label>
                    <select id="operasional_reset_hour" name="operasional_reset_hour">
                        @for($h = 0; $h <= 23; $h++)
                            <option value="{{ $h }}" @selected((int) old('operasional_reset_hour', $setting->operasional_reset_hour ?? 3) === $h)>
                                {{ str_pad((string) $h, 2, '0', STR_PAD_LEFT) }}:00
                            </option>
                        @endfor
                    </select>
                    <div class="hint">Semua filter "hari ini/kemarin" akan mengikuti jam ini.</div>
                </div>

                <div class="field-card">
                    <div class="mini-title">Sesi Shift</div>
                    <label for="active_shift_count">Jumlah Shift Aktif per Hari</label>
                    <select id="active_shift_count" name="active_shift_count">
                        @for($i = 1; $i <= 3; $i++)
                            <option value="{{ $i }}" @selected((int) old('active_shift_count', $setting->active_shift_count ?? 2) === $i)>
                                {{ $i }} Shift
                            </option>
                        @endfor
                    </select>
                    <div class="hint">Kasir hanya bisa memilih Shift 1 s/d Shift {{ (int) old('active_shift_count', $setting->active_shift_count ?? 2) }}.</div>
                </div>

                <div class="field-card">
                    <div class="mini-title">Modal Kas Sistem</div>
                    <label for="default_cash_float">Nominal Kas Awal Tetap (Rp)</label>
                    <input
                        id="default_cash_float"
                        type="number"
                        name="default_cash_float"
                        min="0"
                        step="1"
                        value="{{ old('default_cash_float', (int) round((float) ($setting->default_cash_float ?? 0))) }}"
                        placeholder="Contoh: 200000"
                    >
                    <div class="hint">Nilai ini dipakai sebagai kas awal saat kasir mulai shift. Bisa diubah kapan saja (misal 200.000 jadi 300.000).</div>
                </div>

                <div class="field-card">
                    <div class="mini-title">Siklus Setoran</div>
                    <div class="setoran-grid">
                        <div class="setoran-subcard">
                            <p class="subhead">Interval Setoran</p>
                            <label for="setoran_interval_days">Setoran Uang Tiap Berapa Hari</label>
                            <select id="setoran_interval_days" name="setoran_interval_days">
                                @for($d = 1; $d <= 30; $d++)
                                    <option value="{{ $d }}" @selected((int) old('setoran_interval_days', $setting->setoran_interval_days ?? 7) === $d)>
                                        {{ $d }} Hari
                                    </option>
                                @endfor
                            </select>
                            <div class="hint">Contoh: 7 hari berarti setoran dilakukan seminggu sekali.</div>
                        </div>
                        <div class="setoran-subcard">
                            <p class="subhead">Menu Keuangan</p>
                            <div class="toggle-inline">
                                <label for="enable_keuangan_menu">Aktifkan</label>
                                <input id="enable_keuangan_menu" type="checkbox" name="enable_keuangan_menu" value="1" @checked(old('enable_keuangan_menu', $setting->enable_keuangan_menu ?? true))>
                            </div>
                            <div class="hint">Jika nonaktif, menu Keuangan disembunyikan dan fitur setoran tidak dipakai.</div>
                        </div>
                    </div>
                </div>

                <div class="field-card">
                    <div class="mini-title">Pembayaran Dan Pajak</div>
                    <label>Pengaturan Pembayaran & Pajak</label>
                    <div class="switch-list">
                        <div class="switch-item">
                            <div>
                                <label for="enable_tax">Pajak Otomatis</label>
                                <div class="switch-help">Aktifkan pajak untuk setiap transaksi kasir.</div>
                                <div class="switch-help">Jika aktif, pajak ditanggung customer (ditambahkan ke total bayar).</div>
                            </div>
                            <input id="enable_tax" type="checkbox" name="enable_tax" value="1" @checked(old('enable_tax', $setting->enable_tax ?? false))>
                        </div>
                        <div class="split-2">
                            <div>
                                <label for="tax_percent">Persen Pajak (%)</label>
                                <input id="tax_percent" type="number" name="tax_percent" min="0" max="100" step="0.01" value="{{ old('tax_percent', number_format((float) ($setting->tax_percent ?? 0), 2, '.', '')) }}">
                                <div class="hint">Contoh isi 10 untuk pajak 10%.</div>
                            </div>
                            <div>
                                <label for="tax_mode">Mode Pajak</label>
                                <select id="tax_mode" name="tax_mode">
                                    <option value="transaksi" @selected(old('tax_mode', $setting->tax_mode ?? 'transaksi') === 'transaksi')>Per Transaksi</option>
                                    <option value="produk" @selected(old('tax_mode', $setting->tax_mode ?? 'transaksi') === 'produk')>Per Produk</option>
                                </select>
                                <div class="hint">Per transaksi: dari total akhir. Per produk: hitung per item.</div>
                            </div>
                        </div>
                        <div class="switch-item">
                            <div>
                                <label for="enable_payment_cash">Cash</label>
                                <div class="switch-help">Pembayaran tunai di checkout.</div>
                            </div>
                            <input id="enable_payment_cash" type="checkbox" name="enable_payment_cash" value="1" @checked(old('enable_payment_cash', $setting->enable_payment_cash ?? true))>
                        </div>
                        <div class="switch-item">
                            <div>
                                <label for="enable_payment_qris">QRIS</label>
                                <div class="switch-help">Pembayaran QRIS di checkout.</div>
                            </div>
                            <input id="enable_payment_qris" type="checkbox" name="enable_payment_qris" value="1" @checked(old('enable_payment_qris', $setting->enable_payment_qris ?? true))>
                        </div>
                        <div class="switch-item">
                            <div>
                                <label for="enable_payment_debit">Debit</label>
                                <div class="switch-help">Pembayaran EDC/debit di checkout.</div>
                            </div>
                            <input id="enable_payment_debit" type="checkbox" name="enable_payment_debit" value="1" @checked(old('enable_payment_debit', $setting->enable_payment_debit ?? true))>
                        </div>
                        <div class="switch-item">
                            <div>
                                <label for="enable_payment_shopeefood">ShopeeFood</label>
                                <div class="switch-help">Pembayaran via marketplace delivery.</div>
                            </div>
                            <input id="enable_payment_shopeefood" type="checkbox" name="enable_payment_shopeefood" value="1" @checked(old('enable_payment_shopeefood', $setting->enable_payment_shopeefood ?? false))>
                        </div>
                        <div class="switch-item">
                            <div>
                                <label for="enable_payment_gofood">GoFood (Gojek)</label>
                                <div class="switch-help">Pembayaran dari platform Gojek.</div>
                            </div>
                            <input id="enable_payment_gofood" type="checkbox" name="enable_payment_gofood" value="1" @checked(old('enable_payment_gofood', $setting->enable_payment_gofood ?? false))>
                        </div>
                        <div class="switch-item">
                            <div>
                                <label for="enable_payment_grabfood">GrabFood</label>
                                <div class="switch-help">Pembayaran dari platform Grab.</div>
                            </div>
                            <input id="enable_payment_grabfood" type="checkbox" name="enable_payment_grabfood" value="1" @checked(old('enable_payment_grabfood', $setting->enable_payment_grabfood ?? false))>
                        </div>
                    </div>
                    <div class="hint">Minimal satu metode pembayaran harus aktif.</div>
                </div>

                <div class="save-wrap">
                    <button class="btn-primary" type="submit">Simpan Ruang Kerja</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h2 class="panel-title">Checklist Buka Shift</h2>
            <p class="panel-desc">Panduan cepat sebelum operasional dimulai.</p>
            <div class="right-note">
                <div class="item">1. Pastikan metode pembayaran yang tersedia hari ini sudah diaktifkan.</div>
                <div class="item">2. Cek jam reset operasional sesuai SOP toko.</div>
                <div class="item">3. Lakukan test transaksi kecil untuk verifikasi printer struk/checker.</div>
                <div class="item">4. Pastikan stok awal produk sudah sesuai sebelum kasir buka.</div>
            </div>

            <div class="u-mt-14">
                <h3 class="panel-title panel-title-sm">Setelan Tersimpan Saat Ini</h3>
                <p class="panel-desc panel-desc-tight">Gunakan tabel ini sebagai acuan sebelum melakukan perubahan.</p>
                <div class="summary-table-wrap">
                    <table class="summary-table">
                        <tr>
                            <th>Jam Reset Operasional</th>
                            <td>{{ str_pad((string) ((int) ($setting->operasional_reset_hour ?? 3)), 2, '0', STR_PAD_LEFT) }}:00</td>
                        </tr>
                        <tr>
                            <th>Jumlah Shift Aktif</th>
                            <td>{{ (int) ($setting->active_shift_count ?? 2) }} Shift</td>
                        </tr>
                        <tr>
                            <th>Modal Kas Sistem</th>
                            <td>Rp {{ number_format((float) ($setting->default_cash_float ?? 0), 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <th>Interval Setoran</th>
                            <td>{{ (int) ($setting->setoran_interval_days ?? 7) }} Hari</td>
                        </tr>
                        <tr>
                            <th>Menu Keuangan</th>
                            <td>
                                @if((bool) ($setting->enable_keuangan_menu ?? true))
                                    <span class="pill-yes">Aktif</span>
                                @else
                                    <span class="pill-no">Nonaktif</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Pajak Otomatis</th>
                            <td>
                                @if((bool) ($setting->enable_tax ?? false))
                                    <span class="pill-yes">Aktif</span>
                                    ({{ ($setting->tax_mode ?? 'transaksi') === 'produk' ? 'Per Produk' : 'Per Transaksi' }})
                                @else
                                    <span class="pill-no">Nonaktif</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Persen Pajak Saat Ini</th>
                            <td>{{ number_format((float) ($setting->tax_percent ?? 0), 2, ',', '.') }}%</td>
                        </tr>
                        <tr>
                            <th>Mode Pajak Saat Ini</th>
                            <td>{{ ($setting->tax_mode ?? 'transaksi') === 'produk' ? 'Per Produk' : 'Per Transaksi' }}</td>
                        </tr>
                        <tr>
                            <th>Metode Pembayaran</th>
                            <td>
                                Cash: {{ (bool) ($setting->enable_payment_cash ?? true) ? 'Aktif' : 'Nonaktif' }} |
                                QRIS: {{ (bool) ($setting->enable_payment_qris ?? true) ? 'Aktif' : 'Nonaktif' }} |
                                Debit: {{ (bool) ($setting->enable_payment_debit ?? true) ? 'Aktif' : 'Nonaktif' }} |
                                ShopeeFood: {{ (bool) ($setting->enable_payment_shopeefood ?? false) ? 'Aktif' : 'Nonaktif' }} |
                                GoFood: {{ (bool) ($setting->enable_payment_gofood ?? false) ? 'Aktif' : 'Nonaktif' }} |
                                GrabFood: {{ (bool) ($setting->enable_payment_grabfood ?? false) ? 'Aktif' : 'Nonaktif' }}
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection



