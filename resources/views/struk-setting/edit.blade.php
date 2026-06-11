@extends('layouts.app')

@section('title', 'Pengaturan Struk')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Master Data</div>
            <h1>Pengaturan Struk</h1>
            <p>Atur area atas dan bawah struk. Bisa isi teks bebas dan logo cafe.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert ok">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
    @endif

    <div class="layout">
        <div class="panel form-panel">
            <form method="post" action="{{ route('struk_setting.update') }}" enctype="multipart/form-data" class="form-grid">
                @csrf
                @method('PUT')

                <div class="section-title">Identitas Utama</div>
                <div class="full">
                    <label>Nama Toko</label>
                    <input type="text" name="nama_toko" required value="{{ old('nama_toko', $setting->nama_toko) }}">
                </div>

                <div class="row2">
                    <div>
                        <label>Nama Cabang (opsional)</label>
                        <input type="text" name="nama_cabang" value="{{ old('nama_cabang', $setting->nama_cabang) }}" placeholder="Contoh: Cabang Kota Lama">
                    </div>
                    <div>
                        <label>Alamat Toko (1 baris)</label>
                        <input type="text" name="alamat_toko" value="{{ old('alamat_toko', $setting->alamat_toko) }}" placeholder="Contoh: Jl. Merdeka No. 10">
                    </div>
                </div>

                <div class="section-title">Template Cepat (Opsional)</div>
                <div class="full template-box">
                    <div class="template-title">Template Atas</div>
                    <div class="row3">
                        <div>
                            <label>Website</label>
                            <input type="text" id="tpl_website" placeholder="kopisop.id">
                        </div>
                        <div>
                            <label>Email</label>
                            <input type="text" id="tpl_email" placeholder="halo@kopisop.id">
                        </div>
                        <div>
                            <label>Nomor Telepon</label>
                            <input type="text" id="tpl_phone" placeholder="08xxxx">
                        </div>
                    </div>
                    <span class="muted">Info ini akan tampil di bagian atas struk.</span>
                    <div class="row3">
                        <div>
                            <label>Jam Buka (mulai)</label>
                            <input type="time" id="tpl_jam_start">
                        </div>
                        <div>
                            <label>Jam Buka (selesai)</label>
                            <input type="time" id="tpl_jam_end">
                        </div>
                        <div>
                            <label>Hari Operasional</label>
                            <input type="text" id="tpl_hari" placeholder="Setiap hari / Senin-Sabtu">
                        </div>
                    </div>
                </div>
                <div class="full template-box">
                    <div class="template-title">Template Bawah</div>
                    <div class="row2">
                        <div>
                            <label>Instagram</label>
                            <input type="text" id="tpl_instagram" placeholder="@kopisop">
                        </div>
                        <div>
                            <label>Password WiFi</label>
                            <input type="text" id="tpl_wifi" placeholder="kopisop123">
                        </div>
                    </div>
                    <div class="row2">
                        <div>
                            <label>Ucapan Terima Kasih</label>
                            <input type="text" id="tpl_thanks" placeholder="Terima kasih sudah datang">
                        </div>
                        <div>
                            <label>Catatan Tambahan</label>
                            <input type="text" id="tpl_note" placeholder="Simpan struk untuk klaim promo">
                        </div>
                    </div>
                    <div class="actions">
                        <button class="btn-primary" type="button" id="applyTemplate">Gunakan Template</button>
                        <span class="muted">Klik untuk mengisi teks atas &amp; bawah otomatis.</span>
                    </div>
                </div>

                <div class="full">
                    <label>Teks Atas Tambahan</label>
                    <div class="hint">Cocok untuk jam buka, Instagram, website, email, atau info singkat lainnya.</div>
                    <textarea name="header_text" id="header_text" rows="5" placeholder="Contoh: Buka setiap hari 08:00 - 22:00&#10;Instagram: @kopisop">{{ old('header_text', $setting->header_text) }}</textarea>
                </div>

                <div class="full">
                    <label>Teks Bawah Struk</label>
                    <div class="hint">Cocok untuk ucapan terima kasih, info WiFi, atau pesan penutup.</div>
                    <textarea name="footer_text" id="footer_text" rows="5" placeholder="Contoh: Password WiFi: kopisop123&#10;Terima kasih sudah datang">{{ old('footer_text', $setting->footer_text) }}</textarea>
                </div>

                <div class="full">
                    <label class="check">
                        <input id="show_logo" type="checkbox" name="show_logo" value="1" @checked(old('show_logo', $setting->show_logo) == 1)>
                        <span>Tampilkan logo di struk</span>
                    </label>
                </div>
                <div>
                    <label>Ukuran Maksimum Lebar Logo (px)</label>
                    <input type="number" min="60" max="220" name="logo_max_width" value="{{ old('logo_max_width', $setting->logo_max_width ?? 120) }}" placeholder="120">
                </div>
                <div>
                    <label>Upload Logo Baru (JPG/PNG, max 2MB)</label>
                    <input type="file" name="logo" accept="image/*">
                    <div class="hint u-mt-6">Jika upload logo baru, logo sebelumnya otomatis diganti.</div>
                </div>

                <div class="section-title">Tampilkan Metadata di Struk</div>
                <div class="full admin-grid-secondary-check">
                    <label class="check"><input type="checkbox" name="show_kode_nota" value="1" @checked(old('show_kode_nota', $setting->show_kode_nota ?? true))> <span>Kode Nota</span></label>
                    <label class="check"><input type="checkbox" name="show_id_pesanan" value="1" @checked(old('show_id_pesanan', $setting->show_id_pesanan ?? true))> <span>ID Pesanan</span></label>
                    <label class="check"><input type="checkbox" name="show_waktu" value="1" @checked(old('show_waktu', $setting->show_waktu ?? true))> <span>Waktu</span></label>
                    <label class="check"><input type="checkbox" name="show_pelanggan" value="1" @checked(old('show_pelanggan', $setting->show_pelanggan ?? true))> <span>Pelanggan</span></label>
                    <label class="check"><input type="checkbox" name="show_kasir" value="1" @checked(old('show_kasir', $setting->show_kasir ?? true))> <span>Kasir</span></label>
                    <label class="check"><input type="checkbox" name="show_metode" value="1" @checked(old('show_metode', $setting->show_metode ?? true))> <span>Metode Bayar</span></label>
                    <label class="check"><input type="checkbox" name="show_status" value="1" @checked(old('show_status', $setting->show_status ?? true))> <span>Status</span></label>
                </div>

                <div class="section-title">Checker Dapur/Bar</div>
                <div class="full">
                    <label class="check">
                        <input id="auto_print_checker" type="checkbox" name="auto_print_checker" value="1" @checked(old('auto_print_checker', $setting->auto_print_checker ?? true))>
                        <span>Auto print checker setelah checkout berhasil</span>
                    </label>
                    <div class="hint u-mt-6">Jika nonaktif, checker tetap bisa dicetak manual dari halaman receipt.</div>
                </div>

                <div class="actions full">
                    <button class="btn-primary" type="submit">Simpan Pengaturan</button>
                </div>
            </form>
        </div>

        <div class="panel preview">
            @if(!empty($setting->logo_path) && $setting->show_logo)
                <img class="preview-logo" src="{{ asset('storage/' . $setting->logo_path) }}" alt="Logo Struk" style="max-width:{{ max(60, min(220, (int) ($setting->logo_max_width ?? 120))) }}px;">
            @endif
            <h3>{{ $setting->nama_toko ?: 'KOPISOP' }}</h3>
            @if(!empty($setting->nama_cabang))
                <div class="u-text-center u-text-sm">{{ $setting->nama_cabang }}</div>
            @endif
            @if(!empty($setting->alamat_toko))
                <div class="u-text-center u-text-sm">{{ $setting->alamat_toko }}</div>
            @endif
            @if(!empty($setting->header_text))
                <div class="u-mt-6 u-text-center u-text-sm u-pre-line">{{ $setting->header_text }}</div>
            @endif
            <div class="line"></div>
            <div class="muted u-text-center">Preview area tengah item transaksi</div>
            <div class="line"></div>
            @if(!empty($setting->footer_text))
                <div class="u-text-center u-text-sm u-pre-line">{{ $setting->footer_text }}</div>
            @else
                <div class="u-text-center u-text-sm">Terima kasih telah berkunjung</div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(() => {
    const applyBtn = document.getElementById('applyTemplate');
    const header = document.getElementById('header_text');
    const footer = document.getElementById('footer_text');
    if (!applyBtn || !header || !footer) return;
    applyBtn.addEventListener('click', () => {
        const web = document.getElementById('tpl_website')?.value?.trim();
        const email = document.getElementById('tpl_email')?.value?.trim();
        const phone = document.getElementById('tpl_phone')?.value?.trim();
        const ig = document.getElementById('tpl_instagram')?.value?.trim();
        const wifi = document.getElementById('tpl_wifi')?.value?.trim();
        const thanks = document.getElementById('tpl_thanks')?.value?.trim();
        const note = document.getElementById('tpl_note')?.value?.trim();
        const jamStart = document.getElementById('tpl_jam_start')?.value?.trim();
        const jamEnd = document.getElementById('tpl_jam_end')?.value?.trim();
        const hari = document.getElementById('tpl_hari')?.value?.trim();

        const headerLines = [];
        if (web) headerLines.push(`Website: ${web}`);
        if (email) headerLines.push(`Email: ${email}`);
        if (phone) headerLines.push(`Telp: ${phone}`);
        if (jamStart || jamEnd || hari) {
            const jamRange = jamStart && jamEnd ? `${jamStart} - ${jamEnd}` : (jamStart || jamEnd);
            const jamLine = [
                hari ? `${hari}` : null,
                jamRange ? `Buka: ${jamRange}` : null
            ].filter(Boolean).join(' | ');
            if (jamLine) headerLines.push(jamLine);
        }

        const footerLines = [];
        if (ig) footerLines.push(`Instagram: ${ig}`);
        if (wifi) footerLines.push(`WiFi: ${wifi}`);
        if (thanks) footerLines.push(thanks);
        if (note) footerLines.push(note);

        header.value = headerLines.join('\n');
        footer.value = footerLines.join('\n');
    });
})();
</script>
@endsection
