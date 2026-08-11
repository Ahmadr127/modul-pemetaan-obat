<?php

namespace App\Http\Controllers;

use App\Models\ObatBrand;
use App\Models\ObatGenerik;
use App\Models\PemetaanObat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PemetaanObatController extends Controller
{
    public function index(Request $request)
    {
        $selectedGenerikId = $request->integer('obat_generik_id');

        $generik = null;
        $pemetaan = collect();
        $mappedBrandIds = [];

        if ($selectedGenerikId) {
            $generik = ObatGenerik::with('pemetaan.obatBrand')->find($selectedGenerikId);
            $pemetaan = $generik?->pemetaan ?? collect();
            $mappedBrandIds = $pemetaan->pluck('obat_brand_id')->all();
        }

        $obatGenerikList = ObatGenerik::orderBy('nama_generik')->get()->map(fn($g) => [
            'id' => $g->id,
            'label' => $g->kode_obat . ' - ' . $g->nama_generik,
        ]);

        $obatBrandList = ObatBrand::orderBy('nama_brand')->get()->map(fn($b) => [
            'id' => $b->id,
            'label' => $b->kode_obat . ' - ' . $b->nama_brand,
        ]);

        $pemetaanMap = $generik
            ? $pemetaan->mapWithKeys(fn($p) => [$p->id => [
                'id' => $p->id,
                'generik_id' => $generik->id,
                'generik_label' => $generik->kode_obat . ' - ' . $generik->nama_generik,
                'brand_label' => $p->obatBrand->kode_obat . ' - ' . $p->obatBrand->nama_brand,
            ]])->all()
            : [];

        return view('pemetaan-obat.index', compact(
            'selectedGenerikId',
            'generik',
            'pemetaan',
            'obatGenerikList',
            'obatBrandList',
            'mappedBrandIds',
            'pemetaanMap'
        ));
    }

    public function generikIndex(Request $request)
    {
        $query = ObatGenerik::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($w) => $w->where('kode_obat', 'ilike', "%{$search}%")
                ->orWhere('nama_generik', 'ilike', "%{$search}%"));
        }

        $perPage = in_array((int) $request->input('per_page', 10), [5, 10, 25, 50, 100])
            ? (int) $request->input('per_page', 10) : 10;

        $generikList = $query->withCount('pemetaan')->orderBy('kode_obat')->paginate($perPage)->withQueryString();

        $generikMap = collect($generikList->items())->mapWithKeys(fn($g) => [$g->id => [
            'id' => $g->id,
            'kode_obat' => $g->kode_obat,
            'nama_generik' => $g->nama_generik,
            'harga_jual' => $g->harga_jual,
        ]])->all();

        return view('pemetaan-obat.generik', compact('generikList', 'generikMap'));
    }

    public function generikStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_obat' => 'required|string|max:255|unique:obat_generik,kode_obat',
            'nama_generik' => 'required|string|max:255',
            'harga_jual' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->route('pemetaan-obat.generik')->withErrors($validator)->withInput();
        }

        ObatGenerik::create($this->drugData($validator->validated()));

        return redirect()->route('pemetaan-obat.generik')->with('success', 'Obat generik berhasil ditambahkan!');
    }

    public function generikUpdate(Request $request, ObatGenerik $obatGenerik)
    {
        $validator = Validator::make($request->all(), [
            'kode_obat' => 'required|string|max:255|unique:obat_generik,kode_obat,' . $obatGenerik->id,
            'nama_generik' => 'required|string|max:255',
            'harga_jual' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->route('pemetaan-obat.generik')->withErrors($validator)->withInput();
        }

        $obatGenerik->update($this->drugData($validator->validated()));

        return redirect()->route('pemetaan-obat.generik')->with('success', 'Obat generik berhasil diperbarui!');
    }

    public function generikDestroy(ObatGenerik $obatGenerik)
    {
        if ($obatGenerik->pemetaan()->count() > 0) {
            return redirect()->route('pemetaan-obat.generik')->with('error', 'Obat generik tidak dapat dihapus karena masih memiliki pemetaan brand!');
        }

        $obatGenerik->delete();
        return redirect()->route('pemetaan-obat.generik')->with('success', 'Obat generik berhasil dihapus!');
    }

    public function brandIndex(Request $request)
    {
        $query = ObatBrand::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($w) => $w->where('kode_obat', 'ilike', "%{$search}%")
                ->orWhere('nama_brand', 'ilike', "%{$search}%"));
        }

        $perPage = in_array((int) $request->input('per_page', 10), [5, 10, 25, 50, 100])
            ? (int) $request->input('per_page', 10) : 10;

        $brandList = $query->withCount('pemetaan')->orderBy('kode_obat')->paginate($perPage)->withQueryString();

        $brandMap = collect($brandList->items())->mapWithKeys(fn($b) => [$b->id => [
            'id' => $b->id,
            'kode_obat' => $b->kode_obat,
            'nama_brand' => $b->nama_brand,
            'harga_jual' => $b->harga_jual,
        ]])->all();

        return view('pemetaan-obat.brand', compact('brandList', 'brandMap'));
    }

    public function brandStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_obat' => 'required|string|max:255|unique:obat_brand,kode_obat',
            'nama_brand' => 'required|string|max:255',
            'harga_jual' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->route('pemetaan-obat.brand')->withErrors($validator)->withInput();
        }

        ObatBrand::create($this->drugData($validator->validated()));

        return redirect()->route('pemetaan-obat.brand')->with('success', 'Obat brand berhasil ditambahkan!');
    }

    public function brandUpdate(Request $request, ObatBrand $obatBrand)
    {
        $validator = Validator::make($request->all(), [
            'kode_obat' => 'required|string|max:255|unique:obat_brand,kode_obat,' . $obatBrand->id,
            'nama_brand' => 'required|string|max:255',
            'harga_jual' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->route('pemetaan-obat.brand')->withErrors($validator)->withInput();
        }

        $obatBrand->update($this->drugData($validator->validated()));

        return redirect()->route('pemetaan-obat.brand')->with('success', 'Obat brand berhasil diperbarui!');
    }

    public function brandDestroy(ObatBrand $obatBrand)
    {
        if ($obatBrand->pemetaan()->count() > 0) {
            return redirect()->route('pemetaan-obat.brand')->with('error', 'Obat brand tidak dapat dihapus karena masih digunakan dalam pemetaan!');
        }

        $obatBrand->delete();
        return redirect()->route('pemetaan-obat.brand')->with('success', 'Obat brand berhasil dihapus!');
    }

    public function searchGenerik(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = ObatGenerik::query();
        if ($q !== '') {
            $query->where(fn($w) => $w->where('kode_obat', 'ilike', "%{$q}%")
                ->orWhere('nama_generik', 'ilike', "%{$q}%"));
        }

        $results = $query->orderBy('nama_generik')->limit(20)->get()->map(fn($g) => [
            'id' => $g->id,
            'kode_obat' => $g->kode_obat,
            'nama_generik' => $g->nama_generik,
            'harga_jual' => $g->harga_jual,
        ]);

        return response()->json(['results' => $results]);
    }

    public function searchBrand(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = ObatBrand::query();
        if ($q !== '') {
            $query->where(fn($w) => $w->where('kode_obat', 'ilike', "%{$q}%")
                ->orWhere('nama_brand', 'ilike', "%{$q}%"));
        }

        $results = $query->orderBy('nama_brand')->limit(20)->get()->map(fn($b) => [
            'id' => $b->id,
            'kode_obat' => $b->kode_obat,
            'nama_brand' => $b->nama_brand,
            'harga_jual' => $b->harga_jual,
        ]);

        return response()->json(['results' => $results]);
    }

    public function generikBrands(ObatGenerik $obatGenerik)
    {
        $obatGenerik->load('pemetaan.obatBrand');

        return response()->json([
            'generik' => [
                'id' => $obatGenerik->id,
                'kode_obat' => $obatGenerik->kode_obat,
                'nama_generik' => $obatGenerik->nama_generik,
                'harga_jual' => $obatGenerik->harga_jual,
            ],
            'brands' => $obatGenerik->pemetaan->map(fn($p) => [
                'id' => $p->id,
                'kode_obat' => $p->obatBrand->kode_obat,
                'nama_brand' => $p->obatBrand->nama_brand,
                'harga_jual' => $p->obatBrand->harga_jual,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validateMapping($request, null);

        if ($validator->fails()) {
            return redirect()
                ->route('pemetaan-obat.index', ['obat_generik_id' => $request->obat_generik_id])
                ->withErrors($validator)
                ->withInput();
        }

        PemetaanObat::create([
            'obat_generik_id' => $request->obat_generik_id,
            'obat_brand_id' => $request->obat_brand_id,
        ]);

        return redirect()
            ->route('pemetaan-obat.index', ['obat_generik_id' => $request->obat_generik_id])
            ->with('success', 'Pemetaan obat berhasil ditambahkan!');
    }

    public function update(Request $request, PemetaanObat $pemetaan)
    {
        $validator = $this->validateMapping($request, $pemetaan->id);

        $fallbackGenerik = $request->obat_generik_id ?? $pemetaan->obat_generik_id;

        if ($validator->fails()) {
            return redirect()
                ->route('pemetaan-obat.index', ['obat_generik_id' => $fallbackGenerik])
                ->withErrors($validator)
                ->withInput();
        }

        $pemetaan->update([
            'obat_generik_id' => $request->obat_generik_id,
            'obat_brand_id' => $request->obat_brand_id,
        ]);

        return redirect()
            ->route('pemetaan-obat.index', ['obat_generik_id' => $request->obat_generik_id])
            ->with('success', 'Pemetaan obat berhasil diperbarui!');
    }

    public function destroy(PemetaanObat $pemetaan)
    {
        $generikId = $pemetaan->obat_generik_id;
        $pemetaan->delete();

        return redirect()
            ->route('pemetaan-obat.index', ['obat_generik_id' => $generikId])
            ->with('success', 'Pemetaan obat berhasil dihapus!');
    }

    protected function drugData(array $validated): array
    {
        return [
            'kode_obat' => $validated['kode_obat'],
            'nama_generik' => $validated['nama_generik'] ?? $validated['nama_brand'] ?? null,
            'nama_brand' => $validated['nama_brand'] ?? null,
            'harga_jual' => isset($validated['harga_jual']) && $validated['harga_jual'] !== '' && $validated['harga_jual'] !== null
                ? (int) $validated['harga_jual']
                : null,
        ];
    }

    protected function validateMapping(Request $request, ?int $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'obat_generik_id' => 'required|exists:obat_generik,id',
            'obat_brand_id' => 'required|exists:obat_brand,id',
        ], [
            'obat_generik_id.required' => 'Pilih obat generik terlebih dahulu.',
            'obat_brand_id.required' => 'Pilih obat brand / paten.',
        ])->after(function ($validator) use ($request, $ignoreId) {
            $exists = PemetaanObat::where('obat_generik_id', $request->obat_generik_id)
                ->where('obat_brand_id', $request->obat_brand_id)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'obat_brand_id',
                    'Mapping obat ini sudah ada (duplikat tidak diizinkan).'
                );
            }
        });
    }
}