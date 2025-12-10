<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\InstitutionController; // <- tambahkan ini
use App\Models\Institution;

// List institutions
Route::get('/institutions', fn() =>
    Institution::select('id','code','name','is_active')->orderBy('name')->paginate(20)
);

// Detail + settings (JSON)
Route::get('/institutions/{id}', [InstitutionController::class, 'showApi']);

// Update settings
Route::post('/institutions/{id}/settings', [InstitutionController::class, 'updateSettings']);

// Generate APK
Route::post('/institutions/{id}/generate-app', [InstitutionController::class, 'generateApp']);

// Cek status build
Route::get('/builds/{buildId}', [InstitutionController::class, 'buildStatus']);