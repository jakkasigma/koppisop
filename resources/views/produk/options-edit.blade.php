@extends('layouts.app')

@section('title', 'Atur Opsi Kasir Produk')

@section('content')
<div class="container">
    <div class="admin-page-head">
    <div>
        <div class="hero-split">
            <div>
                <h1>Atur Opsi Kasir: {{ $produk->nama_produk }}</h1>
                <p class="hero-sub">Kelola pilihan dan tambahan biaya. Opsi aktif/nonaktif tetap diatur dari halaman edit produk.</p>
                
            </div>
            <div class="hero-side">
                <a class="btn-ghost" href="{{ route('produk.options.index') }}">Kembali</a>
            </div>
        </div>
    </div>
    </div>

    <div class="panel form-panel">
        @if ($errors->any())
            <div class="alert err">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @php
            $temperatureText = old('temperature_options_text', collect($produk->resolvedTemperatureOptions())->map(fn ($row) => $row['value'].'|'.$row['label'].'|'.(int)($row['extra_price'] ?? 0))->implode("\n"));
            $sugarText = old('sugar_options_text', collect($produk->resolvedSugarOptions())->map(fn ($row) => $row['value'].'|'.$row['label'].'|'.(int)($row['extra_price'] ?? 0))->implode("\n"));
            $cupText = old('cup_size_options_text', collect($produk->resolvedCupSizeOptions())->map(fn ($row) => $row['value'].'|'.$row['label'].'|'.(int)($row['extra_price'] ?? 0))->implode("\n"));
            $spicyText = old('spicy_options_text', collect($produk->resolvedSpicyOptions())->map(fn ($row) => $row['value'].'|'.$row['label'].'|'.(int)($row['extra_price'] ?? 0))->implode("\n"));
            $customGroupsJson = old('custom_option_groups_json', json_encode($produk->resolvedCustomOptionGroups()));
        @endphp

        <form method="post" action="{{ route('produk.options.update', $produk) }}">
            @csrf
            @method('PUT')

            <div class="block">
                <label>Opsi Suhu</label>
                @if($produk->is_temperature_enabled)
                    <textarea name="temperature_options_text" rows="4" placeholder="hot|Hot|0&#10;ice|Es|0&#10;less_ice|Less Es|0">{{ $temperatureText }}</textarea>
                    <p class="hint">Format: `value|Label|ExtraHarga`.</p>
                @else
                    <div class="disabled-note">Opsi suhu sedang nonaktif. Aktifkan dulu dari Edit Produk.</div>
                @endif
            </div>

            <div class="block">
                <label>Opsi Sugar</label>
                @if($produk->is_sugar_enabled)
                    <textarea name="sugar_options_text" rows="4" placeholder="normal|Normal Sugar|0&#10;less|Less Sugar|0&#10;none|No Sugar|0">{{ $sugarText }}</textarea>
                    <p class="hint">Format: `value|Label|ExtraHarga`.</p>
                @else
                    <div class="disabled-note">Opsi sugar sedang nonaktif. Aktifkan dulu dari Edit Produk.</div>
                @endif
            </div>

            <div class="block">
                <label>Opsi Cup</label>
                @if($produk->is_cup_size_enabled)
                    <textarea name="cup_size_options_text" rows="4" placeholder="regular|Regular|0&#10;large|Large|2000">{{ $cupText }}</textarea>
                    <p class="hint">Format: `value|Label|ExtraHarga`.</p>
                @else
                    <div class="disabled-note">Opsi cup sedang nonaktif. Aktifkan dulu dari Edit Produk.</div>
                @endif
            </div>

            <div class="block">
                <label>Opsi Spicy</label>
                @if($produk->is_spicy_enabled)
                    <textarea name="spicy_options_text" rows="4" placeholder="non_spicy|Non Spicy|0&#10;spicy|Spicy|0&#10;extra_spicy|Extra Spicy|0">{{ $spicyText }}</textarea>
                    <p class="hint">Format: `value|Label|ExtraHarga`.</p>
                @else
                    <div class="disabled-note">Opsi spicy sedang nonaktif. Aktifkan dulu dari Edit Produk.</div>
                @endif
            </div>

            <div class="block">
                <label>Opsi Tambahan Dinamis</label>
                <p class="hint">Contoh: Saos, Topping, dll. Tiap pilihan bisa punya tambahan biaya.</p>
                <input type="hidden" name="custom_option_groups_json" id="customOptionGroupsJson" value="{{ $customGroupsJson }}">
                <div class="custom-wrap">
                    <div id="customGroupsContainer"></div>
                    <button type="button" class="btn-neutral btn-small" id="addCustomGroupBtn">+ Tambah Grup Opsi</button>
                </div>
            </div>

            <div class="actions">
                <button class="btn-primary" type="submit">Simpan Opsi</button>
                <a class="btn-neutral" href="{{ route('produk.options.index') }}">Kembali</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const hiddenInput = document.getElementById('customOptionGroupsJson');
        const groupsContainer = document.getElementById('customGroupsContainer');
        const addGroupBtn = document.getElementById('addCustomGroupBtn');

        const createGroup = (group = null) => ({
            id: String(group?.id || ''),
            label: String(group?.label || ''),
            required: !!group?.required,
            options: Array.isArray(group?.options) && group.options.length > 0
                ? group.options.map((opt) => ({
                    value: String(opt?.value || ''),
                    label: String(opt?.label || ''),
                    extra_price: Number(opt?.extra_price || 0),
                }))
                : [{ value: '', label: '', extra_price: 0 }],
        });

        const state = [];
        const slugify = (value) => String(value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        const esc = (value) => String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        const syncHidden = () => {
            const clean = state.map((group) => {
                const id = slugify(group.id || group.label);
                const options = (group.options || []).map((opt) => ({
                    value: slugify(opt.value || opt.label),
                    label: String(opt.label || '').trim(),
                    extra_price: Math.max(0, Number(opt.extra_price || 0)),
                })).filter((opt) => opt.value && opt.label);

                return {
                    id,
                    label: String(group.label || '').trim(),
                    required: !!group.required,
                    options,
                };
            }).filter((group) => group.id && group.label && group.options.length > 0);

            hiddenInput.value = clean.length > 0 ? JSON.stringify(clean) : '';
        };

        const render = () => {
            groupsContainer.innerHTML = '';
            state.forEach((group, groupIndex) => {
                const card = document.createElement('div');
                card.className = 'group-card';
                card.innerHTML = `
                    <div class="group-head">
                        <div><label>Nama Grup</label><input type="text" data-field="group-label" value="${esc(group.label)}"></div>
                        <div><label>Kode Grup (opsional)</label><input type="text" data-field="group-id" value="${esc(group.id)}"></div>
                        <label class="check u-m-0">
                            <input type="checkbox" data-field="group-required" ${group.required ? 'checked' : ''}>
                            <span>Wajib pilih</span>
                        </label>
                        <button type="button" class="btn-danger btn-small" data-action="remove-group">Hapus Grup</button>
                    </div>
                    <div class="group-options"></div>
                    <button type="button" class="btn-neutral btn-small" data-action="add-option">+ Tambah Pilihan</button>
                `;

                const optionsWrap = card.querySelector('.group-options');
                group.options.forEach((opt, optIndex) => {
                    const row = document.createElement('div');
                    row.className = 'opt-row';
                    row.innerHTML = `
                        <input type="text" data-field="opt-label" value="${esc(opt.label)}" placeholder="Label pilihan">
                        <input type="text" data-field="opt-value" value="${esc(opt.value)}" placeholder="Value (opsional)">
                        <input type="number" data-field="opt-price" value="${Math.max(0, Number(opt.extra_price || 0))}" min="0" step="1" placeholder="Extra harga">
                        <button type="button" class="btn-danger btn-small" data-action="remove-option">Hapus</button>
                    `;

                    row.querySelector('[data-field="opt-label"]').addEventListener('input', (e) => { state[groupIndex].options[optIndex].label = e.target.value; syncHidden(); });
                    row.querySelector('[data-field="opt-value"]').addEventListener('input', (e) => { state[groupIndex].options[optIndex].value = e.target.value; syncHidden(); });
                    row.querySelector('[data-field="opt-price"]').addEventListener('input', (e) => { state[groupIndex].options[optIndex].extra_price = Number(e.target.value || 0); syncHidden(); });
                    row.querySelector('[data-action="remove-option"]').addEventListener('click', () => {
                        state[groupIndex].options.splice(optIndex, 1);
                        if (state[groupIndex].options.length === 0) {
                            state[groupIndex].options.push({ value: '', label: '', extra_price: 0 });
                        }
                        render();
                    });
                    optionsWrap.appendChild(row);
                });

                card.querySelector('[data-field="group-label"]').addEventListener('input', (e) => { state[groupIndex].label = e.target.value; syncHidden(); });
                card.querySelector('[data-field="group-id"]').addEventListener('input', (e) => { state[groupIndex].id = e.target.value; syncHidden(); });
                card.querySelector('[data-field="group-required"]').addEventListener('change', (e) => { state[groupIndex].required = e.target.checked; syncHidden(); });
                card.querySelector('[data-action="remove-group"]').addEventListener('click', () => { state.splice(groupIndex, 1); render(); });
                card.querySelector('[data-action="add-option"]').addEventListener('click', () => {
                    state[groupIndex].options.push({ value: '', label: '', extra_price: 0 });
                    render();
                });

                groupsContainer.appendChild(card);
            });

            syncHidden();
        };

        addGroupBtn.addEventListener('click', () => {
            state.push(createGroup());
            render();
        });

        try {
            const initial = hiddenInput.value ? JSON.parse(hiddenInput.value) : [];
            if (Array.isArray(initial)) {
                initial.forEach((group) => state.push(createGroup(group)));
            }
        } catch (_) {}

        render();
    })();
</script>
@endsection
