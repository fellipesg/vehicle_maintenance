<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\PublicVehicleController;
use App\Http\Controllers\Web\User\DashboardController as UserDashboardController;
use App\Http\Controllers\Web\User\MaintenanceController as UserMaintenanceController;
use App\Http\Controllers\Web\User\VehicleController as UserVehicleController;
use App\Http\Controllers\Web\User\WorkshopDirectoryController;
use App\Http\Controllers\Web\Garage\DashboardController as GarageDashboardController;
use App\Http\Controllers\Web\Garage\MaintenanceController as GarageMaintenanceController;
use App\Http\Controllers\Web\Garage\VehicleController as GarageVehicleController;
use App\Http\Controllers\Web\Workshop\DashboardController as WorkshopDashboardController;
use App\Http\Controllers\Web\Workshop\ProfileController as WorkshopProfileController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\Admin\VehicleBrandController as AdminVehicleBrandController;
use App\Http\Controllers\Web\Admin\VehicleModelController as AdminVehicleModelController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/buscar-veiculo', [PublicVehicleController::class, 'search'])->name('vehicle.search');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginHub'])->name('login');
    Route::get('/login/admin', fn () => app(AuthController::class)->showLogin('admin'))->name('login.admin');
    Route::get('/login/lojista', fn () => app(AuthController::class)->showLogin('lojista'))->name('login.lojista');
    Route::get('/login/usuario', fn () => app(AuthController::class)->showLogin('usuario'))->name('login.usuario');
    Route::post('/login/{portal}', [AuthController::class, 'login'])
        ->whereIn('portal', ['admin', 'lojista', 'usuario'])
        ->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::prefix('usuario')->middleware('user.type:user')->name('user.')->group(function () {
        Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
        Route::get('/veiculos', [UserVehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/veiculos/novo', [UserVehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/veiculos/importar-crlv', [UserVehicleController::class, 'importCrlv'])->name('vehicles.import-crlv');
        Route::get('/veiculos/importar-crlv/preview', [UserVehicleController::class, 'previewCrlvImport'])->name('vehicles.import.preview');
        Route::get('/veiculos/vincular', [UserVehicleController::class, 'showClaimForm'])->name('vehicles.claim');
        Route::post('/veiculos/vincular/crlv', [UserVehicleController::class, 'importCrlvForClaim'])->name('vehicles.claim.import-crlv');
        Route::get('/veiculos/vincular/preview', [UserVehicleController::class, 'previewCrlvClaim'])->name('vehicles.claim.preview');
        Route::post('/veiculos/vincular', [UserVehicleController::class, 'claim'])->name('vehicles.claim.store');
        Route::get('/veiculos/consignacao', [UserVehicleController::class, 'showConsignmentForm'])->name('vehicles.consignment');
        Route::post('/veiculos/consignacao', [UserVehicleController::class, 'storeConsignment'])->name('vehicles.consignment.store');
        Route::post('/veiculos', [UserVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/veiculos/{vehicle}', [UserVehicleController::class, 'show'])->name('vehicles.show');
        Route::get('/veiculos/{vehicle}/editar', [UserVehicleController::class, 'edit'])->name('vehicles.edit');
        Route::post('/veiculos/{vehicle}/importar-crlv', [UserVehicleController::class, 'importCrlvForEdit'])->name('vehicles.import-crlv.edit');
        Route::put('/veiculos/{vehicle}', [UserVehicleController::class, 'update'])->name('vehicles.update');
        Route::get('/veiculos/{vehicle}/pdf', [UserVehicleController::class, 'exportPdf'])->name('vehicles.export-pdf');
        Route::get('/manutencoes', [UserMaintenanceController::class, 'index'])->name('maintenances.index');
        Route::get('/manutencoes/nova', [UserMaintenanceController::class, 'create'])->name('maintenances.create');
        Route::post('/manutencoes', [UserMaintenanceController::class, 'store'])->name('maintenances.store');
        Route::get('/manutencoes/{maintenance}', [UserMaintenanceController::class, 'show'])->name('maintenances.show');
        Route::get('/oficinas', [WorkshopDirectoryController::class, 'index'])->name('workshops.index');
    });

    Route::prefix('garagem')->middleware('user.type:garage')->name('garage.')->group(function () {
        Route::get('/dashboard', [GarageDashboardController::class, 'index'])->name('dashboard');
        Route::get('/estoque', [GarageVehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/estoque/novo', [GarageVehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/estoque/importar-crlv', [GarageVehicleController::class, 'importCrlv'])->name('vehicles.import-crlv');
        Route::get('/estoque/importar-crlv/preview', [GarageVehicleController::class, 'previewCrlvImport'])->name('vehicles.import.preview');
        Route::get('/estoque/vincular', [GarageVehicleController::class, 'showClaimForm'])->name('vehicles.claim');
        Route::post('/estoque/vincular/crlv', [GarageVehicleController::class, 'importCrlvForClaim'])->name('vehicles.claim.import-crlv');
        Route::get('/estoque/vincular/preview', [GarageVehicleController::class, 'previewCrlvClaim'])->name('vehicles.claim.preview');
        Route::post('/estoque/vincular', [GarageVehicleController::class, 'claim'])->name('vehicles.claim.store');
        Route::get('/estoque/consignacao', [GarageVehicleController::class, 'showConsignmentForm'])->name('vehicles.consignment');
        Route::post('/estoque/consignacao', [GarageVehicleController::class, 'storeConsignment'])->name('vehicles.consignment.store');
        Route::post('/estoque', [GarageVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/estoque/{vehicle}', [GarageVehicleController::class, 'show'])->name('vehicles.show');
        Route::get('/manutencoes', [GarageMaintenanceController::class, 'index'])->name('maintenances.index');
        Route::get('/manutencoes/nova', [GarageMaintenanceController::class, 'create'])->name('maintenances.create');
        Route::post('/manutencoes', [GarageMaintenanceController::class, 'store'])->name('maintenances.store');
    });

    Route::prefix('oficina')->middleware('user.type:workshop')->name('workshop.')->group(function () {
        Route::get('/dashboard', [WorkshopDashboardController::class, 'index'])->name('dashboard');
        Route::get('/perfil', [WorkshopProfileController::class, 'show'])->name('profile.show');
        Route::get('/perfil/criar', [WorkshopProfileController::class, 'create'])->name('profile.create');
        Route::post('/perfil', [WorkshopProfileController::class, 'store'])->name('profile.store');
        Route::get('/perfil/editar', [WorkshopProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/perfil', [WorkshopProfileController::class, 'update'])->name('profile.update');
        Route::get('/manutencoes', [WorkshopProfileController::class, 'maintenances'])->name('maintenances.index');
    });

    Route::prefix('admin')->middleware('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/usuarios/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::get('/marcas', [AdminVehicleBrandController::class, 'index'])->name('brands.index');
        Route::get('/marcas/nova', [AdminVehicleBrandController::class, 'create'])->name('brands.create');
        Route::post('/marcas', [AdminVehicleBrandController::class, 'store'])->name('brands.store');
        Route::get('/marcas/{brand}', [AdminVehicleBrandController::class, 'show'])->name('brands.show');
        Route::get('/marcas/{brand}/editar', [AdminVehicleBrandController::class, 'edit'])->name('brands.edit');
        Route::put('/marcas/{brand}', [AdminVehicleBrandController::class, 'update'])->name('brands.update');
        Route::delete('/marcas/{brand}', [AdminVehicleBrandController::class, 'destroy'])->name('brands.destroy');
        Route::post('/marcas/{brand}/modelos', [AdminVehicleModelController::class, 'store'])->name('brands.models.store');
        Route::put('/modelos/{model}', [AdminVehicleModelController::class, 'update'])->name('models.update');
        Route::delete('/modelos/{model}', [AdminVehicleModelController::class, 'destroy'])->name('models.destroy');
    });
});
