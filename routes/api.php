<?php

use App\Http\Controllers\Api\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MaintenanceInviteController;
use App\Http\Controllers\Api\MaintenancePhotoController;
use App\Http\Controllers\Api\MaintenancePhotoSignedFileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserFcmTokenController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehiclePdfExportController;
use App\Http\Controllers\Api\WarrantyTemplateController;
use App\Http\Controllers\Api\WorkshopController;
use App\Http\Controllers\Api\WorkshopMessageTemplateController;
use App\Http\Controllers\Api\WorkshopRecordController;
use App\Http\Controllers\Api\WorkshopVehicleController;
use App\Support\Maintenance\MaintenancePhotoUrls;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes (authentication)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('/auth/apple', [AuthController::class, 'loginWithApple'])->middleware('throttle:auth');
    Route::post('/auth/google', [AuthController::class, 'loginWithGoogle'])->middleware('throttle:auth');
    Route::get('/auth/{provider}/redirect', [AuthController::class, 'redirectToProvider']);
    Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback'])
        ->middleware('throttle:auth');

    // Search vehicle by license plate or RENAVAM (public for checking vehicle history)
    Route::get('/vehicles/search/{identifier}', [VehicleController::class, 'search'])
        ->middleware('throttle:search');
    Route::get('/vehicle-catalog/brands', [VehicleController::class, 'catalogBrands'])
        ->middleware('throttle:api');
    Route::get('/vehicle-catalog/models', [VehicleController::class, 'catalogModels'])
        ->middleware('throttle:api');
    Route::get('/legal/terms-of-use', [LegalController::class, 'termsOfUse'])
        ->middleware('throttle:api');
    Route::get('/legal/privacy-policy', [LegalController::class, 'privacyPolicy'])
        ->middleware('throttle:api');

    // Recuperação de senha: o link do e-mail abre a página do portal, que é quem troca a senha.
    Route::post('/password/forgot', [PasswordController::class, 'forgot'])
        ->middleware('throttle:password-forgot');

    // Arquivo de uma foto de manutenção por URL assinada de vida curta. Quem recebe a URL já passou
    // pela MaintenancePhotoPolicy::view ao montar o MaintenancePhotoResource; a assinatura vale por
    // uma foto e uma variante. Sem auth:sanctum porque o carregador de imagem do app e o <img> não
    // mandam Authorization. Só o disco local usa esta rota (S3/R2 assina no próprio storage).
    Route::get('/maintenance-photo-files/{photo}/{variant}', MaintenancePhotoSignedFileController::class)
        ->whereNumber('photo')
        ->whereIn('variant', MaintenancePhotoUrls::VARIANTS)
        ->middleware(['signed', 'throttle:api'])
        ->name('api.maintenance-photos.signed');

    // Workshop routes (public - visible to all users)
    Route::get('/workshops', [WorkshopController::class, 'index'])->middleware('throttle:api');
    Route::get('/workshops/{id}', [WorkshopController::class, 'show'])->middleware('throttle:api');

    // Protected routes (require authentication)
    // garage.approved:api — loja em análise só lê pela API. Sem isto o portão existiria apenas
    // na web, e o app seria o caminho livre para escrever no histórico de um chassi e mandar
    // convite para terceiros (App\Http\Middleware\EnsureGarageApproved).
    // workshop.approved:api — oficina em análise só lê pela API, pelo mesmo motivo e no mesmo
    // formato do portão da loja: pelo app ela criaria OS com Selo, moveria o hodômetro de um
    // veículo e mandaria convite para terceiros (App\Http\Middleware\EnsureWorkshopApproved).
    Route::middleware(['auth:sanctum', 'tenant', 'throttle:api', 'garage.approved:api', 'workshop.approved:api'])->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me'])->middleware('ability:profile:read');
        Route::put('/me', [ProfileController::class, 'update'])->middleware('ability:profile:write');
        Route::delete('/me', [ProfileController::class, 'destroy'])->middleware('ability:profile:write');
        Route::post('/me/avatar', [ProfileController::class, 'uploadAvatar'])->middleware(['ability:profile:write', 'throttle:uploads']);
        Route::put('/me/password', [PasswordController::class, 'update'])->middleware('ability:profile:write');

        // User's vehicles (vehicles owned by authenticated user)
        Route::get('/my-vehicles', [VehicleController::class, 'myVehicles'])
            ->middleware(['ability:vehicles:read', 'etag.vehicle_list']);

        Route::get('/admin/vehicles', [AdminVehicleController::class, 'index'])
            ->middleware('ability:vehicles:read');

        // Vehicle routes
        Route::get('/vehicles', [VehicleController::class, 'index'])->middleware('ability:vehicles:read');
        Route::post('/vehicles', [VehicleController::class, 'store'])->middleware('ability:vehicles:write');
        Route::get('/vehicles/{id}', [VehicleController::class, 'show'])->middleware('ability:vehicles:read');
        Route::get('/vehicles/{id}/plates', [VehicleController::class, 'plates'])->middleware('ability:vehicles:read');
        Route::put('/vehicles/{id}', [VehicleController::class, 'update'])->middleware('ability:vehicles:write');
        Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy'])->middleware('ability:vehicles:write');
        Route::post('/vehicles/{id}/cover', [VehicleController::class, 'uploadCover'])->middleware(['ability:vehicles:write', 'throttle:uploads']);
        Route::get('/vehicles/{id}/maintenances', [VehicleController::class, 'maintenances'])->middleware('ability:maintenances:read');
        Route::get('/vehicles/{id}/timeline', [VehicleController::class, 'timeline'])->middleware('ability:vehicles:read');

        // Link vehicle to user
        Route::post('/vehicles/{id}/link', [VehicleController::class, 'linkToUser'])
            ->middleware(['ability:vehicles:write', 'throttle:vehicle-link']);

        // Maintenance routes
        Route::get('/maintenances', [MaintenanceController::class, 'index'])->middleware('ability:maintenances:read');
        // Store e update recebem `invoices` (InvoiceFile lê o arquivo inteiro), como /invoices/upload:
        // o balde 'uploads' fica por cima do 'throttle:api' do grupo.
        Route::post('/maintenances', [MaintenanceController::class, 'store'])->middleware(['ability:maintenances:write', 'throttle:uploads']);
        Route::get('/maintenances/{id}', [MaintenanceController::class, 'show'])->middleware('ability:maintenances:read');
        Route::put('/maintenances/{id}', [MaintenanceController::class, 'update'])->middleware(['ability:maintenances:write', 'throttle:uploads']);
        Route::delete('/maintenances/{id}', [MaintenanceController::class, 'destroy'])->middleware('ability:maintenances:write');

        // OS de oficina em carro sem proprietário: decisão do proprietário e convite do cliente.
        Route::get('/me/workshop-records', [WorkshopRecordController::class, 'index'])->middleware('ability:maintenances:read');
        Route::post('/maintenances/{id}/owner-decision', [WorkshopRecordController::class, 'decide'])->middleware('ability:maintenances:write');
        // Contestar não exige CRLV-e, ao contrário de owner-decision (MaintenanceOwnerDecisionService::dispute).
        Route::post('/maintenances/{id}/dispute', [WorkshopRecordController::class, 'dispute'])->middleware('ability:maintenances:write');
        Route::delete('/maintenances/{id}/dispute', [WorkshopRecordController::class, 'withdrawDispute'])->middleware('ability:maintenances:write');
        Route::post('/maintenances/{id}/invites/email', [MaintenanceInviteController::class, 'email'])->middleware(['ability:maintenances:write', 'throttle:workshop-invites']);
        Route::post('/maintenances/{id}/invites/whatsapp', [MaintenanceInviteController::class, 'whatsapp'])->middleware(['ability:maintenances:write', 'throttle:workshop-invites']);

        // Oficina: veículo pelo chassi, sem placa nem dados do dono.
        Route::get('/workshop/vehicles/lookup', [WorkshopVehicleController::class, 'lookup'])->middleware(['ability:vehicles:read', 'throttle:workshop-chassis-lookup']);
        Route::post('/workshop/vehicles', [WorkshopVehicleController::class, 'store'])->middleware(['ability:vehicles:write', 'throttle:workshop-chassis-lookup']);

        Route::post('/maintenances/{maintenance}/photos', [MaintenancePhotoController::class, 'store'])
            ->middleware(['ability:maintenances:write', 'throttle:uploads']);
        Route::delete('/maintenances/{maintenance}/photos/{photo}', [MaintenancePhotoController::class, 'destroy'])
            ->middleware('ability:maintenances:write');

        // Foto de uma manutenção: quem pode abrir é a MaintenancePhotoPolicy::view, não quem
        // tiver o link do arquivo no storage. É o que o MaintenancePhotoResource devolve.
        Route::get('/maintenance-photos/{photo}', [MaintenancePhotoController::class, 'show'])
            ->middleware('ability:maintenances:read')
            ->whereNumber('photo')
            ->name('api.maintenance-photos.show');

        // Export maintenance history to PDF (async)
        Route::post('/vehicles/{id}/export-pdf', [VehicleController::class, 'requestExportPdf'])->middleware('ability:vehicles:read');
        Route::get('/vehicle-pdf-exports/{exportId}', [VehiclePdfExportController::class, 'show'])->middleware('ability:vehicles:read');
        Route::get('/vehicle-pdf-exports/{exportId}/download', [VehiclePdfExportController::class, 'download'])->middleware('ability:vehicles:read');

        // Invoice routes
        Route::post('/invoices/upload', [InvoiceController::class, 'upload'])->middleware(['ability:invoices:write', 'throttle:uploads']);
        Route::get('/invoices/{id}/download', [InvoiceController::class, 'download'])->middleware('ability:maintenances:read');
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy'])->middleware('ability:invoices:write');

        // Workshop routes (create, update, delete require authentication)
        Route::post('/workshops', [WorkshopController::class, 'store'])->middleware('ability:workshops:write');
        Route::put('/workshops/{id}', [WorkshopController::class, 'update'])->middleware('ability:workshops:write');
        Route::delete('/workshops/{id}', [WorkshopController::class, 'destroy'])->middleware('ability:workshops:write');

        Route::apiResource('workshops.message-templates', WorkshopMessageTemplateController::class)
            ->scoped()
            ->middleware([
                'index' => 'ability:workshops:read',
                'show' => 'ability:workshops:read',
                'store' => 'ability:workshops:write',
                'update' => 'ability:workshops:write',
                'destroy' => 'ability:workshops:write',
            ]);

        Route::apiResource('workshops.warranty-templates', WarrantyTemplateController::class)
            ->scoped()
            ->middleware([
                'index' => 'ability:workshops:read',
                'show' => 'ability:workshops:read',
                'store' => 'ability:workshops:write',
                'update' => 'ability:workshops:write',
                'destroy' => 'ability:workshops:write',
            ]);

        // Notificações da conta (sino). Usa as abilities de perfil, que todo token do app já tem.
        Route::get('/notifications', [NotificationController::class, 'index'])->middleware('ability:profile:read');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->middleware('ability:profile:read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('ability:profile:write');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('ability:profile:write');

        // FCM Token routes
        Route::get('/fcm-tokens', [UserFcmTokenController::class, 'index'])->middleware('ability:fcm:write');
        Route::post('/fcm-tokens', [UserFcmTokenController::class, 'store'])->middleware('ability:fcm:write');
        Route::delete('/fcm-tokens/{token}', [UserFcmTokenController::class, 'destroy'])->middleware('ability:fcm:write');
    });

    require __DIR__.'/api-two-factor.php';
});
