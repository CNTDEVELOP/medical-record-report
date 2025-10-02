<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ManageScheduleController;
use App\Http\Controllers\Admin\TimeSlotController;
use App\Http\Controllers\Admin\ProcedureController;
use App\Http\Controllers\Reports\TopDiagnosisController;
use App\Http\Controllers\Reports\TopAccidentController;
use App\Http\Controllers\Reports\TopDiagnosisSSSController;
use App\Http\Controllers\Reports\TopAccidentSSSController;
use App\Http\Controllers\Reports\TopOrthoSurgerySSSController;
use App\Http\Controllers\Reports\TopICD9OrthoSSSController;


Route::get('/reports/top-diagnosis', [TopDiagnosisController::class, 'index']);
Route::get('/reports/top-accident', [TopAccidentController::class, 'index']);
Route::get('/reports/top-diagnosis-sss', [TopDiagnosisSSSController::class, 'index']);
Route::get('/reports/top-accident-sss', [TopAccidentSSSController::class, 'index']);
Route::get('/reports/top-ortho-surgery-sss', [TopOrthoSurgerySSSController::class, 'index']);
Route::get('/reports/top-icd9-ortho-sss', [TopICD9OrthoSSSController::class, 'index']);