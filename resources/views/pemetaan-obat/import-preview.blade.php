@extends('layouts.app')

@section('title', 'Preview Import Pemetaan Obat')

@php
    $statusLabels = ['new' => 'Baru', 'exists' => 'Sudah Ada', 'duplicate' => 'Duplicate', 'error' => 'Error'];
    $statusColors = [
        'new' => 'bg-emerald-100 text-emerald-700',
        'exists' => 'bg-sky-100 text-sky-700',
        'duplicate' => 'bg-amber-100 text-amber-700',
        'error' => 'bg-red-100 text-red-700',
    ];
@endphp

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-extrabold text-sp-navy">Preview Import Pemetaan Obat</h3>
            <p class="text-sm text-gray-500">File: <span class="font-semibold text-sp-navy">{{ $fileName }}</span> — Data dikelompokkan per obat generik (satu master, banyak brand).</p>
        </div>
        <a href="{{ route('pemetaan-obat.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md hover:bg-sp-primary/5 transition-colors">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    {{-- Summary Validasi --}}
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <x-stats label="Total Baris" :value="$summary['total']" icon="bi-list-ol" color="bg-sp-navy" />
        <x-stats label="Total Generik" :value="$summary['generik']" icon="bi-capsule" color="bg-emerald-600" />
        <x-stats label="Total Brand" :value="$summary['brand']" icon="bi-bag-check" color="bg-sky-600" />
        <x-stats label="Total Mapping" :value="$summary['mapping']" icon="bi-diagram-3" color="bg-indigo-600" />
        <x-stats label="Peringatan" :value="$summary['warning']" icon="bi-exclamation-triangle" color="bg-orange-500" />
        <x-stats label="Error" :value="$summary['error']" icon="bi-x-circle" color="bg-red-500" />
    </div>

    @if(($summary['exists'] ?? 0) > 0 || ($summary['duplicate'] ?? 0) > 0)
        <p class="text-xs text-gray-500 bg-white border border-gray-200 rounded-lg px-4 py-2">
            Sudah ada: {{ $summary['exists'] }} mapping · Duplikat dalam file: {{ $summary['duplicate'] }} mapping · Siap ditambahkan: {{ $summary['new'] }} mapping.
        </p>
    @endif

    {{-- Struktur grouped: GENERIK -> brand --}}
    <div class="space-y-4">
        @foreach($groups as $group)
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusColors[$group['status']] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusLabels[$group['status']] ?? $group['status'] }}
                        </span>
                        <span class="text-sm font-bold text-sp-navy">{{ $group['kode'] }}</span>
                    </div>
                    <p class="text-sm text-gray-900 mt-1">{{ $group['nama'] ?: '-' }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Harga: {{ $group['harga'] === null ? '-' : 'Rp '.number_format($group['harga'], 0, ',', '.') }} · Baris: {{ $group['row'] }}
                    </p>
                    @if($group['message'])
                        <p class="text-xs text-red-500 mt-1">{{ $group['message'] }}</p>
                    @endif
                </div>

                <ul class="divide-y divide-gray-100">
                    @forelse($group['brands'] as $brand)
                        <li class="px-4 py-2.5 pl-10 flex flex-col lg:flex-row lg:items-center justify-between gap-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <i class="bi bi-arrow-return-right text-gray-300"></i>
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $statusColors[$brand['status']] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ $statusLabels[$brand['status']] ?? $brand['status'] }}
                                </span>
                                <span class="text-sm font-semibold text-sp-navy">{{ $brand['kode'] ?: '-' }}</span>
                                <span class="text-sm text-gray-900">{{ $brand['nama'] ?: '-' }}</span>
                                <span class="text-xs text-gray-500">
                                    {{ $brand['harga'] === null ? '-' : 'Rp '.number_format($brand['harga'], 0, ',', '.') }} · Baris {{ $brand['row'] }}
                                </span>
                            </div>
                            @if($brand['message'])
                                <p class="text-xs text-red-500">{{ $brand['message'] }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-3 pl-10 text-xs text-gray-400">Tidak ada brand pada generik ini.</li>
                    @endforelse
                </ul>
            </div>
        @endforeach

        {{-- Baris error tanpa parent generik --}}
        @foreach($orphans as $orphan)
            <div class="bg-white rounded-lg border border-red-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Error</span>
                    <span class="ml-2 text-sm text-gray-700">Baris {{ $orphan['row'] }}</span>
                    <span class="ml-1 text-sm font-semibold text-sp-navy">{{ $orphan['kode'] ?: '-' }}</span>
                    <span class="text-sm text-gray-900">{{ $orphan['nama'] ?: '' }}</span>
                    <p class="text-xs text-red-500 mt-1">{{ implode(' | ', $orphan['errors']) }}</p>
                </div>
            </div>
        @endforeach

        @if(empty($groups) && empty($orphans))
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-8 text-center text-gray-500">Tidak ada data.</div>
        @endif
    </div>

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