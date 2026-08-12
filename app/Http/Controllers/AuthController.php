<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showRegister()
    {
        $roles = Role::all();
        return view('auth.register', compact('roles'));
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required'
        ], [
            'login.required' => 'The email atau username field is required.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $loginType = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Manual authentication using username or email
        $user = User::where($loginType, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'login' => 'Email/Username atau password salah.',
            ])->withInput();
        }

        // Manual login
        Auth::login($user);
        $request->session()->regenerate();

        return $this->homeRedirect($request);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id
        ]);

        Auth::login($user);

        return $this->homeRedirect($request);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    /**
     * Arahkan user ke halaman pertama yang bisa diakses sesuai permission-nya.
     */
    protected function homeRedirect(Request $request)
    {
        $home = static::homePath();

        if ($home) {
            return redirect($home);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'login' => 'Akun Anda tidak memiliki akses ke sistem.',
        ]);
    }

    /**
     * URL halaman pertama yang dapat diakses user berdasarkan permission-nya.
     * Urutan prioritas: dashboard -> modul pemetaan obat -> pengelolaan data -> lainnya.
     */
    public static function homePath(): ?string
    {
        $permissionRoutes = [
            'view_dashboard' => 'dashboard',
            'manage_pemetaan_obat' => 'pemetaan-obat.index',
            'manage_users' => 'users.index',
            'manage_roles' => 'roles.index',
            'manage_permissions' => 'permissions.index',
            'manage_organization_units' => 'organization-units.index',
            'manage_organization_types' => 'organization-types.index',
        ];

        foreach ($permissionRoutes as $permission => $route) {
            if (Auth::user()->hasPermission($permission)) {
                return route($route);
            }
        }

        return null;
    }
}
