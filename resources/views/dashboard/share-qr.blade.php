@extends('layouts.app')

@section('title', 'Share QR')

@section('content')
<div class="container">
    <div class="admin-page-head">
        <div>
            <div class="admin-page-label">Akses Staf</div>
            <h1>Share QR</h1>
            <p>Tempel QR ini di ruang staf untuk akses portal, absen, dan jadwal.</p>
        </div>
        <div class="admin-page-actions">
            <a class="btn-neutral" href="{{ url()->previous() }}">Kembali</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert err u-mt-14">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @php
        $qrPortalPreview = route('dashboard.share_qr.preview', ['kind' => 'portal', 'size' => 240]);
    @endphp
    <div class="admin-grid-secondary">
        <div class="admin-soft-card portal">
            <div class="admin-soft-card-head">
                <div>
                    <div class="title">QR Portal Karyawan</div>
                    <div class="meta u-mt-6">Portal karyawan (butuh login PIN). Dari portal, karyawan bisa absen atau lihat jadwal.</div>
                </div>
                <span class="tag portal">/staff</span>
            </div>
                <div class="qr">
                    <div class="qr-frame">
                        <img alt="QR Portal Karyawan" src="{{ $qrPortalPreview }}">
                    </div>
                </div>
            <div class="linkline">
                <span class="linkpill">Link</span>
                <span class="linktext">{{ $portalUrl }}</span>
            </div>
            <div id="copied-portal" class="copied">Link portal tercopy.</div>
            <div class="btn-row">
                <a class="btn-primary" href="{{ route('dashboard.share_qr.download', ['kind' => 'portal']) }}">Unduh PNG</a>
                <button class="btn-neutral" type="button" onclick="copyLink(@json($portalUrl), 'copied-portal')">Copy</button>
            </div>
        </div>
    </div>

    <div class="hint">
        <span class="dot"></span>
        <div>Catatan: unduh PNG membutuhkan internet (QR dibuat dari layanan QR online).</div>
    </div>
</div>

<div id="copy-modal" class="copy-modal" aria-hidden="true">
    <div class="copy-card" role="dialog" aria-modal="true" aria-label="Salin Link">
        <div class="copy-title">Salin Link</div>
        <div class="copy-sub">Jika tombol Copy tidak bisa menyalin otomatis (biasanya karena bukan HTTPS), link di bawah akan otomatis terseleksi. Tekan lama lalu pilih <b>Salin</b>.</div>
        <div class="copy-field">
            <input id="copy-input" class="copy-input" readonly value="">
        </div>
        <div class="copy-actions">
            <button class="btn-neutral" type="button" id="copy-close">Tutup</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
 function copyLink(text, statusId) {
    const value = String(text || '');
    const show = () => {
        const el = document.getElementById(statusId);
        if (!el) return;
        el.classList.add('show');
        window.setTimeout(() => el.classList.remove('show'), 1600);
    };

    const execFallback = () => {
        try {
            const inp = document.createElement('input');
            inp.type = 'text';
            inp.value = value;
            inp.style.position = 'fixed';
            inp.style.left = '-9999px';
            inp.style.top = '0';
            inp.style.opacity = '0';
            inp.style.pointerEvents = 'none';
            document.body.appendChild(inp);
            inp.focus({ preventScroll: true });
            inp.select();
            inp.setSelectionRange(0, value.length);
            const ok = document.execCommand('copy');
            document.body.removeChild(inp);
            return !!ok;
        } catch (e) {
            return false;
        }
    };

    const openManual = () => {
        const modal = document.getElementById('copy-modal');
        const input = document.getElementById('copy-input');
        const closeBtn = document.getElementById('copy-close');
        if (!modal || !input || !closeBtn) {
            window.prompt('Copy link:', value);
            return;
        }
        input.value = value;
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        // Auto-select for long-press copy on mobile.
        setTimeout(() => {
            try {
                input.focus({ preventScroll: true });
                input.select();
                input.setSelectionRange(0, value.length);
            } catch (e) {}
        }, 50);

        const close = () => {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        };
        closeBtn.onclick = close;
        modal.onclick = (e) => { if (e.target === modal) close(); };
        document.onkeydown = (e) => { if (e.key === 'Escape') close(); };
    };

    if (!value) {
        window.alert('Link kosong.');
        return;
    }

    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
        navigator.clipboard.writeText(value).then(show).catch(() => {
            if (execFallback()) return show();
            openManual();
        });
        return;
    }

    if (execFallback()) {
        show();
        return;
    }

    openManual();
 }

</script>
@endsection

