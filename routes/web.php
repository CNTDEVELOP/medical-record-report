<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Reports\TopDiagnosisController;
use App\Http\Controllers\Reports\TopAccidentController;
use App\Http\Controllers\Reports\TopDiagnosisSSSController;
use App\Http\Controllers\Reports\TopAccidentSSSController;
use App\Http\Controllers\Reports\TopOrthoSurgerySSSController;
use App\Http\Controllers\Reports\TopICD9OrthoSSSController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/reports/top-diagnosis', [TopDiagnosisController::class, 'view'])
    ->name('reports.top-diagnosis');

Route::get('/reports/top-accident', [TopAccidentController::class, 'view'])
->name('reports.top-accident');

Route::get('/reports/top-accident-sss', [TopAccidentSSSController::class, 'view'])
    ->name('reports.top-accident-sss');


Route::get('/reports/top-diagnosis-sss', [TopDiagnosisSSSController::class, 'view'])
    ->name('reports.top-diagnosis-sss');


// Route::get('/reports/top-ortho-surgery-sss', [TopOrthoSurgerySSSController::class, 'view'])
// ->name('reports.top-ortho-surgery-sss');


Route::get('/reports/top-icd9-ortho-sss', [TopICD9OrthoSSSController::class, 'view'])
    ->name('reports.top-icd9-ortho-sss');