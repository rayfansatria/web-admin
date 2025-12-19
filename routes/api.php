<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\InstitutionController;
use App\Models\Institution;
use App\Models\AppBuild;

// List institutions
Route::get('/institutions', fn() =>
    Institution::select('id','code','name','is_active')->orderBy('name')->paginate(20)
);

// Detail + settings (JSON)
Route::get('/institutions/{id}', [InstitutionController::class, 'showApi']);

// Update settings (accept POST or PUT)
Route::match(['post','put'], '/institutions/{id}/settings', [InstitutionController::class, 'updateSettings']);

// Generate APK
Route::post('/institutions/{id}/generate-app', [InstitutionController::class, 'generateApp']);

// Cek status build
Route::get('/builds/{buildId}', [InstitutionController::class, 'buildStatus']);

// List app builds (for admin UI)
Route::get('/app-builds', [InstitutionController::class, 'listBuilds']);