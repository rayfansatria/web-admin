<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\InstitutionController;

// Login sederhana (opsional untuk dev)
Route::get('/login', fn() => view('auth.login'))->name('login');
Route::post('/login', function (Request $request) {
    $credentials = $request->only('email', 'password');
    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        return redirect()->intended('/admin/institutions');
    }
    return back()->withErrors(['email' => 'Login gagal. Periksa email/password.']);
})->name('login.post');
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

// Halaman admin
Route::middleware(['web'])->group(function () {
    Route::get('/admin/institutions', [InstitutionController::class, 'index'])->name('admin.institutions');
    Route::get('/admin/institutions/{id}/configure', [InstitutionController::class, 'show'])->name('admin.institutions.configure');
});

