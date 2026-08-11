<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ObatBrand extends Model
{
    use HasFactory;

    protected $table = 'obat_brand';

    protected $fillable = [
        'kode_obat',
        'nama_brand',
        'harga_jual',
    ];

    public function pemetaan(): HasMany
    {
        return $this->hasMany(PemetaanObat::class, 'obat_brand_id');
    }
}