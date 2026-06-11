@extends('layouts.app')

@section('title', 'Kasir - Checkout')

@section('content')
@php
    $itemCount = collect($ringkasan)->sum('qty');
    $lineCount = collect($ringkasan)->count();
@endphp
<div class="container">
    <div class="hero flow-hero">
        <div class="flow-hero-head">
            <div>
                <span class="flow-badge">Checkout</span>
                <h1>Checkout Pembayaran</h1>
                <p class="flow-sub">Periksa ringkasan pesanan, pilih metode pembayaran, lalu simpan transaksi dengan alur yang lebih rapi.</p>
            </div>
            <div class="flow-stats">
                <div class="flow-stat">
                    <span>Item</span>
                    <strong>{{ number_format((int) $itemCount, 0, ',', '.') }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Baris pesanan</span>
                    <strong>{{ number_format((int) $lineCount, 0, ',', '.') }}</strong>
                </div>
                <div class="flow-stat">
                    <span>Total sementara</span>
                    <strong>Rp {{ number_format((float) $total, 0, ',', '.') }}</strong>
                </div>
            </div>
        </div>
        <div class="flow-steps">
            <span class="flow-step">1. Pilih Produk</span>
            <span class="flow-step active">2. Checkout & Bayar</span>
            <span class="flow-step">3. Cetak Checker</span>
        </div>
    </div>
    <div class="back-row">
        <a class="btn-primary" href="{{ route(($kasirRoutePrefix ?? 'kasir') . '.index') }}">Kembali Pilih Produk</a>
    </div>

    @if ($errors->any())
        <div class="alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="layout">
        <div class="panel">
            <div class="flow-panel-head">
                <div>
                    <h3 class="section-title">Ringkasan Pesanan</h3>
                    <p class="flow-panel-sub">Pastikan produk, qty, dan subtotal sudah sesuai sebelum pembayaran dikonfirmasi.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="num">Qty</th>
                        <th class="num">Harga</th>
                        <th class="num">Subtotal</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($ringkasan as $row)
                        <tr>
                            <td>
                                <div class="item-name">
                                    <strong>{{ $row['produk']->nama_produk }}</strong>
                                    @if(!empty($row['options_label']))
                                        <span class="item-opt">{{ $row['options_label'] }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="num">{{ $row['qty'] }}</td>
                            <td class="num">{{ number_format((float)$row['harga_satuan'], 0, ',', '.') }}</td>
                            <td class="num">{{ number_format((float)$row['subtotal'], 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="totals">
                <div class="line">
                    <span>Subtotal</span>
                    <strong id="subtotalText">Rp {{ number_format((float)$total, 0, ',', '.') }}</strong>
                </div>
                <div class="line">
                    <span>Diskon</span>
                    <strong id="diskonText">- Rp 0</strong>
                </div>
                <div class="line" id="taxLine" @if(empty($taxEnabled)) hidden @endif>
                    <span>Pajak <span id="taxLabelPercent"></span></span>
                    <strong id="taxText">Rp 0</strong>
                </div>
                <div class="line grand">
                    <span>Total Bayar</span>
                    <span id="totalBayarText">Rp {{ number_format((float)$total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <div class="panel">
            <form id="checkoutForm" method="post" action="{{ route(($kasirRoutePrefix ?? 'kasir') . '.checkout_submit') }}" class="group">
                @csrf

                <div>
                    <div class="flow-panel-head">
                        <div>
                            <h3 class="section-title">Data Pelanggan</h3>
                            <p class="flow-panel-sub">Boleh pilih pelanggan lama, atau isi pelanggan baru jika transaksi ingin langsung disimpan ke database pelanggan.</p>
                        </div>
                    </div>
                    <label>Pelanggan Terdaftar</label>
                    <select name="id_pelanggan">
                        <option value="">Umum / isi pelanggan baru</option>
                        @foreach ($pelanggan as $item)
                            <option value="{{ $item->id_pelanggan }}" @selected(old('id_pelanggan') == $item->id_pelanggan)>{{ $item->nama }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="row3">
                    <div>
                        <label>Nama Pelanggan Baru</label>
                        <input type="text" name="pelanggan_baru_nama" value="{{ old('pelanggan_baru_nama') }}" placeholder="Contoh: Budi">
                    </div>
                    <div>
                        <label>Username IG</label>
                        <input type="text" name="pelanggan_baru_username_ig" value="{{ old('pelanggan_baru_username_ig') }}" placeholder="@username">
                    </div>
                    <div>
                        <label>No Telepon</label>
                        <input type="text" name="pelanggan_baru_no_telepon" value="{{ old('pelanggan_baru_no_telepon') }}" placeholder="08xxxxxxxxxx">
                    </div>
                </div>
                <div class="hint">Jika isi nama pelanggan baru, data akan otomatis tersimpan.</div>

                <div>
                    <div class="flow-panel-head">
                        <div>
                            <h3 class="section-title">Pembayaran</h3>
                            <p class="flow-panel-sub">Pilih diskon, tentukan kasir transaksi, lalu pilih metode pembayaran yang sesuai.</p>
                        </div>
                    </div>
                    @if(!empty($selectedBundling))
                        <div class="discount-note tight">
                            Promo Bundling aktif: <strong>{{ $selectedBundling->nama_promo }}</strong>
                            @if((int) ($selectedBundlingApplies ?? 0) > 0)
                                (x{{ (int) $selectedBundlingApplies }})
                            @endif
                            - Potongan Rp {{ number_format((float) ($selectedBundlingNominal ?? 0), 0, ',', '.') }}
                        </div>
                        <input type="hidden" name="id_promo_bundling" value="{{ (int) $selectedBundling->id_promo_bundling }}">
                    @endif
                    <label>Diskon (Opsional)</label>
                    <select id="selectDiskon" name="id_diskon" @disabled(!empty($selectedBundling))>
                        <option value="">Tanpa diskon</option>
                        @foreach ($diskon as $item)
                            <option
                                value="{{ $item->id_diskon }}"
                                data-name="{{ $item->nama_diskon }}"
                                data-tipe="{{ $item->tipe_diskon }}"
                                data-nilai="{{ (float) $item->nilai_diskon }}"
                                data-minimum="{{ (float) $item->minimal_belanja }}"
                                data-maximum="{{ (float) ($item->maksimal_diskon ?? 0) }}"
                                data-target-category="{{ (int) ($item->id_kategori_target ?? 0) }}"
                                data-target-category-name="{{ (string) ($item->kategoriTarget?->nama_kategori ?? '') }}"
                                data-special-price="{{ (float) ($item->harga_spesial ?? 0) }}"
                                @selected(old('id_diskon') == $item->id_diskon)
                            >
                                {{ $item->nama_diskon }}
                                (
                                @if($item->tipe_diskon === 'persen')
                                    {{ rtrim(rtrim(number_format((float) $item->nilai_diskon, 2, '.', ''), '0'), '.') . '%' }}
                                @elseif($item->tipe_diskon === 'harga_kategori')
                                    Harga spesial: Rp {{ number_format((float) ($item->harga_spesial ?? 0), 0, ',', '.') }}
                                @else
                                    Rp {{ number_format((float) $item->nilai_diskon, 0, ',', '.') }}
                                @endif
                                )
                                @if ((float) $item->minimal_belanja > 0)
                                    - Min. Rp {{ number_format((float) $item->minimal_belanja, 0, ',', '.') }}
                                @endif
                                @if ((int) ($item->id_kategori_target ?? 0) > 0)
                                    - Kategori {{ $item->kategoriTarget?->nama_kategori ?? ('#' . $item->id_kategori_target) }}
                                @endif
                                @if ($item->tipe_diskon === 'persen' && (float) ($item->maksimal_diskon ?? 0) > 0)
                                    - Max. Rp {{ number_format((float) $item->maksimal_diskon, 0, ',', '.') }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @if(!empty($selectedBundling))
                        <div class="hint">Diskon dinonaktifkan karena promo bundling sudah dipilih dari halaman kasir.</div>
                    @endif
                    <div id="diskonHint" class="hint"></div>
                    <div id="diskonNote" class="discount-note" hidden></div>
                    @if ($diskon->isEmpty())
                        <div class="hint">Belum ada diskon aktif. Admin bisa tambah lewat menu Diskon.</div>
                    @endif
                </div>

                @if(empty($isAdminKasir))
                    <div>
                        <label>Kasir</label>
                        <select name="id_karyawan" required>
                            <option value="">Pilih kasir</option>
                            @foreach ($karyawan as $item)
                                <option value="{{ $item->id_karyawan }}" @selected(old('id_karyawan') == $item->id_karyawan)>{{ $item->nama_karyawan }} - {{ $item->jabatan }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label>Kasir</label>
                        <input type="text" value="ADMIN (otomatis)" disabled>
                    </div>
                @endif

                <div>
                    <label>Catatan Pesanan (Opsional)</label>
                    <textarea name="catatan_pesanan" maxlength="255" placeholder="Contoh: tanpa es, saus dipisah, plastik tambahan">{{ old('catatan_pesanan') }}</textarea>
                </div>

                <div>
                    <label>Metode Pembayaran</label>
                    @php
                        $paymentLabels = [
                            'cash' => 'Cash',
                            'qris' => 'QRIS',
                            'debit' => 'Debit',
                            'shopeefood' => 'ShopeeFood',
                            'gofood' => 'GoFood (Gojek)',
                            'grabfood' => 'GrabFood',
                        ];
                        $paymentOptions = collect($enabledPaymentMethods ?? [])->values();
                        $deliveryOptions = collect($enabledDeliveryMethods ?? [])->values();
                        $generalOptions = $paymentOptions->reject(fn ($m) => $deliveryOptions->contains($m))->values();
                        $defaultPaymentMethod = old('metode_pembayaran');
                        if (! $defaultPaymentMethod || ! $paymentOptions->contains($defaultPaymentMethod)) {
                            $defaultPaymentMethod = (string) ($paymentOptions->first() ?? 'cash');
                        }
                        $defaultIsDelivery = $deliveryOptions->contains($defaultPaymentMethod);
                    @endphp
                    <div class="pay">
                        @if($generalOptions->isNotEmpty())
                            <div class="hint u-mb-4">Metode Umum</div>
                            @foreach($generalOptions as $method)
                                <label class="opt">
                                    <input type="radio" name="metode_pembayaran" value="{{ $method }}" {{ $defaultPaymentMethod === $method ? 'checked' : '' }}>
                                    {{ $paymentLabels[$method] ?? strtoupper($method) }}
                                </label>
                            @endforeach
                        @endif
                        @if($deliveryOptions->isNotEmpty())
                            <button type="button" id="deliveryToggle" class="delivery-toggle" aria-expanded="{{ $defaultIsDelivery ? 'true' : 'false' }}">
                                <span>Transaksi Aplikasi</span>
                                <span id="deliveryLabel" class="delivery-label">
                                    {{ $defaultIsDelivery ? ($paymentLabels[$defaultPaymentMethod] ?? strtoupper($defaultPaymentMethod)) : 'Pilih metode' }}
                                </span>
                            </button>
                            <div id="deliveryPanel" class="delivery-panel {{ $defaultIsDelivery ? '' : 'hidden' }}">
                                @foreach($deliveryOptions as $method)
                                    <label class="opt">
                                        <input class="delivery-method" data-label="{{ $paymentLabels[$method] ?? strtoupper($method) }}" type="radio" name="metode_pembayaran" value="{{ $method }}" {{ $defaultPaymentMethod === $method ? 'checked' : '' }}>
                                        {{ $paymentLabels[$method] ?? strtoupper($method) }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @if($generalOptions->isEmpty() && $deliveryOptions->isEmpty())
                            <div class="hint">Belum ada metode pembayaran aktif. Hubungi admin.</div>
                        @endif
                    </div>
                </div>

                <div id="cashWrap" class="cash-wrap">
                    <div>
                        <label>Jumlah Bayar (Cash)</label>
                        <input id="jumlahBayar" type="number" name="jumlah_bayar" min="0" step="1" value="{{ old('jumlah_bayar') }}" placeholder="Masukkan nominal cash">
                    </div>
                <div id="quickCashButtons" class="quick-cash"></div>
                    <div class="cash-line">
                        <span>Kembalian</span>
                        <strong id="kembalianText">Rp 0</strong>
                    </div>
                </div>

                <button id="submitCheckoutBtn" class="btn-primary" type="submit">Bayar & Simpan Transaksi</button>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const discountLines = @json($discountLines ?? []);

        const subtotal = {{ (float) $total }};
        const checkoutForm = document.getElementById('checkoutForm');
        const submitCheckoutBtn = document.getElementById('submitCheckoutBtn');
        const cashWrap = document.getElementById('cashWrap');
        const jumlahBayarInput = document.getElementById('jumlahBayar');
        const kembalianText = document.getElementById('kembalianText');
        const metodeInputs = document.querySelectorAll('input[name="metode_pembayaran"]');
        const deliveryToggle = document.getElementById('deliveryToggle');
        const deliveryPanel = document.getElementById('deliveryPanel');
        const deliveryLabel = document.getElementById('deliveryLabel');
        const quickCashButtons = document.getElementById('quickCashButtons');
        const selectDiskon = document.getElementById('selectDiskon');
        const subtotalText = document.getElementById('subtotalText');
        const diskonText = document.getElementById('diskonText');
        const taxText = document.getElementById('taxText');
        const taxLine = document.getElementById('taxLine');
        const taxLabelPercent = document.getElementById('taxLabelPercent');
        const totalBayarText = document.getElementById('totalBayarText');
        const diskonHint = document.getElementById('diskonHint');
        const diskonNote = document.getElementById('diskonNote');
        const selectedBundlingNominal = @json((float) ($selectedBundlingNominal ?? 0));
        const taxEnabled = @json((bool) ($taxEnabled ?? false));
        const taxPercent = @json((float) ($taxPercent ?? 0));
        const taxMode = @json((string) ($taxMode ?? 'transaksi'));

        const formatRupiah = (angka) => 'Rp ' + new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.max(0, angka));
        const formatNumber = (angka) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.max(0, angka));
        const isCash = () => {
            const active = document.querySelector('input[name="metode_pembayaran"]:checked');
            return active && active.value === 'cash';
        };
        const roundUp = (value, step) => Math.ceil(value / step) * step;

        const updateDiskonAvailability = () => {
            if (!selectDiskon) return;

            let selectedInvalid = false;
            Array.from(selectDiskon.options).forEach((opt, idx) => {
                if (idx === 0) return;
                const minimum = Number(opt.dataset.minimum || 0);
                const targetKategori = Number(opt.dataset.targetCategory || 0);
                const scopeSubtotal = targetKategori > 0
                    ? discountLines.reduce((sum, row) => Number(row.id_kategori || 0) === targetKategori ? sum + (Number(row.harga_satuan || 0) * Number(row.qty || 0)) : sum, 0)
                    : subtotal;
                const allowed = scopeSubtotal >= minimum;
                opt.disabled = !allowed;
                if (!allowed && selectDiskon.value === opt.value) {
                    selectedInvalid = true;
                }
            });

            if (selectedInvalid) {
                selectDiskon.value = '';
                if (diskonNote) {
                    diskonNote.hidden = false;
                    diskonNote.textContent = 'Diskon yang dipilih tidak memenuhi minimal belanja untuk transaksi ini.';
                }
            } else if (diskonNote) {
                diskonNote.hidden = true;
                diskonNote.textContent = '';
            }
        };

        const getDiskonNominal = () => {
            if (selectedBundlingNominal > 0) {
                if (diskonHint) diskonHint.textContent = 'Potongan bundling sudah diterapkan otomatis.';
                return selectedBundlingNominal;
            }

            if (!selectDiskon || !selectDiskon.value) {
                if (diskonHint) diskonHint.textContent = '';
                return 0;
            }

            const selected = selectDiskon.options[selectDiskon.selectedIndex];
            const tipe = selected.dataset.tipe || '';
            const nilai = Number(selected.dataset.nilai || 0);
            const minimum = Number(selected.dataset.minimum || 0);
            const maximum = Number(selected.dataset.maximum || 0);
            const targetKategori = Number(selected.dataset.targetCategory || 0);
            const targetKategoriName = String(selected.dataset.targetCategoryName || '').trim();
            const scopeSubtotal = targetKategori > 0
                ? discountLines.reduce((sum, row) => Number(row.id_kategori || 0) === targetKategori ? sum + (Number(row.harga_satuan || 0) * Number(row.qty || 0)) : sum, 0)
                : subtotal;

            if (scopeSubtotal < minimum) {
                if (diskonHint) {
                    const scopeText = targetKategori > 0
                        ? (' untuk kategori ' + (targetKategoriName || ('#' + targetKategori)))
                        : '';
                    diskonHint.textContent = 'Diskon belum aktif' + scopeText + '. Minimal belanja Rp ' + formatNumber(minimum) + '.';
                }
                return 0;
            }

            if (diskonHint) diskonHint.textContent = '';

            if (tipe === 'persen') {
                const nominal = (scopeSubtotal * nilai) / 100;
                return maximum > 0 ? Math.min(nominal, maximum) : nominal;
            }

            if (tipe === 'harga_kategori') {
                const targetKategori = Number(selected.dataset.targetCategory || 0);
                const specialPrice = Number(selected.dataset.specialPrice || 0);
                if (targetKategori <= 0 || specialPrice <= 0) {
                    return 0;
                }
                return discountLines.reduce((totalNominal, row) => {
                    if (Number(row.id_kategori || 0) !== targetKategori) {
                        return totalNominal;
                    }
                    const qty = Number(row.qty || 0);
                    const hargaSatuan = Number(row.harga_satuan || 0);
                    if (qty <= 0 || hargaSatuan <= 0) {
                        return totalNominal;
                    }
                    const diskonPerItem = Math.max(0, hargaSatuan - specialPrice);
                    return totalNominal + (diskonPerItem * qty);
                }, 0);
            }

            return Math.min(scopeSubtotal, nilai);
        };

        const getTaxNominal = () => {
            if (!taxEnabled || taxPercent <= 0) {
                return 0;
            }
            const discountNominal = getDiskonNominal();
            const base = Math.max(0, subtotal - discountNominal);

            if (taxMode !== 'produk') {
                return (base * taxPercent) / 100;
            }

            const lineSubtotals = discountLines
                .map((row) => Math.max(0, Number(row.harga_satuan || 0) * Number(row.qty || 0)))
                .filter((v) => v > 0);
            const subtotalLines = lineSubtotals.reduce((a, b) => a + b, 0);
            if (subtotalLines <= 0) {
                return 0;
            }

            let remainingDiscount = Math.min(Math.max(0, discountNominal), subtotalLines);
            let taxTotal = 0;
            const totalDiscount = remainingDiscount;

            lineSubtotals.forEach((lineSubtotal, idx) => {
                let lineDiscount = 0;
                if (idx === lineSubtotals.length - 1) {
                    lineDiscount = remainingDiscount;
                } else {
                    lineDiscount = Math.min(remainingDiscount, Math.round((totalDiscount * (lineSubtotal / subtotalLines)) * 100) / 100);
                }
                remainingDiscount = Math.max(0, remainingDiscount - lineDiscount);
                const lineBase = Math.max(0, lineSubtotal - lineDiscount);
                taxTotal += Math.round(((lineBase * taxPercent) / 100) * 100) / 100;
            });

            return taxTotal;
        };

        const getTotalBayar = () => {
            const diskonNominal = getDiskonNominal();
            const base = Math.max(0, subtotal - diskonNominal);
            return base + getTaxNominal();
        };

        const getSelectedDiskonSnapshot = () => {
            if (!selectDiskon || !selectDiskon.value) {
                return {
                    nominal: null,
                    nama: null,
                    tipe: null,
                    nilai: null,
                };
            }

            const selected = selectDiskon.options[selectDiskon.selectedIndex];
            const nominal = getDiskonNominal();
            const tipe = selected.dataset.tipe || null;
            const nilaiRaw = selected.dataset.nilai;

            return {
                nominal,
                nama: selected.dataset.name || selected.text || null,
                tipe,
                nilai: nilaiRaw === undefined || nilaiRaw === null || nilaiRaw === '' ? null : Number(nilaiRaw),
            };
        };

        const updateTotalDisplay = () => {
            const diskonNominal = getDiskonNominal();
            const taxNominal = getTaxNominal();
            const totalBayar = Math.max(0, subtotal - diskonNominal) + taxNominal;

            subtotalText.textContent = formatRupiah(subtotal);
            diskonText.textContent = '- ' + formatRupiah(diskonNominal);
            if (taxText) {
                taxText.textContent = formatRupiah(taxNominal);
            }
            if (taxLine) {
                taxLine.hidden = !(taxEnabled && taxPercent > 0);
            }
            if (taxLabelPercent) {
                const modeText = taxMode === 'produk' ? ' per produk' : ' per transaksi';
                taxLabelPercent.textContent = (taxEnabled && taxPercent > 0) ? `(${taxPercent}%${modeText})` : '';
            }
            totalBayarText.textContent = formatRupiah(totalBayar);
            renderQuickCash(totalBayar);
        };

        const hitungKembalian = () => {
            const bayar = Number(jumlahBayarInput.value || 0);
            kembalianText.textContent = formatRupiah(bayar - getTotalBayar());
        };

        const syncCashState = () => {
            const cash = isCash();
            cashWrap.hidden = !cash;
            jumlahBayarInput.required = cash;
            if (!cash) {
                jumlahBayarInput.value = '';
                kembalianText.textContent = formatRupiah(0);
                return;
            }
            hitungKembalian();
        };

        const renderQuickCash = (totalBayar) => {
            if (!quickCashButtons) return;
            const base = Math.max(0, Math.ceil(totalBayar));
            const suggestions = [
                base,
                roundUp(base, 5000),
                roundUp(base, 10000),
                100000,
            ]
                .filter((v) => v > 0)
                .filter((v, idx, arr) => arr.indexOf(v) === idx)
                .sort((a, b) => a - b)
                .slice(0, 4);

            quickCashButtons.innerHTML = '';
            suggestions.forEach((value) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.cash = String(value);
                btn.textContent = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);
                btn.addEventListener('click', () => {
                    jumlahBayarInput.value = String(value);
                    hitungKembalian();
                    jumlahBayarInput.focus();
                });
                quickCashButtons.appendChild(btn);
            });
        };

        const syncDeliveryState = (forceOpen = false) => {
            if (!deliveryPanel) return;
            const active = document.querySelector('input[name="metode_pembayaran"]:checked');
            const isDelivery = active && active.classList.contains('delivery-method');
            if (forceOpen) deliveryPanel.classList.remove('hidden');
            if (deliveryToggle) {
                deliveryToggle.setAttribute('aria-expanded', (!deliveryPanel.classList.contains('hidden')).toString());
            }
            if (deliveryLabel) {
                const deliveryActive = document.querySelector('input.delivery-method:checked');
                deliveryLabel.textContent = deliveryActive ? (deliveryActive.dataset.label || deliveryActive.value) : 'Pilih metode';
            }
        };

        metodeInputs.forEach((el) => el.addEventListener('change', () => {
            syncCashState();
            const active = document.querySelector('input[name="metode_pembayaran"]:checked');
            const isDelivery = active && active.classList.contains('delivery-method');
            syncDeliveryState(Boolean(isDelivery));
        }));
        if (deliveryToggle) {
            deliveryToggle.addEventListener('click', () => {
                if (!deliveryPanel) return;
                const willOpen = deliveryPanel.classList.contains('hidden');
                deliveryPanel.classList.toggle('hidden');
                syncDeliveryState(willOpen);
            });
        }
        jumlahBayarInput.addEventListener('input', hitungKembalian);
        if (selectDiskon) {
            selectDiskon.addEventListener('change', () => {
                updateTotalDisplay();
                syncCashState();
            });
        }

        checkoutForm.addEventListener('submit', (event) => {
            if (!navigator.onLine) {
                event.preventDefault();
                window.alert('Koneksi internet diperlukan untuk transaksi. Pastikan kasir online sebelum melanjutkan.');
            }
        });

        updateDiskonAvailability();
        updateTotalDisplay();
        syncCashState();
        syncDeliveryState();
    })();
</script>
@endsection

