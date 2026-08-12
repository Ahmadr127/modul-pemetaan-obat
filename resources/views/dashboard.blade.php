@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-5">
    <!-- Welcome -->
    <x-card>
        <h2 class="text-2xl font-bold text-gray-900">Selamat Datang, {{ $user->name }}!</h2>
        <p class="text-gray-500 mb-0">Sistem Pemetaan Obat</p>
    </x-card>

    @if($user->hasPermission('manage_pemetaan_obat'))
    <!-- Stats (sekaligus shortcut, klik untuk membuka halaman terkait) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach($stats as $stat)
        <x-stats
            :label="$stat['label']"
            :value="$stat['value']"
            :icon="$stat['icon']"
            :color="$stat['color']"
            :link="$stat['link'] ?? null"
        />
        @endforeach
    </div>

    <!-- Searchable Table (pencarian per kolom di baris pertama) -->
    <x-card title="Daftar Pemetaan" subtitle="Mapping obat generik dengan brand / paten">
        <x-searchable-table
            :columns="[
                ['key' => 'kode', 'label' => 'Kode'],
                ['key' => 'generik', 'label' => 'Nama Generik'],
                ['key' => 'brand', 'label' => 'Nama Brand'],
                ['key' => 'harga', 'label' => 'Harga Jual'],
                ['key' => 'tanggal', 'label' => 'Dibuat'],
            ]"
            :rows="$tableRows"
            :per-page="8"
        />
    </x-card>

    <!-- Chart -->
    <x-card title="Top Obat Generik" subtitle="Jumlah brand terpetakan per obat generik">
        <x-chart
            type="bar"
            :labels="$chartLabels"
            :datasets="[[
                'label' => 'Jumlah Brand',
                'data' => $chartData,
                'borderColor' => '#007774',
                'backgroundColor' => 'rgba(0, 119, 116, 0.75)',
                'borderRadius' => 6,
            ]]"
            :height="280"
        />
    </x-card>

    
    @endif
</div>
@endsection
