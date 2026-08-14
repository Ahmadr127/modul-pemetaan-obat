<?php

namespace App\Http\Controllers;

use App\Models\ObatBrand;
use App\Models\ObatGenerik;
use App\Models\PemetaanObat;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PemetaanObatController extends Controller
{
    private const GENERIK_SHEET = 'OBAT_GENERIK';

    private const BRAND_SHEET = 'PEMETAAN_BRAND';

    private const GENERIK_HEADERS = ['kode_obat', 'nama_generik', 'harga_jual'];

    private const BRAND_HEADERS = ['kode_generik', 'kode_brand', 'nama_brand', 'harga_brand'];

    private const MAX_UPLOAD_SIZE_KB = 5120;

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

        $obatGenerikList = ObatGenerik::orderBy('nama_generik')->get()->map(fn ($g) => [
            'id' => $g->id,
            'label' => $g->kode_obat.' - '.$g->nama_generik,
        ]);

        $obatBrandList = ObatBrand::orderBy('nama_brand')->get()->map(fn ($b) => [
            'id' => $b->id,
            'label' => $b->kode_obat.' - '.$b->nama_brand,
        ]);

        $pemetaanMap = $generik
            ? $pemetaan->mapWithKeys(fn ($p) => [$p->id => [
                'id' => $p->id,
                'generik_id' => $generik->id,
                'generik_label' => $generik->kode_obat.' - '.$generik->nama_generik,
                'brand_label' => $p->obatBrand->kode_obat.' - '.$p->obatBrand->nama_brand,
            ]])->all()
            : [];

        $pemetaanRows = $pemetaan->map(fn ($p) => [
            'id' => $p->id,
            'kode' => $p->obatBrand->kode_obat,
            'nama' => $p->obatBrand->nama_brand,
            'harga' => 'Rp '.number_format($p->obatBrand->harga_jual ?? 0, 0, ',', '.'),
        ])->values()->all();

        $mappingColumns = [
            ['key' => 'kode', 'label' => 'Kode'],
            ['key' => 'nama', 'label' => 'Nama Brand / Paten'],
            ['key' => 'harga', 'label' => 'Harga Jual'],
        ];

        $mappingActions = [
            ['type' => 'button', 'event' => 'open-edit', 'icon' => 'bi-pencil', 'label' => 'Edit Mapping'],
            ['type' => 'form', 'url' => str_replace('__ID__', '{id}', route('pemetaan-obat.destroy', ['pemetaan' => '__ID__'])), 'method' => 'DELETE', 'icon' => 'bi-trash', 'label' => 'Hapus Mapping', 'confirm' => 'Yakin ingin menghapus pemetaan ini?'],
        ];

        return view('pemetaan-obat.index', compact(
            'selectedGenerikId',
            'generik',
            'pemetaan',
            'obatGenerikList',
            'obatBrandList',
            'mappedBrandIds',
            'pemetaanMap',
            'pemetaanRows',
            'mappingColumns',
            'mappingActions'
        ));
    }

    public function generikIndex()
    {
        $generikList = ObatGenerik::withCount('pemetaan')->orderBy('kode_obat')->get();

        $generikRows = $generikList->map(fn ($g) => [
            'id' => $g->id,
            'kode' => $g->kode_obat,
            'nama' => $g->nama_generik,
            'harga' => 'Rp '.number_format($g->harga_jual ?? 0, 0, ',', '.'),
            'brand' => $g->pemetaan_count.' brand',
        ])->values()->all();

        $generikMap = $generikList->mapWithKeys(fn ($g) => [$g->id => [
            'id' => $g->id,
            'kode_obat' => $g->kode_obat,
            'nama_generik' => $g->nama_generik,
            'harga_jual' => $g->harga_jual,
        ]])->all();

        $generikColumns = [
            ['key' => 'kode', 'label' => 'Kode'],
            ['key' => 'nama', 'label' => 'Nama Generik'],
            ['key' => 'harga', 'label' => 'Harga Jual'],
            ['key' => 'brand', 'label' => 'Brand'],
        ];

        $generikActions = [
            ['type' => 'button', 'event' => 'open-edit', 'icon' => 'bi-pencil', 'label' => 'Edit'],
            ['type' => 'form', 'url' => str_replace('__ID__', '{id}', route('pemetaan-obat.generik.destroy', ['obat_generik' => '__ID__'])), 'method' => 'DELETE', 'icon' => 'bi-trash', 'label' => 'Hapus', 'confirm' => 'Yakin ingin menghapus obat generik ini?'],
        ];

        return view('pemetaan-obat.generik', compact('generikRows', 'generikMap', 'generikColumns', 'generikActions'));
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
            'kode_obat' => 'required|string|max:255|unique:obat_generik,kode_obat,'.$obatGenerik->id,
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

    public function brandIndex()
    {
        $brandList = ObatBrand::withCount('pemetaan')->orderBy('kode_obat')->get();

        $brandRows = $brandList->map(fn ($b) => [
            'id' => $b->id,
            'kode' => $b->kode_obat,
            'nama' => $b->nama_brand,
            'harga' => 'Rp '.number_format($b->harga_jual ?? 0, 0, ',', '.'),
            'pemetaan' => $b->pemetaan_count.' generik',
        ])->values()->all();

        $brandMap = $brandList->mapWithKeys(fn ($b) => [$b->id => [
            'id' => $b->id,
            'kode_obat' => $b->kode_obat,
            'nama_brand' => $b->nama_brand,
            'harga_jual' => $b->harga_jual,
        ]])->all();

        $brandColumns = [
            ['key' => 'kode', 'label' => 'Kode'],
            ['key' => 'nama', 'label' => 'Nama Brand / Paten'],
            ['key' => 'harga', 'label' => 'Harga Jual'],
            ['key' => 'pemetaan', 'label' => 'Pemetaan'],
        ];

        $brandActions = [
            ['type' => 'button', 'event' => 'open-edit', 'icon' => 'bi-pencil', 'label' => 'Edit'],
            ['type' => 'form', 'url' => str_replace('__ID__', '{id}', route('pemetaan-obat.brand.destroy', ['obat_brand' => '__ID__'])), 'method' => 'DELETE', 'icon' => 'bi-trash', 'label' => 'Hapus', 'confirm' => 'Yakin ingin menghapus obat brand ini?'],
        ];

        return view('pemetaan-obat.brand', compact('brandRows', 'brandMap', 'brandColumns', 'brandActions'));
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
            'kode_obat' => 'required|string|max:255|unique:obat_brand,kode_obat,'.$obatBrand->id,
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
            $query->where(fn ($w) => $w->where('kode_obat', 'ilike', "%{$q}%")
                ->orWhere('nama_generik', 'ilike', "%{$q}%"));
        }

        $results = $query->orderBy('nama_generik')->limit(20)->get()->map(fn ($g) => [
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
            $query->where(fn ($w) => $w->where('kode_obat', 'ilike', "%{$q}%")
                ->orWhere('nama_brand', 'ilike', "%{$q}%"));
        }

        $results = $query->orderBy('nama_brand')->limit(20)->get()->map(fn ($b) => [
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
            'brands' => $obatGenerik->pemetaan->map(fn ($p) => [
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

    // ------------------------------------------------------------------
    // Import Excel
    // ------------------------------------------------------------------

    public function importTemplate()
    {
        $spreadsheet = new Spreadsheet;

        $generikSheet = $spreadsheet->getActiveSheet();
        $generikSheet->setTitle(self::GENERIK_SHEET);
        $generikSheet->fromArray([self::GENERIK_HEADERS], null, 'A1');
        $this->styleHeaderRow($generikSheet, 1, count(self::GENERIK_HEADERS));
        $generikSheet->getColumnDimension('A')->setAutoSize(true);
        $generikSheet->getColumnDimension('B')->setAutoSize(true);
        $generikSheet->getColumnDimension('C')->setAutoSize(true);
        $generikSheet->setCellValue('A3', 'Header berada di baris 1. Isi data mulai baris 2. Kode obat diperlakukan sebagai teks, harga harus berupa angka.');
        $generikSheet->getStyle('A3:C3')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');

        $brandSheet = $spreadsheet->createSheet();
        $brandSheet->setTitle(self::BRAND_SHEET);
        $brandSheet->fromArray([self::BRAND_HEADERS], null, 'A1');
        $this->styleHeaderRow($brandSheet, 1, count(self::BRAND_HEADERS));
        $brandSheet->getColumnDimension('A')->setAutoSize(true);
        $brandSheet->getColumnDimension('B')->setAutoSize(true);
        $brandSheet->getColumnDimension('C')->setAutoSize(true);
        $brandSheet->getColumnDimension('D')->setAutoSize(true);
        $brandSheet->setCellValue('A4', 'Satu generik dapat memiliki banyak brand. Satu brand hanya boleh dipetakan ke satu generik (mapping aktif). Brand yang belum ada di database akan otomatis dibuat dari file ini.');
        $brandSheet->getStyle('A4:D4')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');

        $contohSheet = $spreadsheet->createSheet();
        $contohSheet->setTitle('CONTOH');
        $contohSheet->setCellValue('A1', 'Berikut adalah CONTOH data untuk referensi. Data pada sheet CONTOH TIDAK akan diimport.');
        $contohSheet->getStyle('A1:F1')->getFont()->setBold(true)->setSize(12);
        $contohSheet->setCellValue('A3', 'Sheet OBAT_GENERIK');
        $contohSheet->getStyle('A3:F3')->getFont()->setBold(true)->getColor()->setRGB('007774');
        $contohSheet->fromArray([self::GENERIK_HEADERS], null, 'A4');
        $contohSheet->fromArray([
            ['OBT00006', 'ACETYLCISTEIN INF US 200 MG/ML - JS', 298202],
            ['OBT00015', 'ACYCLOVIR CREAM 5 GR - JS', 13352],
        ], null, 'A5');
        $contohSheet->setCellValue('A8', 'Sheet PEMETAAN_BRAND');
        $contohSheet->getStyle('A8:F8')->getFont()->setBold(true)->getColor()->setRGB('007774');
        $contohSheet->fromArray([self::BRAND_HEADERS], null, 'A9');
        $contohSheet->fromArray([
            ['OBT00006', 'OBT0119', 'RESFAR 30 ML INJ', 298202],
            ['OBT00006', 'OBT0494', 'FLUIMUCIL 10% AMPUL 300 MG/3 ML [ HA ]', 96308],
            ['OBT00015', 'OBT02033', 'ZOTER CREAM', 97403],
        ], null, 'A10');

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-pemetaan-obat.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importPreview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:'.self::MAX_UPLOAD_SIZE_KB],
        ], [
            'file.required' => 'Pilih file Excel terlebih dahulu.',
            'file.mimes' => 'File harus berupa Excel (.xlsx / .xls).',
            'file.max' => 'Ukuran file melebihi batas '.self::MAX_UPLOAD_SIZE_KB.' KB.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('pemetaan-obat.index')->withErrors($validator);
        }

        try {
            $parsed = $this->parseImportFile($request->file('file'));
        } catch (\Throwable $e) {
            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Gagal membaca file Excel: '.$e->getMessage());
        }

        if (! empty($parsed['errors'])) {
            return redirect()->route('pemetaan-obat.index')
                ->with('error', implode(' ', $parsed['errors']));
        }

        $built = $this->buildImportRows($parsed);

        if ($built['summary']['total'] === 0) {
            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'File tidak memiliki data pada sheet '.self::GENERIK_SHEET.' atau '.self::BRAND_SHEET.'.');
        }

        $tempDir = Storage::disk('local')->path('import-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $basename = 'import_pemetaan_obat_'.md5(uniqid('', true)).'.xlsx';
        copy($request->file('file')->getPathname(), $tempDir.'/'.$basename);

        $request->session()->put('import_temp_file', $basename);

        return view('pemetaan-obat.import-preview', [
            'rows' => $built['rows'],
            'summary' => $built['summary'],
            'columns' => [
                ['key' => 'sheet', 'label' => 'Sheet'],
                ['key' => 'row', 'label' => 'Baris'],
                ['key' => 'kode', 'label' => 'Kode'],
                ['key' => 'nama', 'label' => 'Nama'],
                ['key' => 'harga', 'label' => 'Harga'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'message', 'label' => 'Keterangan'],
            ],
            'tableRows' => $this->toPreviewRows($built['rows']),
            'fileName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function importConfirm(Request $request)
    {
        $tempFileName = $request->session()->pull('import_temp_file');

        if (! $tempFileName || ! preg_match('/^import_pemetaan_obat_[a-f0-9]{32}\.xlsx$/', $tempFileName)) {
            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Sesi import tidak valid. Silakan upload ulang.');
        }

        $tempPath = Storage::disk('local')->path('import-temp').'/'.$tempFileName;

        if (! file_exists($tempPath)) {
            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'File sementara tidak ditemukan. Silakan upload ulang.');
        }

        try {
            $parsed = $this->parseImportFile($tempPath);
        } catch (\Throwable $e) {
            @unlink($tempPath);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Gagal membaca file: '.$e->getMessage());
        }

        if (! empty($parsed['errors'])) {
            @unlink($tempPath);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', implode(' ', $parsed['errors']));
        }

        $built = $this->buildImportRows($parsed);

        if ($built['summary']['total'] === 0) {
            @unlink($tempPath);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'File tidak memiliki data.');
        }

        DB::beginTransaction();

        try {
            $result = $this->performImport($built);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            @unlink($tempPath);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Terjadi kesalahan saat import. Data dibatalkan seluruhnya: '.$e->getMessage());
        }

        @unlink($tempPath);

        $message = 'Import berhasil. Data diproses: '.$result['total']
            .'. Berhasil: '.$result['imported']
            .'. Dilewati: '.$result['skipped']
            .'. Gagal: '.$result['failed'].'.';

        if ($result['failed'] > 0) {
            return redirect()->route('pemetaan-obat.index')->with('warning', $message);
        }

        return redirect()->route('pemetaan-obat.index')->with('success', $message);
    }

    private function parseImportFile($file): array
    {
        $path = $file instanceof UploadedFile ? $file->getPathname() : $file;

        $spreadsheet = IOFactory::load($path);

        $errors = [];

        $generikSheet = $spreadsheet->getSheetByName(self::GENERIK_SHEET);
        if ($generikSheet === null) {
            $errors[] = 'Sheet '.self::GENERIK_SHEET.' tidak ditemukan.';
            $generikRows = [];
        } else {
            $generikRows = $this->readSheet($generikSheet, self::GENERIK_HEADERS, self::GENERIK_SHEET, $errors);
        }

        $brandSheet = $spreadsheet->getSheetByName(self::BRAND_SHEET);
        if ($brandSheet === null) {
            $errors[] = 'Sheet '.self::BRAND_SHEET.' tidak ditemukan.';
            $brandRows = [];
        } else {
            $brandRows = $this->readSheet($brandSheet, self::BRAND_HEADERS, self::BRAND_SHEET, $errors);
        }

        return compact('generikRows', 'brandRows', 'errors');
    }

    private function readSheet(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $requiredHeaders, string $sheetName, array &$errors): array
    {
        $rows = $sheet->toArray(null, false, false);

        if (count($rows) === 0) {
            return [];
        }

        $headers = array_shift($rows);

        $headerMap = [];
        foreach ($headers as $index => $header) {
            $key = str_replace("\xEF\xBB\xBF", '', strtolower(trim((string) $header)));
            if (in_array($key, $requiredHeaders, true)) {
                $headerMap[$key] = $index;
            }
        }

        foreach ($requiredHeaders as $required) {
            if (! isset($headerMap[$required])) {
                $errors[] = 'Header sheet '.$sheetName.' tidak sesuai. Kolom wajib: '.implode(', ', $requiredHeaders).'.';

                return [];
            }
        }

        $parsed = [];
        foreach ($rows as $i => $row) {
            $values = $this->extractRow($row, $headerMap, $requiredHeaders);
            if ($this->isEmptyRow($values)) {
                continue;
            }

            $parsed[] = [
                'type' => $sheetName === self::GENERIK_SHEET ? 'generik' : 'brand',
                'row' => $i + 2,
                'values' => $values,
            ];
        }

        return $parsed;
    }

    private function extractRow(array $row, array $headerMap, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $this->cellValue($row, $headerMap, $field);
        }

        return $values;
    }

    private function cellValue(array $row, array $headerMap, string $key): mixed
    {
        if (! isset($headerMap[$key]) || ! array_key_exists($headerMap[$key], $row)) {
            return '';
        }

        $value = $row[$headerMap[$key]];

        if ($value === null) {
            return '';
        }

        if (is_numeric($value) && ! is_string($value)) {
            return $value;
        }

        return trim(str_replace("\xEF\xBB\xBF", '', (string) $value));
    }

    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    private function buildImportRows(array $parsed): array
    {
        $generikByKode = ObatGenerik::pluck('id', 'kode_obat')->all();
        $generikNames = ObatGenerik::pluck('nama_generik', 'kode_obat')->all();
        $brandByKode = ObatBrand::pluck('id', 'kode_obat')->all();
        $brandNames = ObatBrand::pluck('nama_brand', 'kode_obat')->all();
        $brandToGenerik = PemetaanObat::pluck('obat_generik_id', 'obat_brand_id')->all();
        $existingPairs = PemetaanObat::get(['obat_generik_id', 'obat_brand_id'])
            ->map(fn ($p) => $p->obat_generik_id.':'.$p->obat_brand_id)
            ->all();

        $rows = [];
        $seenGenerikCodes = [];
        $validGenerikCodes = [];

        foreach ($parsed['generikRows'] as $raw) {
            $row = $this->validateGenerikRow($raw, $generikByKode, $generikNames, $seenGenerikCodes);
            if ($row['status'] === 'new') {
                $validGenerikCodes[$row['kode']] = true;
            }
            $rows[] = $row;
        }

        $seenPairs = [];
        $seenBrandGenerik = [];
        foreach ($parsed['brandRows'] as $raw) {
            $rows[] = $this->validateBrandRow(
                $raw,
                $generikByKode,
                $brandByKode,
                $brandNames,
                $brandToGenerik,
                $existingPairs,
                $validGenerikCodes,
                $seenPairs,
                $seenBrandGenerik
            );
        }

        $statuses = collect($rows)->pluck('status')->countBy();

        $summary = [
            'total' => count($rows),
            'new' => $statuses->get('new', 0),
            'exists' => $statuses->get('exists', 0),
            'duplicate' => $statuses->get('duplicate', 0),
            'error' => $statuses->get('error', 0),
            'warning' => collect($rows)->filter(fn ($r) => count($r['warnings']) > 0)->count(),
        ];

        return compact('rows', 'summary');
    }

    private function validateGenerikRow(array $raw, array $generikByKode, array $generikNames, array &$seenGenerikCodes): array
    {
        $values = $raw['values'];

        $kode = trim((string) ($values['kode_obat'] ?? ''));
        $nama = trim((string) ($values['nama_generik'] ?? ''));
        $harga = $values['harga_jual'] ?? '';

        $errors = [];
        $warnings = [];

        if ($kode === '') {
            $errors[] = 'kode_obat wajib diisi.';
        }

        if ($nama === '') {
            $errors[] = 'nama_generik wajib diisi.';
        }

        if ($harga !== '' && ! is_numeric($harga)) {
            $errors[] = 'harga_jual harus berupa angka.';
        }

        $status = 'error';
        $generikId = null;

        if (empty($errors)) {
            if (isset($seenGenerikCodes[$kode])) {
                $status = 'duplicate';
                $warnings[] = 'kode_obat duplikat dalam file (baris '.$seenGenerikCodes[$kode].').';
            } elseif (isset($generikByKode[$kode])) {
                $status = 'exists';
                $generikId = $generikByKode[$kode];
                if ($generikNames[$kode] !== $nama) {
                    $warnings[] = 'Nama generik berbeda dengan data existing, data existing tidak akan diubah.';
                }
            } else {
                $status = 'new';
            }
        }

        return [
            'type' => 'generik',
            'row' => $raw['row'],
            'kode' => $kode,
            'nama' => $nama,
            'harga' => $harga === '' ? null : (int) $harga,
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'message' => $this->buildMessage($errors, $warnings),
            'generik_id' => $generikId,
        ];
    }

    private function validateBrandRow(
        array $raw,
        array $generikByKode,
        array $brandByKode,
        array $brandNames,
        array $brandToGenerik,
        array $existingPairs,
        array $validGenerikCodes,
        array &$seenPairs,
        array &$seenBrandGenerik
    ): array {
        $values = $raw['values'];

        $kodeGenerik = trim((string) ($values['kode_generik'] ?? ''));
        $kodeBrand = trim((string) ($values['kode_brand'] ?? ''));
        $namaBrand = trim((string) ($values['nama_brand'] ?? ''));
        $hargaBrand = $values['harga_brand'] ?? '';

        $errors = [];
        $warnings = [];

        if ($kodeGenerik === '') {
            $errors[] = 'kode_generik wajib diisi.';
        }

        if ($kodeBrand === '') {
            $errors[] = 'kode_brand wajib diisi.';
        }

        if ($hargaBrand !== '' && ! is_numeric($hargaBrand)) {
            $errors[] = 'harga_brand harus berupa angka.';
        }

        $generikId = null;
        $brandId = null;
        $brandIsNew = false;

        if ($kodeGenerik !== '') {
            if (isset($generikByKode[$kodeGenerik])) {
                $generikId = $generikByKode[$kodeGenerik];
            } elseif (! isset($validGenerikCodes[$kodeGenerik])) {
                $errors[] = 'kode generik "'.$kodeGenerik.'" tidak ditemukan.';
            }
        }

        if ($kodeBrand !== '') {
            if (isset($brandByKode[$kodeBrand])) {
                $brandId = $brandByKode[$kodeBrand];
                if ($namaBrand !== '' && $brandNames[$kodeBrand] !== $namaBrand) {
                    $warnings[] = 'Nama brand berbeda dengan data existing, data existing tidak akan diubah.';
                }
            } else {
                $brandIsNew = true;
                if ($namaBrand === '') {
                    $errors[] = 'nama_brand wajib diisi (brand "'.$kodeBrand.'" belum ada di database dan akan dibuat).';
                }
            }
        }

        $status = 'error';

        if (empty($errors)) {
            $pairKey = $kodeGenerik.'|'.$kodeBrand;

            if (isset($seenPairs[$pairKey])) {
                $status = 'duplicate';
                $warnings[] = 'Pasangan kode generik + kode brand sudah ada di file (baris '.$seenPairs[$pairKey].').';
            } elseif (isset($seenBrandGenerik[$kodeBrand]) && $seenBrandGenerik[$kodeBrand] !== $kodeGenerik) {
                $errors[] = 'Brand "'.$kodeBrand.'" sudah terpetakan ke obat generik lain di file (baris '.$seenBrandGenerik[$kodeBrand].').';
            } else {
                $seenPairs[$pairKey] = $raw['row'];
                $seenBrandGenerik[$kodeBrand] = $kodeGenerik;

                if ($brandId !== null && isset($brandToGenerik[$brandId])) {
                    $existingGenerikId = (int) $brandToGenerik[$brandId];
                    if ($generikId !== null && $existingGenerikId === (int) $generikId) {
                        $status = 'exists';
                    } else {
                        $errors[] = 'Brand "'.$kodeBrand.'" sudah terpetakan ke obat generik lain.';
                    }
                } elseif ($brandId !== null && $generikId !== null) {
                    $status = in_array($generikId.':'.$brandId, $existingPairs, true) ? 'exists' : 'new';
                } else {
                    $status = 'new';
                }
            }
        }

        return [
            'type' => 'brand',
            'row' => $raw['row'],
            'kode' => $kodeBrand,
            'nama' => $namaBrand,
            'harga' => $hargaBrand === '' ? null : (int) $hargaBrand,
            'kode_generik' => $kodeGenerik,
            'kode_brand' => $kodeBrand,
            'brand_is_new' => $brandIsNew,
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warnings,
            'message' => $this->buildMessage($errors, $warnings),
            'generik_id' => $generikId,
            'brand_id' => $brandId,
        ];
    }

    private function performImport(array $built): array
    {
        $rows = $built['rows'];
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $generikIdByKode = ObatGenerik::pluck('id', 'kode_obat')->all();

        foreach ($rows as $row) {
            if ($row['type'] !== 'generik') {
                continue;
            }

            if ($row['status'] === 'error') {
                $failed++;

                continue;
            }

            if (in_array($row['status'], ['exists', 'duplicate'], true)) {
                $skipped++;

                continue;
            }

            $obatGenerik = ObatGenerik::create([
                'kode_obat' => $row['kode'],
                'nama_generik' => $row['nama'],
                'harga_jual' => $row['harga'],
            ]);
            $generikIdByKode[$row['kode']] = $obatGenerik->id;
            $imported++;
        }

        foreach ($rows as $row) {
            if ($row['type'] !== 'brand') {
                continue;
            }

            if ($row['status'] === 'error') {
                $failed++;

                continue;
            }

            if (in_array($row['status'], ['exists', 'duplicate'], true)) {
                $skipped++;

                continue;
            }

            $generikId = $generikIdByKode[$row['kode_generik']] ?? null;

            if ($generikId === null) {
                $failed++;

                continue;
            }

            $brandId = $row['brand_id'];
            if ($brandId === null) {
                $obatBrand = ObatBrand::firstOrCreate(
                    ['kode_obat' => $row['kode_brand']],
                    [
                        'nama_brand' => $row['nama'],
                        'harga_jual' => $row['harga'],
                    ]
                );
                $brandId = $obatBrand->id;
            }

            PemetaanObat::firstOrCreate([
                'obat_generik_id' => $generikId,
                'obat_brand_id' => $brandId,
            ]);
            $imported++;
        }

        return [
            'total' => count($rows),
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    private function toPreviewRows(array $rows): array
    {
        $labels = ['new' => 'Baru', 'exists' => 'Sudah Ada', 'duplicate' => 'Duplicate', 'error' => 'Error'];

        return collect($rows)->map(function ($row) use ($labels) {
            $kode = $row['type'] === 'generik'
                ? ($row['kode'] ?: '-')
                : (($row['kode_generik'] ?: '-').' → '.($row['kode_brand'] ?: '-'));

            return [
                'sheet' => $row['type'] === 'generik' ? self::GENERIK_SHEET : self::BRAND_SHEET,
                'row' => $row['row'],
                'kode' => $kode,
                'nama' => $row['nama'] ?: '-',
                'harga' => $row['harga'] === null || $row['harga'] === '' ? '-' : number_format((int) $row['harga'], 0, ',', '.'),
                'status' => $labels[$row['status']] ?? $row['status'],
                'message' => $row['message'] ?: '-',
            ];
        })->values()->all();
    }

    private function buildMessage(array $errors, array $warnings): string
    {
        $parts = [];
        foreach ($errors as $error) {
            $parts[] = 'Error: '.$error;
        }
        foreach ($warnings as $warning) {
            $parts[] = 'Peringatan: '.$warning;
        }

        return implode(' | ', $parts);
    }

    private function styleHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, int $count): void
    {
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($count);

        $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '007774']],
        ]);
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
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
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
