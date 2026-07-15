<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\RegistrationApprovalController;
use App\Http\Controllers\RiderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Works without public/storage symlink (shared / live hosting).
Route::get('media/{path}', [PublicMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);

    Route::get('register/shop', [RegisterController::class, 'showShopForm'])->name('register.shop');
    Route::post('register/shop', [RegisterController::class, 'registerShop']);

    Route::get('register/rider', [RegisterController::class, 'showRiderForm'])->name('register.rider');
    Route::post('register/rider', [RegisterController::class, 'registerRider']);

    Route::get('registration/pending', [RegisterController::class, 'showPendingPage'])->name('registration.pending');
});

Route::post('logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('track/{trackingNumber}', [DeliveryController::class, 'track'])
    ->name('deliveries.track');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('shops')->name('shops.')->group(function () {
        Route::get('/', [ShopController::class, 'index'])->name('index');
        Route::get('datatable', [ShopController::class, 'datatable'])->name('datatable');
        Route::get('create', [ShopController::class, 'create'])->name('create');
        Route::get('export/{format}', [ShopController::class, 'export'])->name('export');
        Route::post('/', [ShopController::class, 'store'])->name('store');
        Route::get('{shop}/edit', [ShopController::class, 'edit'])->name('edit');
        Route::put('{shop}', [ShopController::class, 'update'])->name('update');
        Route::delete('{shop}', [ShopController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('riders')->name('riders.')->group(function () {
        Route::get('/', [RiderController::class, 'index'])->name('index');
        Route::get('live-map', [RiderController::class, 'liveMap'])->name('live-map');
        Route::get('live-locations', [RiderController::class, 'liveLocations'])->name('live-locations');
        Route::get('datatable', [RiderController::class, 'datatable'])->name('datatable');
        Route::get('create', [RiderController::class, 'create'])->name('create');
        Route::get('export/{format}', [RiderController::class, 'export'])->name('export');
        Route::get('assignable', [RiderController::class, 'assignable'])->name('assignable');
        Route::post('/', [RiderController::class, 'store'])->name('store');
        Route::get('{rider}/edit', [RiderController::class, 'edit'])->name('edit');
        Route::put('{rider}', [RiderController::class, 'update'])->name('update');
        Route::delete('{rider}', [RiderController::class, 'destroy'])->name('destroy');
        Route::post('{rider}/toggle-online', [RiderController::class, 'toggleOnline'])->name('toggle-online');
        Route::post('{rider}/heartbeat', [RiderController::class, 'heartbeat'])->name('heartbeat');
        Route::post('{rider}/location', [RiderController::class, 'updateLocation'])->name('location');
    });

    Route::prefix('deliveries')->name('deliveries.')->group(function () {
        Route::get('/', [DeliveryController::class, 'index'])->name('index');
        Route::get('datatable', [DeliveryController::class, 'datatable'])->name('datatable');
        Route::get('create', [DeliveryController::class, 'create'])->name('create');
        Route::get('export/{format}', [DeliveryController::class, 'export'])->name('export');
        Route::get('available-offers', [DeliveryController::class, 'availableOffers'])->name('available-offers');
        Route::post('/', [DeliveryController::class, 'store'])->name('store');
        Route::get('{delivery}', [DeliveryController::class, 'show'])->name('show');
        Route::get('{delivery}/edit', [DeliveryController::class, 'edit'])->name('edit');
        Route::put('{delivery}', [DeliveryController::class, 'update'])->name('update');
        Route::delete('{delivery}', [DeliveryController::class, 'destroy'])->name('destroy');
        Route::post('{delivery}/assign', [DeliveryController::class, 'assign'])->name('assign');
        Route::post('{delivery}/reject-assignment', [DeliveryController::class, 'rejectAssignment'])->name('reject-assignment');
        Route::post('{delivery}/claim', [DeliveryController::class, 'claim'])->name('claim');
        Route::post('{delivery}/status', [DeliveryController::class, 'updateStatus'])->name('status');
        Route::get('{delivery}/tracking', [DeliveryController::class, 'tracking'])->name('tracking');
    });

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('datatable', [UserController::class, 'datatable'])->name('datatable');
        Route::get('create', [UserController::class, 'create'])->name('create');
        Route::get('export/{format}', [UserController::class, 'export'])->name('export');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('activity-logs')->name('activity-logs.')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        Route::get('datatable', [ActivityLogController::class, 'datatable'])->name('datatable');
    });

    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('datatable', [AuditLogController::class, 'datatable'])->name('datatable');
    });

    Route::prefix('registration-requests')->name('registration-requests.')->group(function () {
        Route::get('/', [RegistrationApprovalController::class, 'index'])->name('index');
        Route::get('datatable', [RegistrationApprovalController::class, 'datatable'])->name('datatable');
        Route::get('{type}/{uuid}', [RegistrationApprovalController::class, 'show'])->name('show');
        Route::post('{type}/{uuid}/approve', [RegistrationApprovalController::class, 'approve'])->name('approve');
        Route::post('{type}/{uuid}/reject', [RegistrationApprovalController::class, 'reject'])->name('reject');
    });

    Route::prefix('menus')->name('menus.')->group(function () {
        Route::get('/', [MenuController::class, 'index'])->name('index');
        Route::get('datatable', [MenuController::class, 'datatable'])->name('datatable');
        Route::get('create', [MenuController::class, 'create'])->name('create');
        Route::get('export/{format}', [MenuController::class, 'export'])->name('export');
        Route::post('/', [MenuController::class, 'store'])->name('store');
        Route::get('{menu}/edit', [MenuController::class, 'edit'])->name('edit');
        Route::put('{menu}', [MenuController::class, 'update'])->name('update');
        Route::delete('{menu}', [MenuController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('password', [ProfileController::class, 'changePassword'])->name('password');
        Route::post('avatar', [ProfileController::class, 'uploadAvatar'])->name('avatar');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/', [SettingsController::class, 'update'])->name('update');
        Route::post('logo', [SettingsController::class, 'uploadLogo'])->name('logo');
    });
});
