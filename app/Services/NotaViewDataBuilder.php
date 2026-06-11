<?php

namespace App\Services;

use App\Models\Pesanan;
use App\Models\StrukSetting;

class NotaViewDataBuilder
{
    public function build(Pesanan $transaksi, StrukSetting $setting, ?string $viewerRole = null): array
    {
        $viewerRole = in_array($viewerRole, ['admin', 'kasir'], true) ? $viewerRole : 'kasir';
        $branding = $this->buildBranding($setting, $viewerRole);
        $items = $this->buildItems($transaksi);

        return [
            'branding' => $branding,
            'visibility' => [
                'show_kode_nota' => (bool) ($setting->show_kode_nota ?? true),
                'show_id_pesanan' => (bool) ($setting->show_id_pesanan ?? true),
                'show_waktu' => (bool) ($setting->show_waktu ?? true),
                'show_pelanggan' => (bool) ($setting->show_pelanggan ?? true),
                'show_kasir' => (bool) ($setting->show_kasir ?? true),
                'show_metode' => (bool) ($setting->show_metode ?? true),
                'show_status' => (bool) ($setting->show_status ?? true),
            ],
            'meta' => [
                'public_order_id' => $this->buildPublicOrderId($transaksi),
                'waktu' => (string) ($transaksi->waktu_pembayaran ?? '-'),
                'pelanggan' => $this->resolvePelangganLabel($transaksi),
                'kasir' => $this->resolveKasirLabel($transaksi),
                'metode' => strtoupper((string) ($transaksi->metode_pembayaran ?? '-')),
                'status' => strtoupper((string) ($transaksi->status_pembayaran ?? '-')),
                'catatan' => trim((string) ($transaksi->catatan_pesanan ?? '')),
            ],
            'items' => $items,
            'totals' => $this->buildTotals($transaksi),
            'promo' => [
                'description' => $this->buildPromoDescription($transaksi, $items),
            ],
        ];
    }

    private function buildBranding(StrukSetting $setting, string $viewerRole): array
    {
        $modeTemplate = (string) ($setting->mode_template ?? 'global');
        $pakaiTemplateRole = $modeTemplate === 'per_role' && in_array($viewerRole, ['admin', 'kasir'], true);
        $suffixRole = $pakaiTemplateRole ? '_' . $viewerRole : '';

        $namaTokoRole = trim((string) data_get($setting, 'nama_toko' . $suffixRole, ''));
        $alamatTokoRole = trim((string) data_get($setting, 'alamat_toko' . $suffixRole, ''));
        $headerTextRole = trim((string) data_get($setting, 'header_text' . $suffixRole, ''));
        $footerTextRole = trim((string) data_get($setting, 'footer_text' . $suffixRole, ''));

        return [
            'nama_toko' => $namaTokoRole !== '' ? $namaTokoRole : trim((string) ($setting->nama_toko ?? 'KOPISOP')),
            'alamat_toko' => $alamatTokoRole !== '' ? $alamatTokoRole : trim((string) ($setting->alamat_toko ?? '')),
            'header_text' => $headerTextRole !== '' ? $headerTextRole : trim((string) ($setting->header_text ?? '')),
            'footer_text' => $footerTextRole !== '' ? $footerTextRole : trim((string) ($setting->footer_text ?? '')),
            'nama_cabang' => trim((string) ($setting->nama_cabang ?? '')),
            'logo_path' => (bool) ($setting->show_logo ?? false) ? trim((string) ($setting->logo_path ?? '')) : '',
            'logo_max_width' => max(60, min(220, (int) ($setting->logo_max_width ?? 120))),
        ];
    }

    private function buildItems(Pesanan $transaksi): array
    {
        $items = [];

        foreach ($transaksi->detail as $detail) {
            $produk = $detail->produk;
            $namaProduk = trim((string) ($produk?->nama_produk ?? ''));
            $qty = max(0, (int) ($detail->jumlah ?? 0));
            $hargaSatuan = (float) ($detail->harga_satuan ?? 0);

            $items[] = [
                'name' => $namaProduk !== '' ? $namaProduk : 'Produk dihapus',
                'options_label' => $this->buildOptionsLabel($detail),
                'qty' => $qty,
                'unit_price' => $hargaSatuan,
                'subtotal' => $hargaSatuan * $qty,
                'category_id' => (int) ($produk?->id_kategori ?? 0),
            ];
        }

        return $items;
    }

