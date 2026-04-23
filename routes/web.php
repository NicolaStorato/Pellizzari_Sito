<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispenserController;
use App\Http\Controllers\DoctorAppointmentController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\MqttCommandController;
use App\Http\Controllers\PatientAppointmentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\SensorLogController;
use App\Http\Controllers\TherapyPlanController;
use App\Http\Controllers\UserManagementController;
use App\UserRole;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:'.UserRole::Patient->value)
        ->group(function (): void {
            Route::get('/appointments', [PatientAppointmentController::class, 'index'])
                ->name('appointments.index');
            Route::post('/appointments', [PatientAppointmentController::class, 'store'])
                ->name('appointments.store');
            Route::patch('/appointments/{appointment}/cancel', [PatientAppointmentController::class, 'cancel'])
                ->name('appointments.cancel');
        });

    Route::middleware('role:'.UserRole::Doctor->value)
        ->group(function (): void {
            Route::get('/doctor/appointments', [DoctorAppointmentController::class, 'index'])
                ->name('doctor-appointments.index');
            Route::patch('/doctor/appointments/{appointment}', [DoctorAppointmentController::class, 'update'])
                ->name('doctor-appointments.update');
        });

    Route::middleware('role:'.UserRole::Admin->value.','.UserRole::Doctor->value)
        ->group(function (): void {
            Route::get('/sensor-logs', [SensorLogController::class, 'index'])
                ->name('sensor-logs.index');

            Route::get('/alerts', [AlertController::class, 'index'])
                ->name('alerts.index');

            Route::get('/user-management', [UserManagementController::class, 'index'])
                ->name('user-management.index');
            Route::get('/user-management/{user}/edit', [UserManagementController::class, 'edit'])
                ->name('user-management.edit');
            Route::patch('/user-management/{user}', [UserManagementController::class, 'update'])
                ->name('user-management.update');
            Route::post('/user-management', [UserManagementController::class, 'store'])
                ->name('user-management.store');

            Route::resource('patients', PatientController::class)
                ->parameters(['patients' => 'patient']);
            Route::resource('medicines', MedicineController::class);
            Route::resource('therapy-plans', TherapyPlanController::class);
            Route::resource('dispensers', DispenserController::class);

            Route::patch('/alerts/{alert}/resolve', [AlertController::class, 'resolve'])
                ->name('alerts.resolve');

            Route::post('/dispensers/{dispenser}/mqtt-command', MqttCommandController::class)
                ->name('dispensers.mqtt-command');

            // Invia manualmente la terapia al dispenser via MQTT
            Route::post('/therapy-plans/{therapy_plan}/send-mqtt', [TherapyPlanController::class, 'sendViaMqtt'])
                ->name('therapy-plans.send-mqtt');

            // Pubblica tutte le terapie attive del paziente associato al dispenser
            Route::post('/dispensers/{dispenser}/publish-all-therapies', [DispenserController::class, 'publishAllTherapies'])
                ->name('dispensers.publish-all-therapies');
        });
});
