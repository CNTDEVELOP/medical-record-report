<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Reports\TopDiagnosisController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/reports/top-diagnosis', [TopDiagnosisController::class, 'view'])
    ->name('reports.top-diagnosis');