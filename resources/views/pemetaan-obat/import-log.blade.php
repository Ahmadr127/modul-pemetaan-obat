@extends('layouts.app')

@section('title', 'Log Import Pemetaan Obat')

@section('content')
<div class="space-y-5">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-extrabold text-sp-navy">Log Import Pemetaan Obat</h3>
            <p class="text-sm text-gray-500">Riwayat setiap sesi import Excel beserta error / keterangannya.</p>
        </div>
        <a href="{{ route('pemetaan-obat.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-semibold text-sp-primary border border-sp-primary/30 rounded-md hover:bg-sp-primary/5 transition-colors">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>

    <x-card padding="false">
        <x-slot name="title">Riwayat Import</x-slot>
        <x-slot name="subtitle">Gunakan pencarian untuk memfilter log berdasarkan file, user, atau pesan.</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-max">
                <thead>
                    <tr class="bg-sp-primary/10 border-b border-gray-200">
                        <th class="px-4 py-2.5 text-left whitespace-nowrap"><span class="font-semibold text-sp-navy">Waktu</span></th>
                        <th class="px-4 py-2.5 text-left whitespace-nowrap"><span class="font-semibold text-sp-navy">File</span></th>
                        <th class="px-4 py-2.5 text-left whitespace-nowrap"><span class="font-semibold text-sp-navy">User</span></th>
                        <th class="px-4 py-2.5 text-left whitespace-nowrap"><span class="font-semibold text-sp-navy">Status</span></th>
                        <th class="px-4 py-2.5 text-center whitespace-nowrap"><span class="font-semibold text-sp-navy">Total</span></th>
                        <th class="px-4 py-2.5 text-center whitespace-nowrap"><span class="font-semibold text-sp-navy">Import</span></th>
                        <th class="px-4 py-2.5 text-center whitespace-nowrap"><span class="font-semibold text-sp-navy">Skipped</span></th>
                        <th class="px-4 py-2.5 text-center whitespace-nowrap"><span class="font-semibold text-sp-navy">Failed</span></th>
                        <th class="px-4 py-2.5 text-left whitespace-nowrap"><span class="font-semibold text-sp-navy">Pesan Error / Keterangan</span></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse($logRows as $row)
                        <tr class="hover:bg-gray-50 transition-colors align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ $row['waktu'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-sp-navy">{{ $row['file'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $row['user'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full {{ $row['status_color'] }}">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-700">{{ $row['total'] }}</td>
                            <td class="px-4 py-3 text-center text-emerald-600 font-semibold">{{ $row['imported'] }}</td>
                            <td class="px-4 py-3 text-center text-sky-600 font-semibold">{{ $row['skipped'] }}</td>
                            <td class="px-4 py-3 text-center text-red-600 font-semibold">{{ $row['failed'] }}</td>
                            <td class="px-4 py-3 max-w-md">
                                <span class="text-xs text-gray-600 leading-snug block">{{ $row['message'] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">Belum ada log import.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
@endsection
