<?php

namespace App\Http\Controllers;

use App\Models\OrganizationType;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationUnitController extends Controller
{
    public function index(Request $request)
    {
        $query = OrganizationUnit::with(['type', 'parent', 'head']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Type filter
        if ($request->filled('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        // Parent filter
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Status filter
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $perPage = in_array((int) $request->input('per_page', 10), [5, 10, 25, 50, 100]) ? (int) $request->input('per_page', 10) : 10;

        $units = $query->orderBy('type_id')->orderBy('name')->paginate($perPage)->withQueryString();
        $types = OrganizationType::orderBy('level')->get();
        $parentUnits = OrganizationUnit::orderBy('name')->get();
        
        return view('organization-units.index', compact('units', 'types', 'parentUnits'));
    }

    public function create()
    {
        $types = OrganizationType::orderBy('level')->get();
        $parentUnits = OrganizationUnit::with('type')->active()->orderBy('name')->get();
        $users = User::orderBy('name')->get();
        
        return view('organization-units.create', compact('types', 'parentUnits', 'users'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:organization_units',
            'type_id' => 'required|exists:organization_types,id',
            'parent_id' => 'nullable|exists:organization_units,id',
            'head_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        OrganizationUnit::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type_id' => $request->type_id,
            'parent_id' => $request->parent_id,
            'head_id' => $request->head_id,
            'description' => $request->description,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->route('organization-units.index')->with('success', 'Unit Organisasi berhasil dibuat!');
    }

    public function show(OrganizationUnit $organizationUnit)
    {
        $organizationUnit->load(['type', 'parent', 'head', 'members', 'children.type']);
        
        // Get available users (not in this unit)
        $availableUsers = User::whereNull('organization_unit_id')
            ->orWhere('organization_unit_id', '!=', $organizationUnit->id)
            ->orderBy('name')
            ->get();
        
        // Get all users for head selection
        $allUsers = User::orderBy('name')->get();
        
        return view('organization-units.show', compact('organizationUnit', 'availableUsers', 'allUsers'));
    }

    public function edit(OrganizationUnit $organizationUnit)
    {
        $types = OrganizationType::orderBy('level')->get();
        // Exclude self and descendants from parent options
        $excludeIds = $this->getDescendantIds($organizationUnit);
        $excludeIds[] = $organizationUnit->id;
        
        $parentUnits = OrganizationUnit::with('type')
            ->active()
            ->whereNotIn('id', $excludeIds)
            ->orderBy('name')
            ->get();
        $users = User::orderBy('name')->get();
        
        return view('organization-units.edit', compact('organizationUnit', 'types', 'parentUnits', 'users'));
    }

    public function update(Request $request, OrganizationUnit $organizationUnit)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:organization_units,code,' . $organizationUnit->id,
            'type_id' => 'required|exists:organization_types,id',
            'parent_id' => 'nullable|exists:organization_units,id',
            'head_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Prevent setting self as parent
        if ($request->parent_id == $organizationUnit->id) {
            return redirect()->back()->with('error', 'Unit tidak bisa menjadi parent dari dirinya sendiri!')->withInput();
        }

        $organizationUnit->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type_id' => $request->type_id,
            'parent_id' => $request->parent_id,
            'head_id' => $request->head_id,
            'description' => $request->description,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()->route('organization-units.index')->with('success', 'Unit Organisasi berhasil diperbarui!');
    }

    public function destroy(OrganizationUnit $organizationUnit)
    {
        // Check if unit has children
        if ($organizationUnit->children()->count() > 0) {
            return redirect()->route('organization-units.index')->with('error', 'Unit tidak dapat dihapus karena masih memiliki sub-unit!');
        }

        // Check if unit has members
        if ($organizationUnit->members()->count() > 0) {
            return redirect()->route('organization-units.index')->with('error', 'Unit tidak dapat dihapus karena masih memiliki anggota!');
        }

        $organizationUnit->delete();
        return redirect()->route('organization-units.index')->with('success', 'Unit Organisasi berhasil dihapus!');
    }

    /**
     * Add a member to the organization unit
     */
    public function addMember(Request $request, OrganizationUnit $organizationUnit)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'User tidak valid!');
        }

        $user = User::find($request->user_id);
        $user->update(['organization_unit_id' => $organizationUnit->id]);

        return redirect()->route('organization-units.show', $organizationUnit)
            ->with('success', "User {$user->name} berhasil ditambahkan ke unit!");
    }

    /**
     * Remove a member from the organization unit
     */
    public function removeMember(OrganizationUnit $organizationUnit, User $user)
    {
        // Don't allow removing the head
        if ($organizationUnit->head_id == $user->id) {
            return redirect()->route('organization-units.show', $organizationUnit)
                ->with('error', 'Tidak dapat menghapus kepala unit. Ganti kepala unit terlebih dahulu!');
        }

        $user->update(['organization_unit_id' => null]);

        return redirect()->route('organization-units.show', $organizationUnit)
            ->with('success', "User {$user->name} berhasil dihapus dari unit!");
    }

    /**
     * Update the head of the organization unit
     */
    public function updateHead(Request $request, OrganizationUnit $organizationUnit)
    {
        $validator = Validator::make($request->all(), [
            'head_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'User tidak valid!');
        }

        $organizationUnit->update(['head_id' => $request->head_id]);

        $headName = $request->head_id ? User::find($request->head_id)->name : 'Tidak ada';
        return redirect()->route('organization-units.show', $organizationUnit)
            ->with('success', "Kepala unit berhasil diubah menjadi: {$headName}");
    }

    /**
     * Get all descendant IDs recursively
     */
    private function getDescendantIds(OrganizationUnit $unit): array
    {
        $ids = [];
        foreach ($unit->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }
        return $ids;
    }
}

