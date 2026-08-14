@extends('layouts.app')

@section('title', 'Pemetaan Obat')

@section('content')
<div class="space-y-5" x-data="mappingModal({{ Js::from($pemetaanMap ?? []) }})" @open-edit.window="openEdit($event.detail.id)">

    {{-- Pilih Obat Generik --}}
    <x-card>
        <x-slot name="title">Pemetaan Obat</x-slot>
        <x-slot name="subtitle">Pilih obat generik / kandungan untuk melihat obat brand & paten terkait</x-slot>
        <x-slot name="actions">
            <a href="{{ route('pemetaan-obat.import.template') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md hover:bg-sp-primary/5 transition-colors">
                <i class="bi bi-file-earmark-arrow-down"></i> Download Template
            </a>
            <button type="button" @click="$dispatch('open-import')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-file-earmark-excel"></i> Import Excel
            </button>
            <a href="{{ route('pemetaan-obat.generik') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md hover:bg-sp-primary/5 transition-colors">
                <i class="bi bi-capsule"></i> Obat Generik
            </a>
            <a href="{{ route('pemetaan-obat.brand') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md hover:bg-sp-primary/5 transition-colors">
                <i class="bi bi-bag-check"></i> Obat Brand
            </a>
        </x-slot>

        <form method="GET" action="{{ route('pemetaan-obat.index') }}" class="flex flex-col sm:flex-row gap-3 items-end">
            <div class="flex-1 w-full">
                <x-searchable-dropdown
                    name="obat_generik_id"
                    label="Obat Generik / Kandungan"
                    :options="$obatGenerikList"
                    value-field="id"
                    label-field="label"
                    :selected="$selectedGenerikId"
                    placeholder="Cari kode / nama obat generik..."
                />
            </div>
            <button type="submit"
                class="inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                <i class="bi bi-search"></i> Tampilkan
            </button>
        </form>
    </x-card>

    @if($generik)
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        {{-- Detail Obat Generik + Tambah Brand --}}
        <div class="lg:col-span-4">
            <x-card title="Obat Generik" subtitle="Informasi generik terpilih">
                <dl class="space-y-3">
                    <div class="flex items-center justify-between">
                        <dt class="text-xs font-semibold text-gray-500">Kode Obat</dt>
                        <dd class="text-sm font-bold text-sp-navy">{{ $generik->kode_obat }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-gray-500 mb-1">Nama Generik</dt>
                        <dd class="text-sm font-medium text-gray-900 leading-snug">{{ $generik->nama_generik }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-xs font-semibold text-gray-500">Harga Jual</dt>
                        <dd class="text-sm font-bold text-sp-primary">Rp {{ number_format($generik->harga_jual ?? 0, 0, ',', '.') }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-xs font-semibold text-gray-500">Jumlah Brand</dt>
                        <dd>
                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $pemetaan->count() > 0 ? 'bg-sp-primary/10 text-sp-primary' : 'bg-gray-100 text-gray-600' }}">
                                {{ $pemetaan->count() }} brand
                            </span>
                        </dd>
                    </div>
                </dl>

                {{-- Tambah Brand Mapping --}}
                <form action="{{ route('pemetaan-obat.store') }}" method="POST" class="mt-5 pt-4 border-t border-gray-100">
                    @csrf
                    <input type="hidden" name="obat_generik_id" value="{{ $generik->id }}">
                    <x-searchable-dropdown
                        name="obat_brand_id"
                        label="Tambah Obat Brand / Paten"
                        :options="$obatBrandList"
                        value-field="id"
                        label-field="label"
                        placeholder="Cari kode / nama brand..."
                        :required="true"
                    />
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-plus-lg"></i> Tambah Pemetaan
                    </button>
                </form>
            </x-card>
        </div>

        {{-- Daftar Brand Terkait --}}
        <div class="lg:col-span-8">
            <x-card padding="false">
                <x-slot name="title">Obat Brand / Paten</x-slot>
                <x-slot name="subtitle">Seluruh brand yang terpetakan ke generik terpilih</x-slot>

                <x-searchable-table :columns="$mappingColumns" :rows="$pemetaanRows" :actions="$mappingActions" empty="Belum ada brand yang terpetakan." />
            </x-card>
        </div>
    </div>
    @endif

    {{-- MODAL IMPORT EXCEL --}}
    <div x-data="importModal" @open-import.window="open = true" x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close()"></div>

        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-sp-primary text-white rounded-t-lg">
                <h5 class="font-bold"><i class="bi bi-file-earmark-excel mr-2"></i>Import Excel Pemetaan Obat</h5>
                <button type="button" @click="close()" class="text-white/80 hover:text-white transition-colors"><i class="bi bi-x-lg"></i></button>
            </div>

            <form action="{{ route('pemetaan-obat.import.preview') }}" method="POST" enctype="multipart/form-data" class="p-4">
                @csrf

                <div class="mb-3">
                    <label for="import-file" class="block text-sm font-semibold text-sp-navy mb-1">
                        File Excel <span class="text-red-500">*</span>
                    </label>
                    <input type="file" id="import-file" name="file" accept=".xlsx,.xls" required
                        @change="fileName = $event.target.files[0] ? $event.target.files[0].name : ''"
                        class="w-full text-sm px-3 py-2 border border-gray-300 rounded-md bg-white file:mr-3 file:py-1.5 file:px-3 file:border-0 file:rounded-md file:bg-sp-primary/10 file:text-sp-primary file:font-semibold hover:file:bg-sp-primary/20 focus:outline-none focus:ring-2 focus:ring-sp-primary/20 focus:border-sp-primary transition-colors">
                    <p class="mt-1 text-xs text-gray-500" x-show="fileName" x-text="'File terpilih: ' + fileName"></p>
                    <p class="mt-1 text-xs text-gray-500">Format: .xlsx / .xls, maksimal 5 MB. Gunakan template yang tersedia agar format sesuai.</p>
                </div>

                @error('file')
                    <p class="mb-3 text-xs text-red-500">{{ $message }}</p>
                @enderror

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="close()"
                        class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL EDIT MAPPING --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="close()"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="close()"></div>

        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-sp-primary text-white rounded-t-lg">
                <h5 class="font-bold"><i class="bi bi-pencil-square mr-2"></i>Edit Pemetaan Obat</h5>
                <button type="button" @click="close()" class="text-white/80 hover:text-white transition-colors"><i class="bi bi-x-lg"></i></button>
            </div>

            <form :action="action" method="POST" class="p-4">
                <input type="hidden" name="_method" value="PUT">
                @csrf
                <input type="hidden" name="obat_generik_id" :value="generikId">

                <div class="mb-3 bg-sp-primary/5 border border-sp-primary/20 rounded-md p-3">
                    <div class="text-xs font-semibold text-sp-navy mb-1">Obat Generik (tetap)</div>
                    <div class="text-sm text-gray-800 leading-snug" x-text="generikLabel"></div>
                </div>

                <div class="mb-3 bg-gray-50 border border-gray-100 rounded-md p-3">
                    <div class="text-xs font-semibold text-gray-500 mb-1">Brand saat ini</div>
                    <div class="text-sm text-gray-800 leading-snug" x-text="currentBrandLabel"></div>
                </div>

                <x-searchable-dropdown
                    name="obat_brand_id"
                    label="Ganti Obat Brand / Paten"
                    :options="$obatBrandList"
                    value-field="id"
                    label-field="label"
                    placeholder="Pilih brand pengganti..."
                    :required="true"
                />

                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" @click="close()"
                        class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('importModal', () => ({
        open: false,
        fileName: '',
        close() {
            this.open = false;
        }
    }));

    Alpine.data('mappingModal', (initial = {}) => ({
        roles: initial,
        open: false,
        action: '',
        generikId: '',
        generikLabel: '',
        currentBrandLabel: '',

        openEdit(id) {
            const item = this.roles[id];
            if (!item) return;

            this.action = '{{ url('pemetaan-obat') }}/' + item.id;
            this.generikId = item.generik_id;
            this.generikLabel = item.generik_label;
            this.currentBrandLabel = item.brand_label;
            this.open = true;
        },

        close() {
            this.open = false;
        }
    }));
});
</script>
@endpush