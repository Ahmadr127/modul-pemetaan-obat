<?php

namespace Tests\Feature;

use App\Models\ImportLog;
use App\Models\ObatBrand;
use App\Models\ObatGenerik;
use App\Models\PemetaanObat;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Shuchkin\SimpleXLSX;
use Shuchkin\SimpleXLSXGen;
use Tests\TestCase;

class PemetaanObatImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['Kode Generik', 'Nama Generik/Kandungan', 'Harga Generik', 'Kode Brand', 'Nama Brand', 'Harga Brand'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeUser());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeUser(): User
    {
        $permission = Permission::create([
            'name' => 'manage_pemetaan_obat',
            'display_name' => 'Kelola Pemetaan Obat',
            'description' => '',
        ]);

        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => '',
        ]);

        $role->permissions()->attach($permission->id);

        return User::factory()->create([
            'role_id' => $role->id,
            'username' => 'admin_'.Str::random(4),
        ]);
    }

    private function makeWorkbook(array $rows, ?array $headers = null): string
    {
        $data = [$headers ?? self::HEADERS];

        foreach ($rows as $row) {
            $data[] = $row;
        }

        $xlsx = SimpleXLSXGen::fromArray(SimpleXLSXGen::rawArray($data), 'Pemetaan Obat');
        $path = sys_get_temp_dir().'/pmo_'.uniqid().'.xlsx';
        $xlsx->saveAs($path);

        return $path;
    }

    private function makeImportFile(array $rows): string
    {
        return $this->makeWorkbook($rows);
    }

    private function makeOldTwoSheetWorkbook(): string
    {
        $xlsx = SimpleXLSXGen::fromArray([
            ['kode_obat', 'nama_generik', 'harga_jual'],
            ['OBT00006', 'ACETYLCISTEIN', 298202],
        ], 'OBAT_GENERIK');

        $xlsx->addSheet([
            ['kode_generik', 'kode_brand', 'nama_brand', 'harga_brand'],
            ['OBT00006', 'OBT0119', 'RESFAR', 298202],
        ], 'PEMETAAN_BRAND');

        $path = sys_get_temp_dir().'/pmo_old_'.uniqid().'.xlsx';
        $xlsx->saveAs($path);

        return $path;
    }

    private function upload(string $path, string $name = 'import.xlsx'): TestResponse
    {
        $file = new UploadedFile(
            $path,
            $name,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        return $this->post(route('pemetaan-obat.import.preview'), ['file' => $file]);
    }

    private function preview(array $rows): TestResponse
    {
        return $this->upload($this->makeImportFile($rows));
    }

    private function assertSummary(TestResponse $response, array $expected): void
    {
        $response->assertOk();
        $summary = $response->viewData('summary');
        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $summary[$key], "Ringkasan tidak sesuai untuk \"{$key}\".");
        }
    }

    private function confirm(): TestResponse
    {
        return $this->post(route('pemetaan-obat.import.confirm'));
    }

    private function createGenerik(string $kode, string $nama = 'Generik Test', ?int $harga = 1000): ObatGenerik
    {
        return ObatGenerik::create([
            'kode_obat' => $kode,
            'nama_generik' => $nama,
            'harga_jual' => $harga,
        ]);
    }

    private function createBrand(string $kode, string $nama = 'Brand Test', ?int $harga = 1000): ObatBrand
    {
        return ObatBrand::create([
            'kode_obat' => $kode,
            'nama_brand' => $nama,
            'harga_jual' => $harga,
        ]);
    }

    // ------------------------------------------------------------------
    // Validasi file & header
    // ------------------------------------------------------------------

    public function test_non_excel_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('not-excel.txt', 100, 'text/plain');

        $response = $this->post(route('pemetaan-obat.import.preview'), ['file' => $file]);

        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHasErrors('file');
    }

    public function test_missing_required_column_is_rejected(): void
    {
        $headers = ['Kode Generik', 'Nama Generik/Kandungan', 'Harga Generik', 'Kode Brand', 'Harga Brand'];
        $path = $this->makeWorkbook([['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 298202]], $headers);

        $response = $this->upload($path);

        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'Kolom "Nama Brand" tidak ditemukan'));
    }

    public function test_old_two_sheet_format_is_rejected_with_clear_error(): void
    {
        $path = $this->makeOldTwoSheetWorkbook();

        $response = $this->upload($path);

        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'Kolom "Kode Generik" tidak ditemukan'));
    }

    // ------------------------------------------------------------------
    // Test 1 — single generik, single brand
    // ------------------------------------------------------------------

    public function test_single_generik_single_brand(): void
    {
        $response = $this->preview([
            ['OBT00015', 'ACYCLOVIR', 13352, 'OBT02033', 'ZOTER', 97403],
        ]);

        $this->assertSummary($response, ['total' => 1, 'generik' => 1, 'brand' => 1, 'mapping' => 1, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseCount('obat_generik', 1);
        $this->assertDatabaseCount('obat_brand', 1);
        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    // ------------------------------------------------------------------
    // Test 2 — single generik, multiple brand (current generik diwariskan)
    // ------------------------------------------------------------------

    public function test_single_generik_multiple_brand(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['', '', '', 'OBT0494', 'FLUIMUCIL', 96308],
        ]);

        $this->assertSummary($response, ['total' => 2, 'generik' => 1, 'brand' => 2, 'mapping' => 2, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertEquals(1, ObatGenerik::where('kode_obat', 'OBT00006')->count());
        $this->assertDatabaseCount('pemetaan_obat', 2);

        $generik = ObatGenerik::where('kode_obat', 'OBT00006')->first();
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $generik->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT0119')->value('id')]);
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $generik->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT0494')->value('id')]);
    }

    // ------------------------------------------------------------------
    // Test 3 — multiple generik
    // ------------------------------------------------------------------

    public function test_multiple_generik(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['', '', '', 'OBT0494', 'FLUIMUCIL', 96308],
            ['OBT00015', 'ACYCLOVIR', 13352, 'OBT02033', 'ZOTER', 97403],
        ]);

        $this->assertSummary($response, ['generik' => 2, 'brand' => 3, 'mapping' => 3, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseCount('obat_generik', 2);
        $this->assertDatabaseCount('obat_brand', 3);
        $this->assertDatabaseCount('pemetaan_obat', 3);

        $g6 = ObatGenerik::where('kode_obat', 'OBT00006')->first();
        $g15 = ObatGenerik::where('kode_obat', 'OBT00015')->first();

        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $g6->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT0119')->value('id')]);
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $g6->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT0494')->value('id')]);
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $g15->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT02033')->value('id')]);
    }

    // ------------------------------------------------------------------
    // Test 4 — empty generic row inherits parent
    // ------------------------------------------------------------------

    public function test_empty_generic_row_inherits_parent(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['', '', '', 'OBT0494', 'FLUIMUCIL', 96308],
        ]);

        $this->assertSummary($response, ['generik' => 1, 'brand' => 2, 'mapping' => 2, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $generik = ObatGenerik::where('kode_obat', 'OBT00006')->first();
        $brand = ObatBrand::where('kode_obat', 'OBT0494')->first();

        $this->assertNotNull($generik);
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $generik->id, 'obat_brand_id' => $brand->id]);
    }

    // ------------------------------------------------------------------
    // Test 5 — empty generic tanpa parent -> error, tidak ada mapping
    // ------------------------------------------------------------------

    public function test_empty_generic_without_parent_produces_error(): void
    {
        $response = $this->preview([
            ['', '', '', 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->assertSummary($response, ['total' => 1, 'generik' => 0, 'mapping' => 0, 'error' => 1]);
        $response->assertSee('belum ada generik aktif');

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseCount('obat_brand', 0);
        $this->assertDatabaseCount('pemetaan_obat', 0);
    }

    // ------------------------------------------------------------------
    // Test 6 — existing generik tidak dibuat duplikat
    // ------------------------------------------------------------------

    public function test_existing_generic_is_not_duplicated(): void
    {
        $this->createGenerik('OBT00006', 'ACETYLCISTEIN', 298202);

        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->assertSummary($response, ['generik' => 1, 'mapping' => 1, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertEquals(1, ObatGenerik::where('kode_obat', 'OBT00006')->count());
    }

    // ------------------------------------------------------------------
    // Test 7 — existing mapping tidak dibuat duplikat
    // ------------------------------------------------------------------

    public function test_existing_mapping_is_not_duplicated(): void
    {
        $generik = $this->createGenerik('OBT00006');
        $brand = $this->createBrand('OBT0119');
        PemetaanObat::create(['obat_generik_id' => $generik->id, 'obat_brand_id' => $brand->id]);

        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->assertSummary($response, ['mapping' => 1, 'exists' => 1, 'new' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    // ------------------------------------------------------------------
    // Test 8 — generik dengan banyak brand
    // ------------------------------------------------------------------

    public function test_generik_with_many_brands(): void
    {
        $response = $this->preview([
            ['OBT02820', 'DESOXIMETASONE 0.25 CREAM 15G - JS', 19945, 'OBT00441', 'ESPERSON CREAM 15 GR', 261580],
            ['', '', '', 'OBT00442', 'ESPERSON CREAM 5 GR', 105709],
        ]);

        $this->assertSummary($response, ['generik' => 1, 'brand' => 2, 'mapping' => 2, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertEquals(1, ObatGenerik::where('kode_obat', 'OBT02820')->count());
        $this->assertDatabaseCount('pemetaan_obat', 2);

        $generikId = ObatGenerik::where('kode_obat', 'OBT02820')->value('id');
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $generikId, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT00441')->value('id')]);
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $generikId, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT00442')->value('id')]);
    }

    // ------------------------------------------------------------------
    // Test 9 — brand sudah dipetakan ke generik lain -> conflict
    // ------------------------------------------------------------------

    public function test_brand_already_mapped_to_other_generik_produces_error(): void
    {
        $g6 = $this->createGenerik('OBT00006');
        $g15 = $this->createGenerik('OBT00015');
        $brand = $this->createBrand('OBT0119');
        PemetaanObat::create(['obat_generik_id' => $g15->id, 'obat_brand_id' => $brand->id]);

        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->assertSummary($response, ['mapping' => 0, 'error' => 1]);
        $response->assertSee('sudah terpetakan');

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $g15->id, 'obat_brand_id' => $brand->id]);
        $this->assertDatabaseMissing('pemetaan_obat', ['obat_generik_id' => $g6->id, 'obat_brand_id' => $brand->id]);
    }

    // ------------------------------------------------------------------
    // Test 10 — duplicate row dalam file -> tidak ada duplicate mapping
    // ------------------------------------------------------------------

    public function test_duplicate_row_in_file_not_duplicated(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['', '', '', 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->assertSummary($response, ['mapping' => 1, 'duplicate' => 1, 'new' => 1]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    // ------------------------------------------------------------------
    // Test 11 — kode generik duplikat dengan data berbeda -> warning
    // ------------------------------------------------------------------

    public function test_generic_duplicate_with_different_data_warns(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['OBT00006', 'NAMA BERBEDA', 999, 'OBT0494', 'FLUIMUCIL', 96308],
        ]);

        $this->assertSummary($response, ['generik' => 1, 'brand' => 2, 'mapping' => 2, 'error' => 0, 'warning' => 1]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseCount('obat_generik', 1);
        $this->assertDatabaseHas('obat_generik', ['kode_obat' => 'OBT00006', 'nama_generik' => 'ACETYLCISTEIN']);
        $this->assertDatabaseCount('pemetaan_obat', 2);
    }

    // ------------------------------------------------------------------
    // Test 12 — blank row tidak mereset current generik
    // ------------------------------------------------------------------

    public function test_blank_row_does_not_reset_parent(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['', '', '', '', '', ''],
            ['', '', '', 'OBT0494', 'FLUIMUCIL', 96308],
        ]);

        $this->assertSummary($response, ['total' => 2, 'generik' => 1, 'brand' => 2, 'mapping' => 2, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $generik = ObatGenerik::where('kode_obat', 'OBT00006')->first();
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $generik->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT0494')->value('id')]);
        $this->assertDatabaseCount('pemetaan_obat', 2);
    }

    // ------------------------------------------------------------------
    // Test 13 — pergantian generik (current parent hanya berubah saat kode baru)
    // ------------------------------------------------------------------

    public function test_generik_switch_changes_parent_only_when_new_code(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['', '', '', 'OBT0494', 'FLUIMUCIL', 96308],
            ['OBT00015', 'ACYCLOVIR', 13352, 'OBT02033', 'ZOTER', 97403],
            ['', '', '', 'OBT02034', 'BRAND B', 100],
        ]);

        $this->assertSummary($response, ['generik' => 2, 'brand' => 4, 'mapping' => 4, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $g6 = ObatGenerik::where('kode_obat', 'OBT00006')->first();
        $g15 = ObatGenerik::where('kode_obat', 'OBT00015')->first();

        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $g6->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT0494')->value('id')]);
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $g15->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT02033')->value('id')]);
        $this->assertDatabaseHas('pemetaan_obat', ['obat_generik_id' => $g15->id, 'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT02034')->value('id')]);
        $this->assertDatabaseCount('pemetaan_obat', 4);
    }

    // ------------------------------------------------------------------
    // Brand baru / conflict dalam file
    // ------------------------------------------------------------------

    public function test_new_brand_is_created_from_file(): void
    {
        $this->createGenerik('OBT00006');

        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT99999', 'RESFAR', 298202],
        ]);

        $this->assertSummary($response, ['mapping' => 1, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('obat_brand', ['kode_obat' => 'OBT99999', 'nama_brand' => 'RESFAR', 'harga_jual' => 298202]);
        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    public function test_new_brand_without_name_produces_error(): void
    {
        $this->createGenerik('OBT00006');

        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT99999', '', 298202],
        ]);

        $this->assertSummary($response, ['mapping' => 0, 'error' => 1]);
        $response->assertSee('nama brand wajib diisi');
    }

    public function test_same_brand_to_two_generik_in_file_produces_error(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['OBT00015', 'ACYCLOVIR', 13352, 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->assertSummary($response, ['mapping' => 1, 'error' => 1]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    public function test_existing_brand_is_used_and_not_overwritten(): void
    {
        $this->createGenerik('OBT00006', 'ACETYLCISTEIN', 298202);
        $this->createBrand('OBT0119', 'NAMA LAMA', 500);

        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'NAMA BARU', 900],
        ]);

        $this->assertSummary($response, ['mapping' => 1, 'error' => 0, 'warning' => 1]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('obat_brand', ['kode_obat' => 'OBT0119', 'nama_brand' => 'NAMA LAMA', 'harga_jual' => 500]);
        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    // ------------------------------------------------------------------
    // Harga
    // ------------------------------------------------------------------

    public function test_price_with_thousands_separator_is_parsed(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', '298,202', 'OBT0119', 'RESFAR', '298.202'],
        ]);

        $this->assertSummary($response, ['total' => 1, 'generik' => 1, 'brand' => 1, 'mapping' => 1, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('obat_generik', ['kode_obat' => 'OBT00006', 'harga_jual' => 298202]);
        $this->assertDatabaseHas('obat_brand', ['kode_obat' => 'OBT0119', 'harga_jual' => 298202]);
    }

    public function test_non_numeric_price_produces_error(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 'abc', 'OBT0119', 'RESFAR', 'xyz'],
        ]);

        $this->assertSummary($response, ['error' => 1, 'mapping' => 0]);
    }

    // ------------------------------------------------------------------
    // Preview & transaction
    // ------------------------------------------------------------------

    public function test_preview_shows_grouped_structure(): void
    {
        $response = $this->preview([
            ['OBT00006', 'ACETYLCISTEIN INF US 200 MG/ML - JS', 298202, 'OBT0119', 'RESFAR 30 ML INJ', 298202],
            ['', '', '', 'OBT0494', 'FLUIMUCIL 10% AMPUL 300 MG/3 ML [ HA ]', 96308],
        ]);

        $response->assertOk();
        $response->assertSee('ACETYLCISTEIN INF');
        $response->assertSee('RESFAR 30 ML INJ');
        $response->assertSee('FLUIMUCIL');

        $groups = $response->viewData('groups');
        $this->assertCount(1, $groups);
        $this->assertSame('OBT00006', $groups[0]['kode']);
        $this->assertCount(2, $groups[0]['brands']);
    }

    public function test_import_rolls_back_when_error_occurs(): void
    {
        $path = $this->makeImportFile([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->upload($path)->assertOk();

        PemetaanObat::saving(function () {
            throw new \RuntimeException('forced failure');
        });

        $response = $this->confirm();
        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('obat_generik', 0);
        $this->assertDatabaseCount('obat_brand', 0);
        $this->assertDatabaseCount('pemetaan_obat', 0);
    }

    // ------------------------------------------------------------------
    // Log import
    // ------------------------------------------------------------------

    public function test_successful_import_creates_success_log(): void
    {
        $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
        ])->assertOk();

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('import_logs', [
            'status' => ImportLog::STATUS_SUCCESS,
            'total' => 1,
            'imported' => 1,
            'skipped' => 0,
            'failed' => 0,
        ]);

        $log = ImportLog::where('status', ImportLog::STATUS_SUCCESS)->latest()->first();
        $this->assertNotEmpty($log->details);
        $this->assertStringContainsString('dibuat baru', implode(' ', $log->details));
        $this->assertStringContainsString('dipetakan ke generik', implode(' ', $log->details));
    }

    public function test_partial_import_creates_warning_log(): void
    {
        $this->preview([
            ['', '', '', 'OBT0117', 'ORPHAN 1', 100],
            ['', '', '', 'OBT0116', 'ORPHAN 2', 100],
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
            ['', '', '', 'OBT0118', 'FLUIMUCIL', 96308],
        ])->assertOk();

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('import_logs', [
            'status' => ImportLog::STATUS_WARNING,
            'total' => 4,
            'imported' => 2,
            'skipped' => 0,
            'failed' => 2,
        ]);

        $log = ImportLog::where('status', ImportLog::STATUS_WARNING)->latest()->first();
        $this->assertNotEmpty($log->details);
        $detailText = implode(' ', $log->details);
        $this->assertStringContainsString('belum ada generik aktif', $detailText);
        $this->assertStringContainsString('dibuat baru', $detailText);
    }

    public function test_existing_mapping_import_logs_details(): void
    {
        $generik = $this->createGenerik('OBT00006', 'ACETYLCISTEIN', 298202);
        $brand = $this->createBrand('OBT0119', 'RESFAR', 298202);
        PemetaanObat::create(['obat_generik_id' => $generik->id, 'obat_brand_id' => $brand->id]);

        $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
        ])->assertOk();

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $log = ImportLog::where('status', ImportLog::STATUS_SUCCESS)->latest()->first();
        $this->assertNotEmpty($log->details);
        $detailText = implode(' ', $log->details);
        $this->assertStringContainsString('sudah ada di database', $detailText);
        $this->assertStringContainsString('dilewati', $detailText);
    }

    public function test_existing_brand_not_overwritten_logs_details(): void
    {
        $this->createGenerik('OBT00006', 'ACETYLCISTEIN', 298202);
        $this->createBrand('OBT0119', 'NAMA LAMA', 500);

        $this->preview([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'NAMA BARU', 900],
        ])->assertOk();

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $log = ImportLog::where('status', ImportLog::STATUS_SUCCESS)->latest()->first();
        $this->assertNotEmpty($log->details);
        $detailText = implode(' ', $log->details);
        $this->assertStringContainsString('Nama brand berbeda', $detailText);
        $this->assertStringContainsString('tidak akan diubah', $detailText);
    }

    public function test_rejected_file_creates_error_log(): void
    {
        $headers = ['Kode Generik', 'Nama Generik/Kandungan', 'Harga Generik', 'Kode Brand', 'Harga Brand'];
        $path = $this->makeWorkbook([['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 298202]], $headers);

        $this->upload($path);

        $log = ImportLog::where('status', ImportLog::STATUS_ERROR)->latest()->first();
        $this->assertNotNull($log);
        $this->assertNotNull($log->errors);
        $this->assertStringContainsString('Nama Brand', implode(' ', $log->errors));
    }

    public function test_rollback_import_creates_error_log(): void
    {
        $path = $this->makeImportFile([
            ['OBT00006', 'ACETYLCISTEIN', 298202, 'OBT0119', 'RESFAR', 298202],
        ]);

        $this->upload($path)->assertOk();

        PemetaanObat::saving(function () {
            throw new \RuntimeException('forced failure');
        });

        $this->confirm();

        $log = ImportLog::where('status', ImportLog::STATUS_ERROR)->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('forced failure', implode(' ', $log->errors));
    }

    public function test_import_log_page_lists_logs(): void
    {
        ImportLog::create([
            'user_id' => auth()->id(),
            'file_name' => 'import.xlsx',
            'status' => ImportLog::STATUS_ERROR,
            'total' => 1,
            'imported' => 0,
            'skipped' => 0,
            'failed' => 1,
            'errors' => ['Kolom "Nama Brand" tidak ditemukan.'],
            'details' => ['Baris 3: Brand "OBT0119" gagal dipetakan — kode brand wajib diisi.'],
        ]);

        $response = $this->get(route('pemetaan-obat.import.log'));

        $response->assertOk();
        $response->assertSee('import.xlsx');
        $response->assertSee('Kolom "Nama Brand" tidak ditemukan');
        $response->assertSee('Brand "OBT0119" gagal dipetakan');
    }

    // ------------------------------------------------------------------
    // Template download
    // ------------------------------------------------------------------

    public function test_template_download_is_single_sheet(): void
    {
        $response = $this->get(route('pemetaan-obat.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type') ?? '');

        $path = sys_get_temp_dir().'/pmo_tpl_'.uniqid().'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $xlsx = SimpleXLSX::parse($path);
        $this->assertInstanceOf(SimpleXLSX::class, $xlsx);

        $sheetNames = $xlsx->sheetNames();

        $this->assertCount(1, $sheetNames);
        $this->assertSame('Pemetaan Obat', $sheetNames[0]);
        $this->assertSame(self::HEADERS, array_slice($xlsx->rows(0)[0], 0, 6));
    }

    // ------------------------------------------------------------------
    // Halaman lain
    // ------------------------------------------------------------------

    public function test_index_page_shows_import_buttons(): void
    {
        $response = $this->get(route('pemetaan-obat.index'));

        $response->assertOk();
        $response->assertSee('Import Excel');
        $response->assertSee('Download Template');
        $response->assertSee(route('pemetaan-obat.import.preview'));
    }

    public function test_generik_page_renders_searchable_table(): void
    {
        $this->createGenerik('OBT00006', 'ACETYLCISTEIN', 298202);

        $response = $this->get(route('pemetaan-obat.generik'));

        $response->assertOk();
        $response->assertSee('ACETYLCISTEIN');
        $response->assertSee('searchableTable');
    }

    public function test_brand_page_renders_searchable_table(): void
    {
        $this->createBrand('OBT0119', 'RESFAR 30 ML INJ');

        $response = $this->get(route('pemetaan-obat.brand'));

        $response->assertOk();
        $response->assertSee('RESFAR 30 ML INJ');
        $response->assertSee('searchableTable');
    }
}
