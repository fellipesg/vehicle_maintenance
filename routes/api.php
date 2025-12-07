<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\InvoiceController;

Route::prefix('api/v1')->group(function () {
    // Public routes (authentication)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
    Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);
    
    // Search vehicle by license plate or RENAVAM (public for checking vehicle history)
    Route::get('/vehicles/search/{identifier}', [VehicleController::class, 'search']);
    
    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        
        // User's vehicles (vehicles owned by authenticated user)
        Route::get('/my-vehicles', [VehicleController::class, 'myVehicles']);
        
        // Vehicle routes
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::get('/vehicles/{id}', [VehicleController::class, 'show']);
        Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);
        Route::get('/vehicles/{id}/maintenances', [VehicleController::class, 'maintenances']);
        
        // Link vehicle to user
        Route::post('/vehicles/{id}/link', [VehicleController::class, 'linkToUser']);
        
        // Maintenance routes
        Route::get('/maintenances', [MaintenanceController::class, 'index']);
        Route::post('/maintenances', [MaintenanceController::class, 'store']);
        Route::get('/maintenances/{id}', [MaintenanceController::class, 'show']);
        Route::put('/maintenances/{id}', [MaintenanceController::class, 'update']);
        Route::delete('/maintenances/{id}', [MaintenanceController::class, 'destroy']);
        
        // Export maintenance history to PDF
        Route::get('/vehicles/{id}/export-pdf', [VehicleController::class, 'exportPdf']);
        
        // Invoice routes
        Route::post('/invoices/upload', [InvoiceController::class, 'upload']);
        Route::get('/invoices/{id}/download', [InvoiceController::class, 'download']);
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy']);
    });
});

