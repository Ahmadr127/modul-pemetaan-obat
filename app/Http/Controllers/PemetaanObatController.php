<?php

namespace App\Http\Controllers;

use App\Models\ImportLog;
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
    private const HEADER_FIELDS = [
        'kode generik' => 'kode_generik',
        'nama generik/kandungan' => 'nama_generik',
        'harga generik' => 'harga_generik',
        'kode brand' => 'kode_brand',
        'nama brand' => 'nama_brand',
        'harga brand' => 'harga_brand',
    ];

    private const HEADER_LABELS = [
        'kode_generik' => 'Kode Generik',
        'nama_generik' => 'Nama Generik/Kandungan',
        'harga_generik' => 'Harga Generik',
        'kode_brand' => 'Kode Brand',
        'nama_brand' => 'Nama Brand',
        'harga_brand' => 'Harga Brand',
    ];

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
    // Import Excel (single sheet + current generik + grouping)
    // ------------------------------------------------------------------

public function importTemplate()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pemetaan Obat');
        $sheet->fromArray([array_values(self::HEADER_LABELS)], null, 'A1');
        $this->styleHeaderRow($sheet, 1, count(self::HEADER_LABELS));

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->setCellValue('H1', 'Petunjuk: baris 1 adalah header. Isi data mulai baris 2. Kode Generik wajib diisi pada baris pertama sebuah grup; kosongkan pada baris brand lanjutan (brand mengikuti generik aktif). Harga harus berupa angka. Jangan hapus kolom.');
        $sheet->getStyle('H1')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-pemetaan-obat.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importLogIndex()
    {
        $logList = ImportLog::with('user')->orderByDesc('created_at')->get();

        $logRows = $logList->map(function (ImportLog $log) {
            $status = $log->status;
            $labels = [
                ImportLog::STATUS_SUCCESS => ['Berhasil', 'bg-emerald-100 text-emerald-700'],
                ImportLog::STATUS_WARNING => ['Peringatan', 'bg-amber-100 text-amber-700'],
                ImportLog::STATUS_ERROR => ['Gagal', 'bg-red-100 text-red-700'],
            ];
            [$statusLabel, $statusColor] = $labels[$status] ?? [$status, 'bg-gray-100 text-gray-600'];

            return [
                'id' => $log->id,
                'waktu' => $log->created_at?->format('d M Y H:i:s'),
                'file' => $log->file_name,
                'user' => $log->user?->name ?: $log->user?->username ?: '-',
                'status' => $statusLabel,
                'status_color' => $statusColor,
                'total' => $log->total,
                'imported' => $log->imported,
                'skipped' => $log->skipped,
                'failed' => $log->failed,
                'message' => $log->errors ? implode(' | ', $log->errors) : '-',
            ];
        })->values()->all();

        $logColumns = [
            ['key' => 'waktu', 'label' => 'Waktu'],
            ['key' => 'file', 'label' => 'File'],
            ['key' => 'user', 'label' => 'User'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'total', 'label' => 'Total'],
            ['key' => 'imported', 'label' => 'Import'],
            ['key' => 'skipped', 'label' => 'Skipped'],
            ['key' => 'failed', 'label' => 'Failed'],
            ['key' => 'message', 'label' => 'Pesan Error / Keterangan'],
        ];

        return view('pemetaan-obat.import-log', compact('logRows', 'logColumns'));
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
            $this->recordImportLog(
                ImportLog::STATUS_ERROR,
                $request->file('file')?->getClientOriginalName() ?: 'import.xlsx',
                [],
                $validator->errors()->all()
            );

            return redirect()->route('pemetaan-obat.index')->withErrors($validator);
        }

        try {
            $parsed = $this->parseImportFile($request->file('file'));
        } catch (\Throwable $e) {
            $this->recordImportLog(
                ImportLog::STATUS_ERROR,
                $request->file('file')->getClientOriginalName(),
                [],
                ['Gagal membaca file Excel: '.$e->getMessage()]
            );

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Gagal membaca file Excel: '.$e->getMessage());
        }

        if (! empty($parsed['errors'])) {
            $this->recordImportLog(
                ImportLog::STATUS_ERROR,
                $request->file('file')->getClientOriginalName(),
                [],
                $parsed['errors']
            );

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Import gagal: '.implode(' ', $parsed['errors']));
        }

        $grouped = $this->groupRows($parsed['rows'], $parsed['headerMap']);
        $built = $this->buildImportRows($grouped);

        if ($built['summary']['total'] === 0) {
            $this->recordImportLog(
                ImportLog::STATUS_ERROR,
                $request->file('file')->getClientOriginalName(),
                [],
                ['File tidak memiliki data.']
            );

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'File tidak memiliki data.');
        }

        $tempDir = Storage::disk('local')->path('import-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $basename = 'import_pemetaan_obat_'.md5(uniqid('', true)).'.xlsx';
        copy($request->file('file')->getPathname(), $tempDir.'/'.$basename);

        $request->session()->put('import_temp_file', $basename);
        $request->session()->put('import_temp_name', $request->file('file')->getClientOriginalName());

        return view('pemetaan-obat.import-preview', [
            'groups' => $built['groups'],
            'orphans' => $built['orphans'],
            'summary' => $built['summary'],
            'fileName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

public function importConfirm(Request $request)
    {
        $tempFileName = $request->session()->pull('import_temp_file');
        $tempFileNameOriginal = $request->session()->pull('import_temp_name', 'import.xlsx');

        if (! $tempFileName || ! preg_match('/^import_pemetaan_obat_[a-f0-9]{32}\.xlsx$/', $tempFileName)) {
            $this->recordImportLog(ImportLog::STATUS_ERROR, $tempFileNameOriginal, [], ['Sesi import tidak valid.']);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Sesi import tidak valid. Silakan upload ulang.');
        }

        $tempPath = Storage::disk('local')->path('import-temp').'/'.$tempFileName;

        if (! file_exists($tempPath)) {
            $this->recordImportLog(ImportLog::STATUS_ERROR, $tempFileNameOriginal, [], ['File sementara tidak ditemukan.']);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'File sementara tidak ditemukan. Silakan upload ulang.');
        }

        try {
            $parsed = $this->parseImportFile($tempPath);
        } catch (\Throwable $e) {
            @unlink($tempPath);
            $this->recordImportLog(ImportLog::STATUS_ERROR, $tempFileNameOriginal, [], ['Gagal membaca file: '.$e->getMessage()]);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Gagal membaca file: '.$e->getMessage());
        }

        if (! empty($parsed['errors'])) {
            @unlink($tempPath);
            $this->recordImportLog(ImportLog::STATUS_ERROR, $tempFileNameOriginal, [], $parsed['errors']);

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Import gagal: '.implode(' ', $parsed['errors']));
        }

        $grouped = $this->groupRows($parsed['rows'], $parsed['headerMap']);
        $built = $this->buildImportRows($grouped);

        if ($built['summary']['total'] === 0) {
            @unlink($tempPath);
            $this->recordImportLog(ImportLog::STATUS_ERROR, $tempFileNameOriginal, [], ['File tidak memiliki data.']);

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
            $this->recordImportLog(
                ImportLog::STATUS_ERROR,
                $tempFileNameOriginal,
                ['total' => $built['summary']['total']],
                ['Terjadi kesalahan saat import. Data dibatalkan seluruhnya: '.$e->getMessage()]
            );

            return redirect()->route('pemetaan-obat.index')
                ->with('error', 'Terjadi kesalahan saat import. Data dibatalkan seluruhnya: '.$e->getMessage());
        }

        @unlink($tempPath);

        $this->recordImportLog(
            $result['failed'] > 0 ? ImportLog::STATUS_WARNING : ImportLog::STATUS_SUCCESS,
            $tempFileNameOriginal,
            $result
        );

        $message = 'Import berhasil. Data diproses: '.$result['total']
            .'. Berhasil: '.$result['imported']
            .'. Dilewati: '.$result['skipped']
            .'. Gagal: '.$result['failed'].'.';

        if ($result['failed'] > 0) {
            return redirect()->route('pemetaan-obat.index')->with('warning', $message);
        }

        return redirect()->route('pemetaan-obat.index')->with('success', $message);
    }

    private function recordImportLog(string $status, string $fileName, array $counts, array $errors = []): void
    {
        try {
            ImportLog::create([
                'user_id' => auth()->id(),
                'file_name' => $fileName,
                'status' => $status,
                'total' => $counts['total'] ?? 0,
                'imported' => $counts['imported'] ?? 0,
                'skipped' => $counts['skipped'] ?? 0,
                'failed' => $counts['failed'] ?? 0,
                'errors' => $errors ?: null,
            ]);
        } catch (\Throwable) {
            // Logging tidak boleh mengganggu alur import.
        }
    }

    private function parseImportFile($file): array
    {
        $path = $file instanceof UploadedFile ? $file->getPathname() : $file;

        $spreadsheet = IOFactory::load($path);

        $headerMap = null;
        $rawRows = [];
        $bestMissing = array_values(self::HEADER_LABELS);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, false, false);
            if (empty($rows)) {
                continue;
            }

            $map = $this->mapHeaders($rows[0]);
            if (empty($map['missing'])) {
                $headerMap = $map['found'];
                $rawRows = array_slice($rows, 1);
                break;
            }

            if (count($map['missing']) < count($bestMissing)) {
                $bestMissing = $map['missing'];
            }
        }

        if ($headerMap === null) {
            $errors = [];
            foreach ($bestMissing as $label) {
                $errors[] = 'Kolom "'.$label.'" tidak ditemukan.';
            }

            return ['errors' => $errors, 'rows' => [], 'headerMap' => null];
        }

        return ['errors' => [], 'rows' => $rawRows, 'headerMap' => $headerMap];
    }

    private function mapHeaders(array $headerRow): array
    {
        $found = [];
        foreach ($headerRow as $index => $header) {
            $key = strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $header)));
            if (isset(self::HEADER_FIELDS[$key]) && ! isset($found[self::HEADER_FIELDS[$key]])) {
                $found[self::HEADER_FIELDS[$key]] = $index;
            }
        }

        $missing = [];
        foreach (self::HEADER_LABELS as $field => $label) {
            if (! isset($found[$field])) {
                $missing[] = $label;
            }
        }

        return ['found' => $found, 'missing' => $missing];
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

    private function extractValues(array $row, array $headerMap): array
    {
        $values = [];
        foreach (array_keys(self::HEADER_LABELS) as $field) {
            $values[$field] = $this->cellValue($row, $headerMap, $field);
        }

        return $values;
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

    private function normalizeNumeric($value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $str = trim((string) $value);
        if ($str === '') {
            return null;
        }

        // grouping ribuan Indonesia: 298.202 atau 1.234.567
        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $str)) {
            return (int) str_replace('.', '', $str);
        }

        // grouping ribuan US: 298,202 atau 1,234,567
        if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $str)) {
            return (int) str_replace(',', '', $str);
        }

        if (is_numeric($str)) {
            return (int) $str;
        }

        return $str;
    }

    private function groupRows(array $rawRows, array $headerMap): array
    {
        $groups = [];
        $orphans = [];
        $seenGenerik = [];
        $currentIndex = null;
        $totalRows = 0;

        foreach ($rawRows as $i => $row) {
            $values = $this->extractValues($row, $headerMap);

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $totalRows++;
            $excelRow = $i + 2;

            $kodeGenerik = trim((string) ($values['kode_generik'] ?? ''));
            $namaGenerik = trim((string) ($values['nama_generik'] ?? ''));
            $hargaGenerik = $values['harga_generik'] ?? '';
            $kodeBrand = trim((string) ($values['kode_brand'] ?? ''));
            $namaBrand = trim((string) ($values['nama_brand'] ?? ''));
            $hargaBrand = $values['harga_brand'] ?? '';
            $hasBrand = $kodeBrand !== '' || $namaBrand !== '' || $hargaBrand !== '';

            if ($kodeGenerik !== '') {
                if (isset($seenGenerik[$kodeGenerik])) {
                    $currentIndex = $seenGenerik[$kodeGenerik];
                    if ($groups[$currentIndex]['nama'] !== $namaGenerik) {
                        $groups[$currentIndex]['warnings'][] = 'Baris '.$excelRow.': kode generik "'.$kodeGenerik.'" sudah muncul sebelumnya di file dengan nama berbeda; data pertama yang digunakan.';
                    }
                } else {
                    $currentIndex = count($groups);
                    $groups[] = [
                        'row' => $excelRow,
                        'kode' => $kodeGenerik,
                        'nama' => $namaGenerik,
                        'harga_raw' => $hargaGenerik,
                        'harga' => null,
                        'brands' => [],
                        'errors' => [],
                        'warnings' => [],
                        'status' => null,
                        'generik_id' => null,
                    ];
                    $seenGenerik[$kodeGenerik] = $currentIndex;
                }
            } elseif ($currentIndex === null) {
                $orphans[] = [
                    'row' => $excelRow,
                    'kode' => $kodeBrand,
                    'nama' => $namaBrand,
                    'errors' => ['Kode generik kosong dan belum ada generik aktif (baris brand tanpa parent generik).'],
                ];
                continue;
            }

            if ($hasBrand) {
                $groups[$currentIndex]['brands'][] = [
                    'row' => $excelRow,
                    'kode' => $kodeBrand,
                    'nama' => $namaBrand,
                    'harga_raw' => $hargaBrand,
                    'harga' => null,
                    'errors' => [],
                    'warnings' => [],
                    'status' => null,
                    'brand_id' => null,
                ];
            }
        }

        return compact('groups', 'orphans', 'totalRows');
    }

    private function buildImportRows(array $parsed): array
    {
        $groups = $parsed['groups'];
        $orphans = $parsed['orphans'];

        $generikByKode = ObatGenerik::pluck('id', 'kode_obat')->all();
        $generikNames = ObatGenerik::pluck('nama_generik', 'kode_obat')->all();
        $brandByKode = ObatBrand::pluck('id', 'kode_obat')->all();
        $brandNames = ObatBrand::pluck('nama_brand', 'kode_obat')->all();
        $brandToGenerik = PemetaanObat::pluck('obat_generik_id', 'obat_brand_id')->all();
        $existingPairs = PemetaanObat::get(['obat_generik_id', 'obat_brand_id'])
            ->map(fn ($p) => $p->obat_generik_id.':'.$p->obat_brand_id)
            ->all();

        // Validasi master generik (satu master per kode, bukan per baris)
        foreach ($groups as $idx => &$group) {
            $errors = [];
            $warnings = [];

            if ($group['kode'] === '') {
                $errors[] = 'kode generik wajib diisi.';
            }

            if ($group['nama'] === '') {
                $errors[] = 'nama generik/kandungan wajib diisi.';
            }

            $harga = $this->normalizeNumeric($group['harga_raw']);
            if ($harga !== null && (is_string($harga) || (is_float($harga) && (int) $harga != $harga))) {
                $errors[] = 'harga generik harus berupa angka bulat.';
                $harga = null;
            } else {
                $harga = $harga === null ? null : (int) $harga;
            }
            $group['harga'] = $harga;
            $group['errors'] = $errors;

            if (! empty($errors)) {
                $group['status'] = 'error';
            } elseif (isset($generikByKode[$group['kode']])) {
                $group['status'] = 'exists';
                $group['generik_id'] = $generikByKode[$group['kode']];
                if ($generikNames[$group['kode']] !== $group['nama']) {
                    $warnings[] = 'Nama generik berbeda dengan data existing, data existing tidak akan diubah.';
                }
            } else {
                $group['status'] = 'new';
            }

            $group['warnings'] = array_merge($warnings, $group['warnings']);
            $group['message'] = $this->buildMessage($group['errors'], $group['warnings']);
        }
        unset($group);

        // Validasi brand per grup
        $seenPairs = [];
        $seenBrandGenerik = [];

        foreach ($groups as $idx => &$group) {
            $kodeGenerik = $group['kode'];

            foreach ($group['brands'] as &$brand) {
                $errors = [];
                $warnings = [];

                if ($group['status'] === 'error') {
                    $errors[] = 'Generik induk tidak valid, mapping tidak dapat dibuat.';
                }

                if ($brand['kode'] === '') {
                    $errors[] = 'kode brand wajib diisi.';
                }

                $hargaBrand = $this->normalizeNumeric($brand['harga_raw']);
                if ($hargaBrand !== null && (is_string($hargaBrand) || (is_float($hargaBrand) && (int) $hargaBrand != $hargaBrand))) {
                    $errors[] = 'harga brand harus berupa angka bulat.';
                    $hargaBrand = null;
                } else {
                    $hargaBrand = $hargaBrand === null ? null : (int) $hargaBrand;
                }
                $brand['harga'] = $hargaBrand;

                $brandId = null;
                if ($brand['kode'] !== '') {
                    if (isset($brandByKode[$brand['kode']])) {
                        $brandId = $brandByKode[$brand['kode']];
                        if ($brand['nama'] !== '' && $brandNames[$brand['kode']] !== $brand['nama']) {
                            $warnings[] = 'Nama brand berbeda dengan data existing, data existing tidak akan diubah.';
                        }
                    } elseif ($brand['nama'] === '') {
                        $errors[] = 'nama brand wajib diisi (brand belum ada di database dan akan dibuat).';
                    }
                }

                $status = 'error';

                if (empty($errors)) {
                    $pairKey = $kodeGenerik.'|'.$brand['kode'];

                    if (isset($seenPairs[$pairKey])) {
                        $status = 'duplicate';
                        $warnings[] = 'Pasangan generik + brand sudah ada di file (baris '.$seenPairs[$pairKey].').';
                    } elseif (isset($seenBrandGenerik[$brand['kode']]) && $seenBrandGenerik[$brand['kode']] !== $kodeGenerik) {
                        $errors[] = 'Brand "'.$brand['kode'].'" sudah terpetakan ke obat generik lain di file (baris '.$seenBrandGenerik[$brand['kode']].').';
                    } else {
                        $seenPairs[$pairKey] = $brand['row'];
                        $seenBrandGenerik[$brand['kode']] = $kodeGenerik;

                        $generikId = $group['generik_id'];

                        if ($brandId !== null && isset($brandToGenerik[$brandId])) {
                            $existingGenerikId = (int) $brandToGenerik[$brandId];
                            if ($generikId !== null && $existingGenerikId === (int) $generikId) {
                                $status = 'exists';
                            } else {
                                $errors[] = 'Brand "'.$brand['kode'].'" sudah terpetakan ke obat generik lain.';
                            }
                        } elseif ($brandId !== null && $generikId !== null) {
                            $status = in_array($generikId.':'.$brandId, $existingPairs, true) ? 'exists' : 'new';
                        } else {
                            $status = 'new';
                        }
                    }
                }

                $brand['status'] = $status;
                $brand['brand_id'] = $brandId;
                $brand['errors'] = $errors;
                $brand['warnings'] = $warnings;
                $brand['message'] = $this->buildMessage($errors, $warnings);
            }
            unset($brand);
        }
        unset($group);

        $brandCount = 0;
        $mappingCount = 0;
        $newCount = 0;
        $existsCount = 0;
        $duplicateCount = 0;
        $brandErrorCount = 0;
        $generikErrorCount = 0;
        $warningGroupCount = 0;

        foreach ($groups as $group) {
            if ($group['status'] === 'error') {
                $generikErrorCount++;
            }
            if (count($group['warnings']) > 0) {
                $warningGroupCount++;
            }

            foreach ($group['brands'] as $brand) {
                $brandCount++;
                if (in_array($brand['status'], ['new', 'exists'], true)) {
                    $mappingCount++;
                }
                if ($brand['status'] === 'new') {
                    $newCount++;
                }
                if ($brand['status'] === 'exists') {
                    $existsCount++;
                }
                if ($brand['status'] === 'duplicate') {
                    $duplicateCount++;
                }
if ($brand['status'] === 'error' && $group['status'] !== 'error') {
                    $brandErrorCount++;
                }
                if (count($brand['warnings']) > 0) {
                    $warningGroupCount++;
                }
            }
        }

        $summary = [
            'total' => $parsed['totalRows'],
            'generik' => count($groups),
            'brand' => $brandCount,
            'mapping' => $mappingCount,
            'new' => $newCount,
            'exists' => $existsCount,
            'duplicate' => $duplicateCount,
            'error' => $generikErrorCount + $brandErrorCount + count($orphans),
            'warning' => $warningGroupCount,
        ];

        return compact('groups', 'orphans', 'summary');
    }

    private function performImport(array $built): array
    {
        $groups = $built['groups'];
        $orphans = $built['orphans'];
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        $generikIdByKode = ObatGenerik::pluck('id', 'kode_obat')->all();

        // 1. Resolve/create satu master generik per kode (jangan buat duplikat)
        foreach ($groups as $group) {
            if ($group['status'] === 'error') {
                continue;
            }

            $obatGenerik = ObatGenerik::firstOrCreate(
                ['kode_obat' => $group['kode']],
                ['nama_generik' => $group['nama'], 'harga_jual' => $group['harga']]
            );
            $generikIdByKode[$group['kode']] = $obatGenerik->id;
        }

        // 2. Resolve brand + buat mapping
        foreach ($groups as $group) {
            if (empty($group['brands'])) {
                if ($group['status'] === 'error') {
                    $failed++;
                } elseif ($group['status'] === 'exists') {
                    $skipped++;
                } else {
                    $imported++;
                }

                continue;
            }

            $generikId = $generikIdByKode[$group['kode']] ?? null;

            foreach ($group['brands'] as $brand) {
                if ($brand['status'] === 'error') {
                    $failed++;
                    continue;
                }

                if (in_array($brand['status'], ['exists', 'duplicate'], true)) {
                    $skipped++;
                    continue;
                }

                if ($generikId === null) {
                    $failed++;
                    continue;
                }

                $brandId = $brand['brand_id'];
                if ($brandId === null) {
                    $obatBrand = ObatBrand::firstOrCreate(
                        ['kode_obat' => $brand['kode']],
                        ['nama_brand' => $brand['nama'], 'harga_jual' => $brand['harga']]
                    );
                    $brandId = $obatBrand->id;
                }

                PemetaanObat::firstOrCreate([
                    'obat_generik_id' => $generikId,
                    'obat_brand_id' => $brandId,
                ]);
                $imported++;
            }
        }

        $failed += count($orphans);

        return [
            'total' => $built['summary']['total'],
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
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
