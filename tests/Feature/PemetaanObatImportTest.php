<?php

namespace Tests\Feature;

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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PemetaanObatImportTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIK_HEADERS = ['kode_obat', 'nama_generik', 'harga_jual'];

    private const BRAND_HEADERS = ['kode_generik', 'kode_brand', 'nama_brand', 'harga_brand'];

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

    private function makeWorkbook(array $sheets): string
    {
        $spreadsheet = new Spreadsheet;
        $first = true;

        foreach ($sheets as $sheet) {
            $worksheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $first = false;

            $worksheet->setTitle($sheet['title']);
            if (! empty($sheet['headers'])) {
                $worksheet->fromArray([$sheet['headers']], null, 'A1');
            }
            if (! empty($sheet['rows'])) {
                $worksheet->fromArray($sheet['rows'], null, 'A2');
            }
        }

        $writer = new Xlsx($spreadsheet);
        $path = sys_get_temp_dir().'/pmo_'.uniqid().'.xlsx';
        $writer->save($path);

        return $path;
    }

    private function makeImportFile(array $generikRows, array $brandRows): string
    {
        return $this->makeWorkbook([
            ['title' => 'OBAT_GENERIK', 'headers' => self::GENERIK_HEADERS, 'rows' => $generikRows],
            ['title' => 'PEMETAAN_BRAND', 'headers' => self::BRAND_HEADERS, 'rows' => $brandRows],
        ]);
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

    private function preview(array $generikRows, array $brandRows): TestResponse
    {
        return $this->upload($this->makeImportFile($generikRows, $brandRows));
    }

    private function assertSummary(TestResponse $response, array $expected): void
    {
        $response->assertOk();
        $summary = $response->viewData('summary');
        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $summary[$key], "Ringkasan tidak sesuai untuk \"{$key}\".");
        }
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

    private function confirm(): TestResponse
    {
        return $this->post(route('pemetaan-obat.import.confirm'));
    }

    // ------------------------------------------------------------------
    // Validasi file
    // ------------------------------------------------------------------

    public function test_non_excel_file_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('not-excel.txt', 100, 'text/plain');

        $response = $this->post(route('pemetaan-obat.import.preview'), ['file' => $file]);

        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHasErrors('file');
    }

    public function test_missing_required_sheet_is_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['title' => 'OBAT_GENERIK', 'headers' => self::GENERIK_HEADERS, 'rows' => [['OBT00006', 'ACETYLCISTEIN', 298202]]],
        ]);

        $response = $this->upload($path);

        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'PEMETAAN_BRAND'));
    }

    public function test_wrong_headers_are_rejected(): void
    {
        $path = $this->makeWorkbook([
            ['title' => 'OBAT_GENERIK', 'headers' => ['kode', 'nama', 'harga'], 'rows' => [['OBT00006', 'ACETYLCISTEIN', 298202]]],
            ['title' => 'PEMETAAN_BRAND', 'headers' => self::BRAND_HEADERS, 'rows' => []],
        ]);

        $response = $this->upload($path);

        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHas('error', fn (string $message) => str_contains($message, 'Header sheet'));
    }

    // ------------------------------------------------------------------
    // Validasi data
    // ------------------------------------------------------------------

    public function test_empty_generik_code_produces_error(): void
    {
        $response = $this->preview(
            [['', 'ACETYLCISTEIN', 298202]],
            []
        );

        $this->assertSummary($response, ['total' => 1, 'error' => 1]);
        $response->assertSee('kode_obat wajib diisi');
    }

    public function test_empty_brand_code_produces_error(): void
    {
        $this->createGenerik('OBT00006');

        $response = $this->preview(
            [],
            [['OBT00006', '', 'RESFAR 30 ML INJ', 298202]]
        );

        $this->assertSummary($response, ['total' => 1, 'error' => 1]);
        $response->assertSee('kode_brand wajib diisi');
    }

    public function test_unknown_generik_code_produces_error(): void
    {
        $this->createBrand('OBT0119');

        $response = $this->preview(
            [],
            [['OBT99999', 'OBT0119', 'RESFAR 30 ML INJ', 298202]]
        );

        $this->assertSummary($response, ['total' => 1, 'error' => 1]);
        $response->assertSee('tidak ditemukan');
    }

    public function test_new_brand_is_created_from_file(): void
    {
        $this->createGenerik('OBT00006');

        $response = $this->preview(
            [],
            [['OBT00006', 'OBT99999', 'RESFAR 30 ML INJ', 298202]]
        );

        $this->assertSummary($response, ['total' => 1, 'new' => 1, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('obat_brand', [
            'kode_obat' => 'OBT99999',
            'nama_brand' => 'RESFAR 30 ML INJ',
            'harga_jual' => 298202,
        ]);
        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    public function test_new_brand_without_name_produces_error(): void
    {
        $this->createGenerik('OBT00006');

        $response = $this->preview(
            [],
            [['OBT00006', 'OBT99999', '', 298202]]
        );

        $this->assertSummary($response, ['total' => 1, 'error' => 1]);
        $response->assertSee('nama_brand wajib diisi');
    }

    public function test_same_brand_to_two_generik_in_file_produces_error(): void
    {
        $this->createGenerik('OBT00006');
        $this->createGenerik('OBT00007');

        $response = $this->preview(
            [],
            [
                ['OBT00006', 'OBT99999', 'RESFAR', 298202],
                ['OBT00007', 'OBT99999', 'RESFAR', 298202],
            ]
        );

        $this->assertSummary($response, ['total' => 2, 'new' => 1, 'error' => 1]);
        $response->assertSee('sudah terpetakan');
    }

    public function test_non_numeric_price_produces_error(): void
    {
        $generikResponse = $this->preview(
            [['OBT00006', 'ACETYLCISTEIN', 'abc']],
            []
        );
        $this->assertSummary($generikResponse, ['total' => 1, 'error' => 1]);

        $this->createGenerik('OBT00015');
        $this->createBrand('OBT02033');

        $brandResponse = $this->preview(
            [],
            [['OBT00015', 'OBT02033', 'ZOTER CREAM', 'abc']]
        );
        $this->assertSummary($brandResponse, ['total' => 1, 'error' => 1]);
    }

    public function test_duplicate_pair_in_file_marked_duplicate(): void
    {
        $this->createGenerik('OBT00006');
        $this->createBrand('OBT0119');

        $response = $this->preview(
            [],
            [
                ['OBT00006', 'OBT0119', 'RESFAR 30 ML INJ', 298202],
                ['OBT00006', 'OBT0119', 'RESFAR 30 ML INJ', 298202],
            ]
        );

        $this->assertSummary($response, ['total' => 2, 'new' => 1, 'duplicate' => 1, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));
        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    public function test_brand_already_mapped_to_other_generik_produces_error(): void
    {
        $this->createGenerik('OBT00006', 'Generik 1');
        $this->createGenerik('OBT00007', 'Generik 2');
        $brand = $this->createBrand('OBT0119');

        PemetaanObat::create([
            'obat_generik_id' => ObatGenerik::where('kode_obat', 'OBT00007')->value('id'),
            'obat_brand_id' => $brand->id,
        ]);

        $response = $this->preview(
            [],
            [['OBT00006', 'OBT0119', 'RESFAR 30 ML INJ', 298202]]
        );

        $this->assertSummary($response, ['total' => 1, 'error' => 1]);
        $response->assertSee('sudah terpetakan');
    }

    // ------------------------------------------------------------------
    // Preview & import
    // ------------------------------------------------------------------

    public function test_valid_file_shows_preview_and_imports_data(): void
    {
        $path = $this->makeImportFile(
            [['OBT00006', 'ACETYLCISTEIN INF US 200 MG/ML - JS', 298202]],
            [['OBT00006', 'OBT0119', 'RESFAR 30 ML INJ', 298202]]
        );

        $preview = $this->upload($path);
        $this->assertSummary($preview, ['total' => 2, 'new' => 2, 'error' => 0]);
        $preview->assertSee('ACETYLCISTEIN INF');

        $response = $this->confirm();
        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('obat_generik', [
            'kode_obat' => 'OBT00006',
            'nama_generik' => 'ACETYLCISTEIN INF US 200 MG/ML - JS',
            'harga_jual' => 298202,
        ]);

        $this->assertDatabaseHas('obat_brand', [
            'kode_obat' => 'OBT0119',
            'nama_brand' => 'RESFAR 30 ML INJ',
            'harga_jual' => 298202,
        ]);

        $this->assertDatabaseHas('pemetaan_obat', [
            'obat_generik_id' => ObatGenerik::where('kode_obat', 'OBT00006')->value('id'),
            'obat_brand_id' => ObatBrand::where('kode_obat', 'OBT0119')->value('id'),
        ]);
    }

    public function test_one_generik_many_brands(): void
    {
        $this->createGenerik('OBT00006', 'ACETYLCISTEIN');
        $this->createBrand('OBT0119', 'RESFAR');
        $this->createBrand('OBT0494', 'FLUIMUCIL');

        $response = $this->preview(
            [],
            [
                ['OBT00006', 'OBT0119', 'RESFAR', 298202],
                ['OBT00006', 'OBT0494', 'FLUIMUCIL', 96308],
            ]
        );

        $this->assertSummary($response, ['total' => 2, 'new' => 2, 'error' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));
        $this->assertDatabaseCount('pemetaan_obat', 2);
    }

    public function test_existing_brand_is_used_and_not_overwritten(): void
    {
        $this->createGenerik('OBT00006');
        $this->createBrand('OBT0119', 'NAMA BRAND LAMA', 500);

        $response = $this->preview(
            [],
            [['OBT00006', 'OBT0119', 'NAMA BRAND BARU', 900]]
        );

        $this->assertSummary($response, ['total' => 1, 'new' => 1, 'error' => 0, 'warning' => 1]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));

        $this->assertDatabaseHas('obat_brand', [
            'kode_obat' => 'OBT0119',
            'nama_brand' => 'NAMA BRAND LAMA',
            'harga_jual' => 500,
        ]);
        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    public function test_existing_mapping_is_not_duplicated(): void
    {
        $generik = $this->createGenerik('OBT00006');
        $brand = $this->createBrand('OBT0119');

        PemetaanObat::create([
            'obat_generik_id' => $generik->id,
            'obat_brand_id' => $brand->id,
        ]);

        $response = $this->preview(
            [['OBT00006', 'ACETYLCISTEIN', 298202]],
            [['OBT00006', 'OBT0119', 'RESFAR', 298202]]
        );

        $this->assertSummary($response, ['total' => 2, 'exists' => 2, 'new' => 0]);

        $this->confirm()->assertRedirect(route('pemetaan-obat.index'));
        $this->assertDatabaseCount('pemetaan_obat', 1);
    }

    public function test_preview_shows_error_details_per_row(): void
    {
        $this->createGenerik('OBT00006');
        $this->createBrand('OBT0119');

        $response = $this->preview(
            [],
            [
                ['OBT99999', 'OBT0119', 'RESFAR 30 ML INJ', 298202],
                ['OBT00006', 'OBT0119', 'RESFAR 30 ML INJ', 298202],
            ]
        );

        $this->assertSummary($response, ['total' => 2, 'new' => 1, 'error' => 1]);
        $response->assertSee('tidak ditemukan');
    }

    public function test_import_rolls_back_when_error_occurs(): void
    {
        $this->createBrand('OBT0119', 'RESFAR 30 ML INJ');

        $path = $this->makeImportFile(
            [['OBT00006', 'ACETYLCISTEIN INF US 200 MG/ML - JS', 298202]],
            [['OBT00006', 'OBT0119', 'RESFAR 30 ML INJ', 298202]]
        );

        $this->upload($path)->assertOk();

        PemetaanObat::saving(function () {
            throw new \RuntimeException('forced failure');
        });

        $response = $this->confirm();
        $response->assertRedirect(route('pemetaan-obat.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('obat_generik', 0);
        $this->assertDatabaseCount('pemetaan_obat', 0);
    }

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

    public function test_template_download(): void
    {
        $response = $this->get(route('pemetaan-obat.import.template'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('content-type') ?? ''
        );
    }
}
