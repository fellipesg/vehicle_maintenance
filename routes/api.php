<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserFcmTokenController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehiclePdfExportController;
use App\Http\Controllers\Api\WorkshopController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes (authentication)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
    Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])
        ->middleware('throttle:auth');

    // Search vehicle by license plate or RENAVAM (public for checking vehicle history)
    Route::get('/vehicles/search/{identifier}', [VehicleController::class, 'search']);
    Route::get('/vehicle-catalog/brands', [VehicleController::class, 'catalogBrands']);
    Route::get('/vehicle-catalog/models', [VehicleController::class, 'catalogModels']);
    Route::get('/legal/terms-of-use', [LegalController::class, 'termsOfUse']);

    // Workshop routes (public - visible to all users)
    Route::get('/workshops', [WorkshopController::class, 'index']);
    Route::get('/workshops/{id}', [WorkshopController::class, 'show']);

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me'])->middleware('ability:profile:read');
        Route::put('/me', [ProfileController::class, 'update'])->middleware('ability:profile:write');
        Route::post('/me/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('ability:profile:write');

        // User's vehicles (vehicles owned by authenticated user)
        Route::get('/my-vehicles', [VehicleController::class, 'myVehicles'])->middleware('ability:vehicles:read');

        // Vehicle routes
        Route::get('/vehicles', [VehicleController::class, 'index'])->middleware('ability:vehicles:read');
        Route::post('/vehicles', [VehicleController::class, 'store'])->middleware('ability:vehicles:write');
        Route::get('/vehicles/{id}', [VehicleController::class, 'show'])->middleware('ability:vehicles:read');
        Route::put('/vehicles/{id}', [VehicleController::class, 'update'])->middleware('ability:vehicles:write');
        Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy'])->middleware('ability:vehicles:write');
        Route::post('/vehicles/{id}/cover', [VehicleController::class, 'uploadCover'])->middleware('ability:vehicles:write');
        Route::get('/vehicles/{id}/maintenances', [VehicleController::class, 'maintenances'])->middleware('ability:maintenances:read');
        Route::get('/vehicles/{id}/timeline', [VehicleController::class, 'timeline'])->middleware('ability:vehicles:read');

        // Link vehicle to user
        Route::post('/vehicles/{id}/link', [VehicleController::class, 'linkToUser'])->middleware('ability:vehicles:write');

        // Maintenance routes
        Route::get('/maintenances', [MaintenanceController::class, 'index'])->middleware('ability:maintenances:read');
        Route::post('/maintenances', [MaintenanceController::class, 'store'])->middleware('ability:maintenances:write');
        Route::get('/maintenances/{id}', [MaintenanceController::class, 'show'])->middleware('ability:maintenances:read');
        Route::put('/maintenances/{id}', [MaintenanceController::class, 'update'])->middleware('ability:maintenances:write');
        Route::delete('/maintenances/{id}', [MaintenanceController::class, 'destroy'])->middleware('ability:maintenances:write');

        // Export maintenance history to PDF (async)
        Route::post('/vehicles/{id}/export-pdf', [VehicleController::class, 'requestExportPdf'])->middleware('ability:vehicles:read');
        Route::get('/vehicle-pdf-exports/{exportId}', [VehiclePdfExportController::class, 'show'])->middleware('ability:vehicles:read');
        Route::get('/vehicle-pdf-exports/{exportId}/download', [VehiclePdfExportController::class, 'download'])->middleware('ability:vehicles:read');

        // Invoice routes
        Route::post('/invoices/upload', [InvoiceController::class, 'upload'])->middleware('ability:invoices:write');
        Route::get('/invoices/{id}/download', [InvoiceController::class, 'download'])->middleware('ability:maintenances:read');
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy'])->middleware('ability:invoices:write');

        // Workshop routes (create, update, delete require authentication)
        Route::post('/workshops', [WorkshopController::class, 'store'])->middleware('ability:workshops:write');
        Route::put('/workshops/{id}', [WorkshopController::class, 'update'])->middleware('ability:workshops:write');
        Route::delete('/workshops/{id}', [WorkshopController::class, 'destroy'])->middleware('ability:workshops:write');

        // FCM Token routes
        Route::get('/fcm-tokens', [UserFcmTokenController::class, 'index'])->middleware('ability:fcm:write');
        Route::post('/fcm-tokens', [UserFcmTokenController::class, 'store'])->middleware('ability:fcm:write');
        Route::delete('/fcm-tokens/{token}', [UserFcmTokenController::class, 'destroy'])->middleware('ability:fcm:write');
    });

    require __DIR__.'/api-two-factor.php';
});
