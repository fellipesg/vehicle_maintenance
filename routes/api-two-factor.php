<?php

use App\Http\Controllers\Api\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::post('/two-factor/challenge', [TwoFactorController::class, 'challenge']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable']);
    Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable']);
    Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
});