    private function buildTotals(Pesanan $transaksi): array
    {
        $subtotalHarga = $transaksi->subtotal_harga !== null
            ? (float) $transaksi->subtotal_harga
            : (float) $transaksi->total_harga;
        $diskonNominal = (float) ($transaksi->diskon_nominal ?? 0);
        $pajakNominal = (float) ($transaksi->pajak_nominal ?? 0);
        $pajakPersen = (float) ($transaksi->pajak_persen ?? 0);
        $totalSebelumPajak = max(0, $subtotalHarga - $diskonNominal);
        $totalJikaPajakKePembeli = $totalSebelumPajak + $pajakNominal;

        return [
            'subtotal' => $subtotalHarga,
            'discount' => $diskonNominal,
            'tax' => $pajakNominal,
            'tax_percent' => $pajakPersen,
            'tax_charged_to_customer' => abs($totalJikaPajakKePembeli - (float) $transaksi->total_harga) < 0.01,
            'grand_total' => (float) $transaksi->total_harga,
        ];
    }

    private function buildPromoDescription(Pesanan $transaksi, array $items): ?string
    {
        $diskonNominal = (float) ($transaksi->diskon_nominal ?? 0);
        if ($diskonNominal <= 0) {
            return null;
        }

        $diskonTipe = (string) ($transaksi->diskon_tipe ?? '');
        $diskonNilai = (float) ($transaksi->diskon_nilai ?? 0);

        $diskonNilaiLabel = match ($diskonTipe) {
            'persen' => rtrim(rtrim(number_format($diskonNilai, 2, '.', ''), '0'), '.') . '%',
            'bundling', 'nominal', 'harga_kategori' => 'Rp ' . number_format($diskonNilai, 0, ',', '.'),
            default => null,
        };

        $diskonDeskripsiDasar = match ($diskonTipe) {
            'bundling' => trim('Bundling ' . ($transaksi->diskon_nama ?: 'Paket') . ' ' . ($diskonNilaiLabel ?? '')),
            'harga_kategori' => trim('Promo ' . ($transaksi->diskon_nama ?: 'Kategori') . ' Harga ' . ($diskonNilaiLabel ?? '')),
            'persen' => trim('Promo ' . ($transaksi->diskon_nama ?: 'Diskon') . ' ' . ($diskonNilaiLabel ?? '')),
            'nominal' => trim('Promo ' . ($transaksi->diskon_nama ?: 'Diskon') . ' ' . ($diskonNilaiLabel ?? '')),
            default => null,
        };

        $diskonKategoriTargetId = (int) ($transaksi->diskon?->id_kategori_target ?? 0);
        $diskonKategoriTargetName = trim((string) ($transaksi->diskon?->kategoriTarget?->nama_kategori ?? ''));
        $diskonMenuTerdampak = [];

        if ($diskonKategoriTargetId > 0) {
            foreach ($items as $item) {
                if ((int) ($item['category_id'] ?? 0) !== $diskonKategoriTargetId) {
                    continue;
                }

                $namaProduk = trim((string) ($item['name'] ?? ''));
                if ($namaProduk === '' || $namaProduk === 'Produk dihapus') {
                    continue;
                }

                $diskonMenuTerdampak[$namaProduk] = ($diskonMenuTerdampak[$namaProduk] ?? 0) + (int) ($item['qty'] ?? 0);
            }
        }

        $diskonMenuTerdampakLabel = null;
        if ($diskonMenuTerdampak !== []) {
            $menuLabels = [];
            foreach ($diskonMenuTerdampak as $menuNama => $menuQty) {
                $menuLabels[] = $menuNama . ' x' . max(1, (int) $menuQty);
            }
            $diskonMenuTerdampakLabel = implode(', ', $menuLabels);
        }

        $diskonCakupanLabel = null;
        if ($diskonTipe === 'harga_kategori' && $diskonMenuTerdampakLabel) {
            $diskonCakupanLabel = 'Menu promo: ' . $diskonMenuTerdampakLabel;
        } elseif (in_array($diskonTipe, ['persen', 'nominal'], true) && $diskonKategoriTargetId > 0) {
            $namaKategori = $diskonKategoriTargetName !== '' ? $diskonKategoriTargetName : ('#' . $diskonKategoriTargetId);
            $diskonCakupanLabel = 'Kategori ' . $namaKategori;
            if ($diskonMenuTerdampakLabel) {
                $diskonCakupanLabel .= ' | Menu: ' . $diskonMenuTerdampakLabel;
            }
        } elseif ($diskonTipe === 'bundling') {
            $diskonCakupanLabel = 'Paket bundling pada pesanan ini';
        }

        if ($diskonDeskripsiDasar && $diskonCakupanLabel) {
            return $diskonDeskripsiDasar . ' - ' . $diskonCakupanLabel;
        }

        return $diskonDeskripsiDasar;
    }

