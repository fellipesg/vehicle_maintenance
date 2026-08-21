<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserFcmTokenController;
use App\Http\Controllers\Api\VehicleController;
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
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/me', [ProfileController::class, 'update']);
        Route::post('/me/avatar', [ProfileController::class, 'uploadAvatar']);

        // User's vehicles (vehicles owned by authenticated user)
        Route::get('/my-vehicles', [VehicleController::class, 'myVehicles']);

        // Vehicle routes
        Route::get('/vehicles', [VehicleController::class, 'index']);
        Route::post('/vehicles', [VehicleController::class, 'store']);
        Route::get('/vehicles/{id}', [VehicleController::class, 'show']);
        Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
        Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);
        Route::post('/vehicles/{id}/cover', [VehicleController::class, 'uploadCover']);
        Route::get('/vehicles/{id}/maintenances', [VehicleController::class, 'maintenances']);
        Route::get('/vehicles/{id}/timeline', [VehicleController::class, 'timeline']);

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

        // Workshop routes (create, update, delete require authentication)
        Route::post('/workshops', [WorkshopController::class, 'store']);
        Route::put('/workshops/{id}', [WorkshopController::class, 'update']);
        Route::delete('/workshops/{id}', [WorkshopController::class, 'destroy']);

        // FCM Token routes
        Route::get('/fcm-tokens', [UserFcmTokenController::class, 'index']);
        Route::post('/fcm-tokens', [UserFcmTokenController::class, 'store']);
        Route::delete('/fcm-tokens/{token}', [UserFcmTokenController::class, 'destroy']);
    });

    require __DIR__.'/api-two-factor.php';
});
