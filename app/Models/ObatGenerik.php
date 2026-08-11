<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ObatGenerik extends Model
{
    use HasFactory;

    protected $table = 'obat_generik';

    protected $fillable = [
        'kode_obat',
        'nama_generik',
        'harga_jual',
    ];

    public function pemetaan(): HasMany
    {
        return $this->hasMany(PemetaanObat::class, 'obat_generik_id');
    }

    public function brand(): HasManyThrough
    {
        return $this->hasManyThrough(
            ObatBrand::class,
            PemetaanObat::class,
            'obat_generik_id',
            'id',
            'id',
            'obat_brand_id'
        );
    }
}