    private function buildPublicOrderId(Pesanan $transaksi): string
    {
        if (! empty($transaksi->no_urut_shift)) {
            $shiftKe = (int) ($transaksi->shift?->shift_ke ?? 0);
            $urut = str_pad((string) ((int) $transaksi->no_urut_shift), 3, '0', STR_PAD_LEFT);

            return 'S' . $shiftKe . '-' . $urut;
        }

        $hash = strtoupper(base_convert(sprintf('%u', crc32((string) $transaksi->id_pesanan)), 10, 36));

        return 'S0-' . str_pad(substr($hash, -6), 6, '0', STR_PAD_LEFT);
    }

    private function resolvePelangganLabel(Pesanan $transaksi): string
    {
        $pelanggan = trim((string) ($transaksi->pelanggan?->nama ?? ''));
        if ($pelanggan !== '') {
            return $pelanggan;
        }

        return $transaksi->kasir_label ? 'Admin' : 'Umum';
    }

    private function resolveKasirLabel(Pesanan $transaksi): string
    {
        $kasir = trim((string) ($transaksi->kasir_label ?: ($transaksi->karyawan?->nama_karyawan ?? '-')));

        return $kasir !== '' ? $kasir : '-';
    }

    private function buildOptionsLabel(mixed $detail): ?string
    {
        $parts = [];
        $note = null;

        foreach ([
            $this->mapOptionValue($detail->temperature ?? null, [
                'hot' => 'Hot',
                'less_ice' => 'Less Es',
                'ice' => 'Es',
            ]),
            $this->mapOptionValue($detail->sugar_level ?? null, [
                'none' => 'No Sugar',
                'less' => 'Less Sugar',
                'normal' => 'Normal Sugar',
            ]),
            $this->mapOptionValue($detail->cup_size ?? null, [
                'large' => 'Cup Large',
                'regular' => 'Cup Regular',
            ]),
            $this->mapOptionValue($detail->spicy_level ?? null, [
                'extra_spicy' => 'Extra Spicy',
                'spicy' => 'Spicy',
                'non_spicy' => 'Non Spicy',
            ]),
        ] as $label) {
            if ($label !== null) {
                $parts[] = $label;
            }
        }

        $selectedOptions = $detail->selected_options ?? null;
        if (is_string($selectedOptions) && $selectedOptions !== '') {
            $selectedOptions = json_decode($selectedOptions, true);
        }

        if (is_array($selectedOptions)) {
            foreach ($selectedOptions as $selectedKey => $selectedValue) {
                if (in_array((string) $selectedKey, ['note', '_note'], true)) {
                    if (is_string($selectedValue)) {
                        $cleanNote = preg_replace('/\s+/', ' ', trim($selectedValue)) ?? '';
                        if ($cleanNote !== '') {
                            $note = $cleanNote;
                        }
                    }
                    continue;
                }

                if (! is_string($selectedValue) || trim($selectedValue) === '') {
                    continue;
                }

                $parts[] = ucwords(str_replace('_', ' ', $selectedValue));
            }
        }

        if ($note !== null) {
            $parts[] = 'Catatan: ' . $note;
        }

        $parts = array_values(array_unique($parts));

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    private function mapOptionValue(mixed $value, array $map): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return $map[$value] ?? ucwords(str_replace('_', ' ', $value));
    }
}
