<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrganizationTypeController;
use App\Http\Controllers\OrganizationUnitController;
use App\Http\Controllers\PemetaanObatController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will be
| assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('/login');
});

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:view_dashboard')->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Profile routes
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'index'])->name('profile.index');

    // User Management routes
    Route::middleware('permission:manage_users')->group(function () {
        Route::resource('users', UserController::class);
    });

    // Role Management routes
    Route::middleware('permission:manage_roles')->group(function () {
        Route::resource('roles', RoleController::class);
    });

    // Permission Management routes
    Route::middleware('permission:manage_permissions')->group(function () {
        Route::resource('permissions', PermissionController::class);
    });

    // Organization Type Management routes
    Route::middleware('permission:manage_organization_types')->group(function () {
        Route::resource('organization-types', OrganizationTypeController::class);
    });

    // Organization Unit Management routes
    Route::middleware('permission:manage_organization_units')->group(function () {
        Route::resource('organization-units', OrganizationUnitController::class);

        // Member management routes
        Route::post('organization-units/{organization_unit}/members', [OrganizationUnitController::class, 'addMember'])
            ->name('organization-units.add-member');
        Route::delete('organization-units/{organization_unit}/members/{user}', [OrganizationUnitController::class, 'removeMember'])
            ->name('organization-units.remove-member');
        Route::patch('organization-units/{organization_unit}/head', [OrganizationUnitController::class, 'updateHead'])
            ->name('organization-units.update-head');
    });

    // Pemetaan Obat routes
    Route::middleware('permission:manage_pemetaan_obat')->group(function () {
        Route::get('pemetaan-obat', [PemetaanObatController::class, 'index'])->name('pemetaan-obat.index');

        // CRUD Obat Generik
        Route::get('pemetaan-obat/obat-generik', [PemetaanObatController::class, 'generikIndex'])->name('pemetaan-obat.generik');
        Route::post('pemetaan-obat/obat-generik', [PemetaanObatController::class, 'generikStore'])->name('pemetaan-obat.generik.store');
        Route::put('pemetaan-obat/obat-generik/{obat_generik}', [PemetaanObatController::class, 'generikUpdate'])->name('pemetaan-obat.generik.update');
        Route::delete('pemetaan-obat/obat-generik/{obat_generik}', [PemetaanObatController::class, 'generikDestroy'])->name('pemetaan-obat.generik.destroy');

        // CRUD Obat Brand
        Route::get('pemetaan-obat/obat-brand', [PemetaanObatController::class, 'brandIndex'])->name('pemetaan-obat.brand');
        Route::post('pemetaan-obat/obat-brand', [PemetaanObatController::class, 'brandStore'])->name('pemetaan-obat.brand.store');
        Route::put('pemetaan-obat/obat-brand/{obat_brand}', [PemetaanObatController::class, 'brandUpdate'])->name('pemetaan-obat.brand.update');
        Route::delete('pemetaan-obat/obat-brand/{obat_brand}', [PemetaanObatController::class, 'brandDestroy'])->name('pemetaan-obat.brand.destroy');

        // Search / autocomplete
        Route::get('pemetaan-obat/generik/search', [PemetaanObatController::class, 'searchGenerik'])->name('pemetaan-obat.generik.search');
        Route::get('pemetaan-obat/brand/search', [PemetaanObatController::class, 'searchBrand'])->name('pemetaan-obat.brand.search');
        Route::get('pemetaan-obat/generik/{obat_generik}/brand', [PemetaanObatController::class, 'generikBrands'])->name('pemetaan-obat.generik.brand');

        // CRUD pemetaan
        Route::post('pemetaan-obat', [PemetaanObatController::class, 'store'])->name('pemetaan-obat.store');
        Route::put('pemetaan-obat/{pemetaan}', [PemetaanObatController::class, 'update'])->name('pemetaan-obat.update');
        Route::delete('pemetaan-obat/{pemetaan}', [PemetaanObatController::class, 'destroy'])->name('pemetaan-obat.destroy');

        // Import Excel
        Route::get('pemetaan-obat/import/template', [PemetaanObatController::class, 'importTemplate'])->name('pemetaan-obat.import.template');
        Route::post('pemetaan-obat/import/preview', [PemetaanObatController::class, 'importPreview'])->name('pemetaan-obat.import.preview');
        Route::post('pemetaan-obat/import/confirm', [PemetaanObatController::class, 'importConfirm'])->name('pemetaan-obat.import.confirm');
    });

});
