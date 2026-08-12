<?php

namespace App\Http\Controllers;

use App\Models\ObatBrand;
use App\Models\ObatGenerik;
use App\Models\PemetaanObat;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $hasAccess = $user->hasPermission('manage_pemetaan_obat');

        $stats = $hasAccess ? [
            ['label' => 'Obat Generik', 'value' => ObatGenerik::count(), 'icon' => 'bi-capsule', 'color' => 'bg-sp-primary', 'link' => route('pemetaan-obat.generik')],
            ['label' => 'Obat Brand', 'value' => ObatBrand::count(), 'icon' => 'bi-bag-check', 'color' => 'bg-blue-500', 'link' => route('pemetaan-obat.brand')],
            ['label' => 'Total Pemetaan', 'value' => PemetaanObat::count(), 'icon' => 'bi-diagram-3-fill', 'color' => 'bg-purple-500', 'link' => route('pemetaan-obat.index')],
        ] : [];

        // Chart: top obat generik berdasarkan jumlah brand yang terpetakan
        $topGenerik = ObatGenerik::withCount('brand')->orderByDesc('brand_count')->limit(8)->get();
        $chartLabels = $topGenerik->map(fn($g) => $g->nama_generik)->all();
        $chartData = $topGenerik->map(fn($g) => $g->brand_count)->all();

        // Data untuk tabel pemetaan dengan pencarian per kolom
        $tableRows = PemetaanObat::with('obatGenerik', 'obatBrand')->latest('updated_at')->limit(50)->get()->map(fn($p) => [
            'kode' => $p->obatGenerik->kode_obat,
            'generik' => $p->obatGenerik->nama_generik,
            'brand' => $p->obatBrand->nama_brand,
            'harga' => $p->obatBrand->harga_jual !== null ? 'Rp ' . number_format($p->obatBrand->harga_jual, 0, ',', '.') : '-',
            'tanggal' => $p->created_at->format('d/m/Y'),
        ])->toArray();

        return view('dashboard', compact('user', 'stats', 'chartLabels', 'chartData', 'tableRows'));
    }
}
