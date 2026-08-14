@extends('layouts.app')

@section('title', 'Data Obat Brand')

@section('content')
<div class="space-y-5" x-data="brandModal({{ Js::from($brandMap) }})" @open-edit.window="openEdit($event.detail.id)">

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        {{-- Tambah Obat Brand --}}
        <div class="lg:col-span-4">
            <x-card title="Tambah Obat Brand" subtitle="Tambahkan obat brand / paten baru">
                <form action="{{ route('pemetaan-obat.brand.store') }}" method="POST">
                    @csrf

                    <x-input name="kode_obat" label="Kode Obat" placeholder="Contoh: OBT01119" :required="true" />
                    <x-input name="nama_brand" label="Nama Brand / Paten" placeholder="Contoh: RESFAR 30 ML INJ" :required="true" />
                    <x-input name="harga_jual" label="Harga Jual" type="number" placeholder="Contoh: 298202" />

                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark transition-colors">
                        <i class="bi bi-floppy"></i> Simpan Obat Brand
                    </button>
                </form>
            </x-card>
        </div>

        {{-- Data Obat Brand --}}
        <div class="lg:col-span-8">
            <x-card padding="false">
                <x-slot name="title">Data Obat Brand</x-slot>
                <x-slot name="subtitle">Daftar obat brand / paten dalam sistem — gunakan pencarian per kolom untuk memfilter data</x-slot>

                <x-searchable-table :columns="$brandColumns" :rows="$brandRows" :actions="$brandActions" />
            </x-card>
        </div>
    </div>

    {{-- MODAL EDIT OBAT BRAND --}}
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
                <h5 class="font-bold"><i class="bi bi-pencil-square mr-2"></i>Edit Obat Brand</h5>
                <button type="button" @click="close()" class="text-white/80 hover:text-white transition-colors"><i class="bi bi-x-lg"></i></button>
            </div>

            <form :action="action" method="POST" class="p-4">
                <input type="hidden" name="_method" value="PUT">
                @csrf

                <x-input x-model="kode_obat" id="br-kode" name="kode_obat" label="Kode Obat" :required="true" />
                <x-input x-model="nama_brand" id="br-nama" name="nama_brand" label="Nama Brand / Paten" :required="true" />
                <x-input x-model="harga_jual" id="br-harga" name="harga_jual" label="Harga Jual" type="number" />

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
    Alpine.data('brandModal', (initial = {}) => ({
        roles: initial,
        open: false,
        action: '',
        kode_obat: '',
        nama_brand: '',
        harga_jual: '',

        openEdit(id) {
            const item = this.roles[id];
            if (!item) return;

            this.action = '{{ url('pemetaan-obat/obat-brand') }}/' + item.id;
            this.kode_obat = item.kode_obat;
            this.nama_brand = item.nama_brand;
            this.harga_jual = item.harga_jual ?? '';
            this.open = true;
        },

        close() {
            this.open = false;
        }
    }));
});
</script>
@endpush