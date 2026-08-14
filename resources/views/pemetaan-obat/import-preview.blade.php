@extends('layouts.app')

@section('title', 'Preview Import Pemetaan Obat')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-extrabold text-sp-navy">Preview Import Pemetaan Obat</h3>
            <p class="text-sm text-gray-500">File: <span class="font-semibold text-sp-navy">{{ $fileName }}</span> — Periksa hasil validasi sebelum import.</p>
        </div>
        <a href="{{ route('pemetaan-obat.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md hover:bg-sp-primary/5 transition-colors">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    {{-- Summary Validasi --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <x-stats label="Total Data" :value="$summary['total']" icon="bi-list-ol" color="bg-sp-navy" />
        <x-stats label="Akan Ditambahkan" :value="$summary['new']" icon="bi-plus-circle" color="bg-emerald-600" />
        <x-stats label="Sudah Ada" :value="$summary['exists']" icon="bi-check-circle" color="bg-sky-600" />
        <x-stats label="Duplicate" :value="$summary['duplicate']" icon="bi-files" color="bg-amber-500" />
        <x-stats label="Peringatan" :value="$summary['warning']" icon="bi-exclamation-triangle" color="bg-orange-500" />
        <x-stats label="Error" :value="$summary['error']" icon="bi-x-circle" color="bg-red-500" />
    </div>

    {{-- Detail Data --}}
    <x-card padding="false">
        <x-slot name="title">Detail Data</x-slot>
        <x-slot name="subtitle">Gunakan pencarian & paginasi untuk menelusuri data. Kolom "Keterangan" menampilkan detail error / peringatan per baris.</x-slot>

        <x-searchable-table :columns="$columns" :rows="$tableRows" />
    </x-card>

    {{-- Aksi --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2">
        <a href="{{ route('pemetaan-obat.index') }}"
            class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold text-gray-600 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors">
            Batal
        </a>
        <form method="POST" action="{{ route('pemetaan-obat.import.confirm') }}" x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <button type="submit" :disabled="submitting"
                class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-md bg-sp-primary hover:bg-sp-primary-dark disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                <span x-show="!submitting"><i class="bi bi-check2-circle"></i> Import</span>
                <span x-show="submitting"><i class="bi bi-arrow-repeat animate-spin"></i> Memproses...</span>
            </button>
        </form>
    </div>
</div>
@endsection
