<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DepartamentController;
use App\Http\Controllers\Api\HourController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    
    // Autenticación pública
    Route::post('/login', [AuthController::class, 'login']);

    // Rutas protegidas por el Middleware de Token
    Route::middleware('api.token')->group(function () {
        
        // Perfil de usuario logueado
        Route::get('/me', [AuthController::class, 'me']);

        // Endpoints de Asistencias (Lógica de marcado de tiempos)
        Route::prefix('attendance')->group(function () {
            Route::get('/today', [AttendanceController::class, 'today']);
            Route::post('/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('/check-out', [AttendanceController::class, 'checkOut']);
            Route::post('/lunch/start', [AttendanceController::class, 'startLunch']);
            Route::post('/lunch/end', [AttendanceController::class, 'endLunch']);
        // metodo para  escanear el codigo QR y registrar la asistencia
        Route::post('/scan', [AttendanceController::class, 'scanQr']);
        });

        // Recursos CRUD completos
        Route::apiResource('activities', ActivityController::class);
        Route::apiResource('attendances', AttendanceController::class);
        Route::apiResource('calendars', CalendarController::class);
        Route::apiResource('departaments', DepartamentController::class);
        Route::apiResource('hours', HourController::class);
        Route::apiResource('reports', ReportController::class);
        Route::apiResource('schedules', ScheduleController::class);
    });
});