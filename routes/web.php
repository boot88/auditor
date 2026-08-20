<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiteAuditController;

Route::get('/audit', [SiteAuditController::class, 'create'])->name('audit.create');
Route::post('/audit', [SiteAuditController::class, 'store'])
->middleware('throttle:audit')
->name('audit.store');

Route::get('/audit/{audit}', [SiteAuditController::class, 'show'])->name('audit.show');
Route::get('/audit/{audit}/status', [SiteAuditController::class, 'status'])->name('audit.status');
