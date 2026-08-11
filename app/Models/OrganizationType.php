<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'level',
        'description'
    ];

    /**
     * Get all organization units of this type
     */
    public function organizationUnits()
    {
        return $this->hasMany(OrganizationUnit::class, 'type_id');
    }
}
