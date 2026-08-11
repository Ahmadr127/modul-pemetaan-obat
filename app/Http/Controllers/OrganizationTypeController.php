<?php

namespace App\Http\Controllers;

use App\Models\OrganizationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = OrganizationType::query();

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        $perPage = in_array((int) $request->input('per_page', 10), [5, 10, 25, 50, 100]) ? (int) $request->input('per_page', 10) : 10;

        $types = $query->orderBy('level')->paginate($perPage)->withQueryString();
        
        return view('organization-types.index', compact('types'));
    }

    public function create()
    {
        return view('organization-types.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:organization_types',
            'display_name' => 'required|string|max:255',
            'level' => 'required|integer|min:1',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        OrganizationType::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'level' => $request->level,
            'description' => $request->description
        ]);

        return redirect()->route('organization-types.index')->with('success', 'Tipe Organisasi berhasil dibuat!');
    }

    public function edit(OrganizationType $organizationType)
    {
        return view('organization-types.edit', compact('organizationType'));
    }

    public function update(Request $request, OrganizationType $organizationType)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:organization_types,name,' . $organizationType->id,
            'display_name' => 'required|string|max:255',
            'level' => 'required|integer|min:1',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $organizationType->update([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'level' => $request->level,
            'description' => $request->description
        ]);

        return redirect()->route('organization-types.index')->with('success', 'Tipe Organisasi berhasil diperbarui!');
    }

    public function destroy(OrganizationType $organizationType)
    {
        // Check if type is used by any organization unit
        if ($organizationType->organizationUnits()->count() > 0) {
            return redirect()->route('organization-types.index')->with('error', 'Tipe organisasi tidak dapat dihapus karena masih digunakan!');
        }

        $organizationType->delete();
        return redirect()->route('organization-types.index')->with('success', 'Tipe Organisasi berhasil dihapus!');
    }
}
