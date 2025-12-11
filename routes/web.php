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
})->name('logout.post');

// Halaman admin
Route::middleware(['web'])->group(function () {
    Route::get('/admin/institutions', [InstitutionController::class, 'index'])->name('admin.institutions');

    // Configure & related
    Route::get('/admin/institutions/{id}/configure', [InstitutionController::class, 'show'])->name('admin.institutions.configure');
    Route::get('/admin/institutions/{id}/features', [InstitutionController::class, 'featuresPage'])->name('admin.institutions.features');

    // Create / Edit web UI
    Route::get('/admin/institutions/create', [InstitutionController::class, 'create'])->name('admin.institutions.create');
    Route::post('/admin/institutions', [InstitutionController::class, 'store'])->name('admin.institutions.store');
    Route::get('/admin/institutions/{id}/edit', [InstitutionController::class, 'edit'])->name('admin.institutions.edit');
    Route::post('/admin/institutions/{id}', [InstitutionController::class, 'update'])->name('admin.institutions.update');
});