<?php

use App\Http\Controllers\DownloadFhirPatientController;
use App\Livewire\AuditLogs\Index as AuditLogs;
use App\Livewire\Dashboard;
use App\Livewire\Patients\Create as CreatePatient;
use App\Livewire\Patients\Index as Patients;
use App\Livewire\Patients\Show as ShowPatient;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? route('dashboard') : route('login')))->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/patients', Patients::class)->name('patients.index');
    Route::get('/patients/create', CreatePatient::class)->name('patients.create');
    Route::get('/patients/{patient}', ShowPatient::class)->name('patients.show');
    Route::get('/patients/{patient}/fhir', DownloadFhirPatientController::class)->name('patients.fhir.download');
    Route::get('/audit-logs', AuditLogs::class)->name('audit-logs.index');
});
