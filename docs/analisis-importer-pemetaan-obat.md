# Analisis Importer Pemetaan Obat

Dokumen ini mencatat hasil inspeksi (Phase 1), akar masalah (Phase 6), dan desain baru single-sheet (Phase 2-5) pada modul importer pemetaan obat.

---

## 1. Ringkasan

| Aspek | Sebelum (two-sheet) | Sesudah (single-sheet) |
| :--- | :--- | :--- |
| Struktur file Excel | 2 sheet wajib (`OBAT_GENERIK`, `PEMETAAN_BRAND`) + sheet `CONTOH` | 1 sheet `Pemetaan Obat` dengan 6 kolom |
| Relasi generik-brand | Berbasis kolom `kode_generik` pada setiap baris brand | Berbasis "current generik": kode generik hanya diisi di baris pertama grup, brand berikutnya mengikuti |
| Parsing harga | `is_numeric()` sederhana, tanpa dukungan separator ribuan | Normalisasi otomatis (`.` atau `,` grouping ribuan) |
| Preview | Tabel datar per baris | Card per generik dengan list brand di dalamnya |
| Duplikasi master | Potensi beberapa master untuk kode generik yang sama | Satu master per kode generik (`firstOrCreate`) |

---

## 2. Struktur File Baru (Single-Sheet)

Header (baris 1), data mulai baris 2:

| Kolom | Field |
| :--- | :--- |
| Kode Generik | `kode_generik` |
| Nama Generik/Kandungan | `nama_generik` |
| Harga Generik | `harga_generik` |
| Kode Brand | `kode_brand` |
| Nama Brand | `nama_brand` |
| Harga Brand | `harga_brand` |

### Aturan "current generik"

- Baris dengan **kode generik terisi** memulai grup generik baru.
- Baris berikutnya dengan **kode generik kosong** dianggap brand milik generik aktif sebelumnya.
- Blank row total dilewati dan **tidak** mereset current generik.
- Baris brand tanpa parent generik aktif → error (`orphan`), tidak diimport.

### Alur proses

1. `parseImportFile` → cari sheet pertama yang header-nya cocok penuh dengan 6 kolom.
2. `groupRows` → bangun struktur `groups[]` (`kode`, `nama`, `harga`, `brands[]`) + `orphans[]`.
3. `buildImportRows` → validasi per grup (master generik) dan per brand (mapping), hitung summary.
4. `performImport` → dalam 1 transaksi DB:
   - `firstOrCreate` satu master `obat_generik` per kode (tidak menduplikasi).
   - Resolve/`firstOrCreate` brand, lalu `firstOrCreate` mapping `pemetaan_obat`.

---

## 3. Akar Masalah Bug Lama (Phase 6)

### Bug 3.1 — Dua sheet, risiko master generik ganda

Pada versi lama, importer membutuhkan 2 sheet terpisah. Brand rows memakai kolom
`kode_generik` yang divalidasi silang terhadap master generik, tetapi pembuatan master
hanya terjadi untuk baris bertipe generik. Jika user hanya mengisi sheet brand tanpa
mendaftarkan generik di sheet `OBAT_GENERIK`, mapping gagal. Sebaliknya, format 2 sheet
mudah salah isi dan file yang tidak sesuai struktur langsung ditolak dengan pesan kurang
jelas.

### Bug 3.2 — Parsing harga dengan separator ribuan salah (root cause baru terungkap di Phase 6)

Pada versi lama, validasi harga menggunakan `is_numeric($harga)`. Nilai `"298.202"`:

- `is_numeric("298.202")` → `true` (dianggap desimal),
- selanjutnya `(int) "298.202"` → `298`.

Akibatnya harga `298.202` (format ribuan Indonesia) tersimpan sebagai `298`, bukan `298202`.

Pada versi baru, `normalizeNumeric` memeriksa **sebelum** `is_numeric`:

```
/^-?\d{1,3}(\.\d{3})+$/   → "298.202" → 298202   (ribuan Indonesia)
/^-?\d{1,3}(,\d{3})+$/    → "298,202" → 298202   (ribuan US)
```

Bug ini direproduksi oleh test `test_price_with_thousands_separator_is_parsed` yang
sebelumnya gagal dengan harga tersimpan `298` (kondisi reproduksi).

### Bug 3.3 — PhpSpreadsheet auto-mengubah string angka

`Worksheet::fromArray()` mengonversi string `"298.202"` menjadi float `298.202` saat
menulis workbook. Test harus menulis sel dengan `setCellValueExplicit(..., TYPE_STRING)`
agar nilai berupa string saat dibaca ulang (kondisi reproduksi di Phase 6-7).

---

## 4. Ringkasan Test (Phase 5)

| # | Test | Perilaku yang diverifikasi |
| :-- | :-- | :-- |
| 1 | `test_single_generik_single_brand` | 1 generik + 1 brand → 1 mapping |
| 2 | `test_single_generik_multiple_brand` | current generik diwariskan ke 2 brand |
| 3 | `test_multiple_generik` | 2 generik, 3 brand, 3 mapping |
| 4 | `test_empty_generic_row_inherits_parent` | baris brand tanpa kode generik mengikuti parent |
| 5 | `test_empty_generic_without_parent_produces_error` | brand tanpa parent → error, tanpa mapping |
| 6 | `test_existing_generic_is_not_duplicated` | master generik existing tidak dibuat ulang |
| 7 | `test_existing_mapping_is_not_duplicated` | mapping existing dilewati (status `exists`) |
| 8 | `test_generik_with_many_brands` | 1 generik, 2 brand, 2 mapping |
| 9 | `test_brand_already_mapped_to_other_generik_produces_error` | konflik brand → error |
| 10 | `test_duplicate_row_in_file_not_duplicated` | duplikat dalam file → 1 mapping |
| 11 | `test_generic_duplicate_with_different_data_warns` | kode sama nama beda → warning |
| 12 | `test_blank_row_does_not_reset_parent` | blank row tidak mereset current generik |
| 13 | `test_generik_switch_changes_parent_only_when_new_code` | pergantian generik saat kode baru muncul |

Test pendukung: header missing, format lama ditolak, harga separator, non-numeric,
rollback transaksi, preview grouped, template single-sheet, dan halaman CRUD.

### Hasil (Phase 7)

- `PemetaanObatImportTest`: **28 passed** (204 assertions).
- Full suite: 29 passed, 1 failed (`Tests\Feature\ExampleTest` — test bawaan starterpack,
  tidak terkait modul ini; mengharapkan `/` 200 padahal ter-redirect ke login).

---

## 5. Verifikasi Database (Phase 8)

Diverifikasi lewat assertion test:

- `assertEquals(1, ObatGenerik::where('kode_obat', 'OBT00006')->count())` — satu master per kode.
- `assertDatabaseCount('obat_generik', ...)`, `assertDatabaseCount('obat_brand', ...)`,
  `assertDatabaseCount('pemetaan_obat', ...)` untuk konsistensi relasi.

---

## 6. Catatan Implementasi

- Semua logika import berada di `PemetaanObatController` (metode `importTemplate`,
  `importPreview`, `importConfirm`, dan helper privat).
- Sesi import disimpan sebagai file temp di `storage/app/import-temp/` dan dibaca ulang
  saat `importConfirm` (file dihapus setelah diproses).
- Import berjalan dalam `DB::beginTransaction()`; jika terjadi error, seluruh data
  dibatalkan (`rollBack`) dan file temp dihapus.
