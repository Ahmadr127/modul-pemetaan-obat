<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type_id',
        'parent_id',
        'head_id',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the type of this organization unit
     */
    public function type()
    {
        return $this->belongsTo(OrganizationType::class, 'type_id');
    }

    /**
     * Get the parent organization unit
     */
    public function parent()
    {
        return $this->belongsTo(OrganizationUnit::class, 'parent_id');
    }

    /**
     * Get all child organization units
     */
    public function children()
    {
        return $this->hasMany(OrganizationUnit::class, 'parent_id');
    }

    /**
     * Get all descendants recursively
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the head/manager of this unit
     */
    public function head()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    /**
     * Get all users/members in this unit
     */
    public function members()
    {
        return $this->hasMany(User::class, 'organization_unit_id');
    }

    /**
     * Get all ancestors (parent hierarchy)
     */
    public function ancestors()
    {
        $ancestors = collect();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the full path as string (e.g., "PT > RS > Direktorat > Departemen")
     */
    public function getFullPathAttribute()
    {
        return $this->ancestors()->reverse()->pluck('name')->push($this->name)->implode(' > ');
    }

    /**
     * Scope for active units only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for root units (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
