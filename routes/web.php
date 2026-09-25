<?php

use App\Http\Controllers\Web\Admin\BlogCategoryController as AdminBlogCategoryController;
use App\Http\Controllers\Web\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Web\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Web\Admin\MaintenanceController as AdminMaintenanceController;
use App\Http\Controllers\Web\Admin\MapController as AdminMapController;
use App\Http\Controllers\Web\Admin\UserController as AdminUserController;
use App\Http\Controllers\Web\Admin\VehicleBrandController as AdminVehicleBrandController;
use App\Http\Controllers\Web\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\Web\Admin\VehicleModelController as AdminVehicleModelController;
use App\Http\Controllers\Web\Admin\WorkshopController as AdminWorkshopController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BlogController;
use App\Http\Controllers\Web\BlogFeedController;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\Garage\DashboardController as GarageDashboardController;
use App\Http\Controllers\Web\Garage\MaintenanceController as GarageMaintenanceController;
use App\Http\Controllers\Web\Garage\VehicleController as GarageVehicleController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PublicVehicleController;
use App\Http\Controllers\Web\SitemapController;
use App\Http\Controllers\Web\User\DashboardController as UserDashboardController;
use App\Http\Controllers\Web\User\MaintenanceController as UserMaintenanceController;
use App\Http\Controllers\Web\User\VehicleController as UserVehicleController;
use App\Http\Controllers\Web\User\VehiclePdfExportDownloadController;
use App\Http\Controllers\Web\User\WorkshopDirectoryController;
use App\Http\Controllers\Web\Workshop\DashboardController as WorkshopDashboardController;
use App\Http\Controllers\Web\Workshop\MaintenanceController as WorkshopMaintenanceController;
use App\Http\Controllers\Web\Workshop\ProfileController as WorkshopProfileController;
use App\Http\Controllers\Web\Workshop\WarrantyTemplateController as WorkshopWarrantyTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/termos', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacidade', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/feed', BlogFeedController::class)->name('blog.feed');
Route::get('/blog/categoria/{category}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/contato', [ContactController::class, 'show'])->name('contact.show');
Route::post('/contato', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');
Route::get('/v/{code}', [\App\Http\Controllers\Web\PublicVerificationController::class, 'show'])
    ->middleware('throttle:search')
    ->name('verification.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginHub'])->name('login');
    Route::get('/login/admin', fn () => app(AuthController::class)->showLogin('admin'))->name('login.admin');
    Route::get('/login/lojista', fn () => app(AuthController::class)->showLogin('lojista'))->name('login.lojista');
    Route::get('/login/usuario', fn () => app(AuthController::class)->showLogin('usuario'))->name('login.usuario');
    Route::get('/login/oficina', fn () => app(AuthController::class)->showLogin('oficina'))->name('login.oficina');
    Route::post('/login/{portal}', [AuthController::class, 'login'])
        ->whereIn('portal', ['admin', 'lojista', 'usuario', 'oficina'])
        ->middleware('throttle:auth-web')
        ->name('login.submit');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-web');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/buscar-veiculo', [PublicVehicleController::class, 'search'])->name('vehicle.search');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notificacoes/{notification}/lida', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notificacoes/lidas', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

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
        Route::post('/veiculos', [UserVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/veiculos/{vehicle}', [UserVehicleController::class, 'show'])->name('vehicles.show');
        Route::get('/veiculos/{vehicle}/editar', [UserVehicleController::class, 'edit'])->name('vehicles.edit');
        Route::post('/veiculos/{vehicle}/importar-crlv', [UserVehicleController::class, 'importCrlvForEdit'])->name('vehicles.import-crlv.edit');
        Route::put('/veiculos/{vehicle}', [UserVehicleController::class, 'update'])->name('vehicles.update');
        Route::post('/veiculos/{vehicle}/pdf', [UserVehicleController::class, 'exportPdf'])->name('vehicles.export-pdf');
        Route::get('/exportacoes-pdf/{export}/baixar', [VehiclePdfExportDownloadController::class, 'redirectToNamedFile'])
            ->name('vehicle-pdf-exports.redirect');
        Route::get('/exportacoes-pdf/{export}/{filename}', [VehiclePdfExportDownloadController::class, 'download'])
            ->where('filename', '[A-Za-z0-9._-]+\.pdf')
            ->name('vehicle-pdf-exports.download');
        Route::get('/manutencoes', [UserMaintenanceController::class, 'index'])->name('maintenances.index');
        Route::get('/manutencoes/nova', [UserMaintenanceController::class, 'create'])->name('maintenances.create');
        Route::post('/manutencoes', [UserMaintenanceController::class, 'store'])->name('maintenances.store');
        Route::get('/manutencoes/{maintenance}', [UserMaintenanceController::class, 'show'])
            ->whereNumber('maintenance')
            ->name('maintenances.show');
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
        Route::post('/estoque', [GarageVehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/estoque/{vehicle}', [GarageVehicleController::class, 'show'])->name('vehicles.show');
        Route::post('/estoque/{vehicle}/encerrar-consignacao', [GarageVehicleController::class, 'endConsignment'])
            ->name('vehicles.consignment.end');
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
        Route::get('/manutencoes', [WorkshopMaintenanceController::class, 'index'])->name('maintenances.index');
        Route::get('/manutencoes/nova', [WorkshopMaintenanceController::class, 'create'])->name('maintenances.create');
        Route::post('/manutencoes', [WorkshopMaintenanceController::class, 'store'])
            ->middleware('throttle:uploads')
            ->name('maintenances.store');
        Route::get('/manutencoes/{maintenance}', [WorkshopMaintenanceController::class, 'show'])
            ->whereNumber('maintenance')
            ->name('maintenances.show');
        Route::get('/manutencoes/{maintenance}/editar', [WorkshopMaintenanceController::class, 'edit'])
            ->whereNumber('maintenance')
            ->name('maintenances.edit');
        Route::put('/manutencoes/{maintenance}', [WorkshopMaintenanceController::class, 'update'])
            ->whereNumber('maintenance')
            ->middleware('throttle:uploads')
            ->name('maintenances.update');
        Route::delete('/manutencoes/{maintenance}', [WorkshopMaintenanceController::class, 'destroy'])
            ->whereNumber('maintenance')
            ->name('maintenances.destroy');
        Route::resource('garantias/templates', WorkshopWarrantyTemplateController::class)
            ->names('warranty-templates')
            ->parameters(['templates' => 'warranty_template']);
    });

    Route::prefix('admin')->middleware('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/usuarios/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::get('/veiculos', [AdminVehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/veiculos/{vehicle}', [AdminVehicleController::class, 'show'])->name('vehicles.show');
        Route::get('/manutencoes', [AdminMaintenanceController::class, 'index'])->name('maintenances.index');
        Route::get('/oficinas', [AdminWorkshopController::class, 'index'])->name('workshops.index');
        Route::get('/mapa/oficinas', [AdminMapController::class, 'workshops'])->name('maps.workshops');
        Route::get('/mapa/usuarios', [AdminMapController::class, 'users'])->name('maps.users');
        Route::get('/marcas', [AdminVehicleBrandController::class, 'index'])->name('brands.index');
        Route::get('/marcas/nova', [AdminVehicleBrandController::class, 'create'])->name('brands.create');
        Route::post('/marcas', [AdminVehicleBrandController::class, 'store'])->name('brands.store');
        Route::get('/marcas/{brand}', [AdminVehicleBrandController::class, 'show'])->name('brands.show');
        Route::get('/marcas/{brand}/editar', [AdminVehicleBrandController::class, 'edit'])->name('brands.edit');
        Route::put('/marcas/{brand}', [AdminVehicleBrandController::class, 'update'])->name('brands.update');
        Route::delete('/marcas/{brand}', [AdminVehicleBrandController::class, 'destroy'])->name('brands.destroy');
        Route::get('/blog', [AdminBlogPostController::class, 'index'])->name('blog.index');
        Route::get('/blog/novo', [AdminBlogPostController::class, 'create'])->name('blog.create');
        Route::post('/blog', [AdminBlogPostController::class, 'store'])->name('blog.store');
        Route::get('/blog/categorias', [AdminBlogCategoryController::class, 'index'])->name('blog.categories.index');
        Route::post('/blog/categorias', [AdminBlogCategoryController::class, 'store'])->name('blog.categories.store');
        Route::put('/blog/categorias/{category}', [AdminBlogCategoryController::class, 'update'])->name('blog.categories.update');
        Route::delete('/blog/categorias/{category}', [AdminBlogCategoryController::class, 'destroy'])->name('blog.categories.destroy');
        Route::get('/blog/{post}/editar', [AdminBlogPostController::class, 'edit'])->name('blog.edit');
        Route::put('/blog/{post}', [AdminBlogPostController::class, 'update'])->name('blog.update');
        Route::delete('/blog/{post}', [AdminBlogPostController::class, 'destroy'])->name('blog.destroy');
        Route::post('/marcas/{brand}/modelos', [AdminVehicleModelController::class, 'store'])->name('brands.models.store');
        Route::put('/modelos/{model}', [AdminVehicleModelController::class, 'update'])->name('models.update');
        Route::delete('/modelos/{model}', [AdminVehicleModelController::class, 'destroy'])->name('models.destroy');
    });
});
