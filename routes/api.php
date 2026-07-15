<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\GeocodeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RegistrationApprovalController;
use App\Http\Controllers\RiderController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserController;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'success' => true,
            'message' => 'API and database are reachable.',
            'data' => [
                'database' => config('database.connections.mysql.database'),
            ],
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Database connection failed. Start MySQL in XAMPP and verify DB settings in .env.',
        ], 503);
    }
});

Route::post('login', [LoginController::class, 'login']);

Route::post('register/shop', [RegisterController::class, 'registerShop']);
Route::post('register/rider', [RegisterController::class, 'registerRider']);

Route::get('track/{trackingNumber}', [DeliveryController::class, 'track']);

    Route::get('config/maps', [ConfigController::class, 'maps']);
    Route::get('geocode/reverse', [GeocodeController::class, 'reverse']);
    Route::get('geocode/search', [GeocodeController::class, 'search']);
    Route::get('geocode/place', [GeocodeController::class, 'place']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('user', [ProfileController::class, 'show']);
    Route::put('user', [ProfileController::class, 'update']);
    Route::put('user/password', [ProfileController::class, 'changePassword']);
    Route::post('user/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::post('logout', [LoginController::class, 'logout']);

    Route::prefix('dashboard')->name('api.dashboard.')->group(function () {
        Route::get('stats', [DashboardController::class, 'stats'])->name('stats');
        Route::get('trends', [DashboardController::class, 'trends'])->name('trends');
        Route::get('status-chart', [DashboardController::class, 'statusChart'])->name('status-chart');
        Route::get('latest-deliveries', [DashboardController::class, 'latestDeliveries'])->name('latest-deliveries');
        Route::get('pending-deliveries', [DashboardController::class, 'pendingDeliveries'])->name('pending-deliveries');
        Route::get('online-riders', [DashboardController::class, 'onlineRiders'])->name('online-riders');
    });

    Route::prefix('notifications')->name('api.notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
    });

    Route::get('shops/datatable', [ShopController::class, 'datatable']);
    Route::get('shops/export/{format}', [ShopController::class, 'export']);
    Route::apiResource('shops', ShopController::class);

    Route::get('riders/datatable', [RiderController::class, 'datatable']);
    Route::get('riders/assignable', [RiderController::class, 'assignable']);
        Route::post('riders/{rider}/toggle-online', [RiderController::class, 'toggleOnline']);
        Route::post('riders/{rider}/heartbeat', [RiderController::class, 'heartbeat']);
        Route::post('riders/{rider}/location', [RiderController::class, 'updateLocation']);
    Route::get('riders/export/{format}', [RiderController::class, 'export']);
    Route::apiResource('riders', RiderController::class);

    Route::get('deliveries/datatable', [DeliveryController::class, 'datatable']);
    Route::get('deliveries/available-offers', [DeliveryController::class, 'availableOffers']);
    Route::post('deliveries/{delivery}/assign', [DeliveryController::class, 'assign']);
    Route::post('deliveries/{delivery}/claim', [DeliveryController::class, 'claim']);
    Route::post('deliveries/{delivery}/reject-assignment', [DeliveryController::class, 'rejectAssignment']);
    Route::post('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus']);
    Route::get('deliveries/{delivery}/tracking', [DeliveryController::class, 'tracking']);
    Route::get('deliveries/export/{format}', [DeliveryController::class, 'export']);
    Route::apiResource('deliveries', DeliveryController::class);

    Route::get('users/datatable', [UserController::class, 'datatable']);
    Route::get('users/export/{format}', [UserController::class, 'export']);
    Route::apiResource('users', UserController::class);

    Route::prefix('registration-requests')->group(function () {
        Route::get('/', [RegistrationApprovalController::class, 'index']);
        Route::get('datatable', [RegistrationApprovalController::class, 'datatable']);
        Route::get('{type}/{uuid}', [RegistrationApprovalController::class, 'show']);
        Route::post('{type}/{uuid}/approve', [RegistrationApprovalController::class, 'approve']);
        Route::post('{type}/{uuid}/reject', [RegistrationApprovalController::class, 'reject']);
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index']);
    Route::get('activity-logs/datatable', [ActivityLogController::class, 'datatable']);
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/datatable', [AuditLogController::class, 'datatable']);

    Route::get('menus/sidebar', [MenuController::class, 'sidebar']);
    Route::get('menus/datatable', [MenuController::class, 'datatable']);
    Route::get('menus/export/{format}', [MenuController::class, 'export']);
    Route::apiResource('menus', MenuController::class);

    Route::get('settings', [SettingsController::class, 'show']);
    Route::put('settings', [SettingsController::class, 'update']);
    Route::post('settings/logo', [SettingsController::class, 'uploadLogo']);
});
