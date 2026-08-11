<?php

namespace Database\Seeders;

use App\Models\ObatBrand;
use App\Models\ObatGenerik;
use App\Models\PemetaanObat;
use Illuminate\Database\Seeder;

/**
 * Seed data contoh modul Pemetaan Obat.
 *
 * Logika pengelompokan (sesuai aturan):
 * - Baris yang memiliki obat generik memulai kelompok baru.
 * - Baris berikutnya dengan generik KOSONG tetapi brand terisi
 *   tetap menjadi bagian dari generik terakhir.
 */
class PemetaanObatSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'kode' => 'OBT00006',
                'nama' => 'ACETYLCISTEINE INFUS 200 MG/ML - JS',
                'harga' => 298202,
                'brands' => [
                    ['OBT01119', 'RESFAR 30 ML INJ', 298202],
                    ['OBT00494', 'FLUIMUCIL 10% AMPUL 300 MG/3 ML [ HA ]', 96803],
                ],
            ],
            [
                'kode' => 'OBT00015',
                'nama' => 'ACYCLOVIR CREAM 5 GR - JS',
                'harga' => 13352,
                'brands' => [
                    ['OBT02033', 'ZOTER CREAM', 97403],
                ],
            ],
            [
                'kode' => 'OBT02530',
                'nama' => 'ADALAT OROS 30 MG - JS',
                'harga' => 6190,
                'brands' => [
                    ['OBT00017', 'ADALAT OROS 30 MG', 13428],
                ],
            ],
            [
                'kode' => 'OBT00036',
                'nama' => 'ALPRAZOLAM TAB 0,5MG - JS',
                'harga' => 1409,
                'brands' => [
                    ['OBT01442', 'ZYPRAZ 0.5 MG', 6294],
                ],
            ],
            [
                'kode' => 'OBT00065',
                'nama' => 'APIDRA - JS',
                'harga' => 121924,
                'brands' => [
                    ['OBT00064', 'APIDRA INJ', 258933],
                ],
            ],
            [
                'kode' => 'OBT02820',
                'nama' => 'DESOXIMETASONE 0.25 CREAM 15G - JS',
                'harga' => 19945,
                'brands' => [
                    ['OBT00441', 'ESPERSON CREAM 15 GR', 261580],
                    ['OBT00442', 'ESPERSON CREAM 5 GR', 105709],
                ],
            ],
        ];

        foreach ($data as $generik) {
            $obatGenerik = ObatGenerik::updateOrCreate(
                ['kode_obat' => $generik['kode']],
                [
                    'nama_generik' => $generik['nama'],
                    'harga_jual' => $generik['harga'],
                ]
            );

            foreach ($generik['brands'] as [$kode, $nama, $harga]) {
                $obatBrand = ObatBrand::updateOrCreate(
                    ['kode_obat' => $kode],
                    [
                        'nama_brand' => $nama,
                        'harga_jual' => $harga,
                    ]
                );

                PemetaanObat::firstOrCreate([
                    'obat_generik_id' => $obatGenerik->id,
                    'obat_brand_id' => $obatBrand->id,
                ]);
            }
        }
    }
}