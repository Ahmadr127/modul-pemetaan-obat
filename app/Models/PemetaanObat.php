<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PemetaanObat extends Model
{
    use HasFactory;

    protected $table = 'pemetaan_obat';

    protected $fillable = [
        'obat_generik_id',
        'obat_brand_id',
    ];

    public function obatGenerik(): BelongsTo
    {
        return $this->belongsTo(ObatGenerik::class, 'obat_generik_id');
    }

    public function obatBrand(): BelongsTo
    {
        return $this->belongsTo(ObatBrand::class, 'obat_brand_id');
    }
}