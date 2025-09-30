<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\ManageScheduleController;
use App\Http\Controllers\Admin\TimeSlotController;
use App\Http\Controllers\Admin\ProcedureController;
use App\Http\Controllers\Reports\TopDiagnosisController;

Route::get('/reports/top-diagnosis', [TopDiagnosisController::class, 'index']);
