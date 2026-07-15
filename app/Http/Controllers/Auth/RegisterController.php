<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRiderRequest;
use App\Http\Requests\Auth\RegisterShopRequest;
use App\Http\Resources\RiderResource;
use App\Http\Resources\ShopResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\VerificationDocumentResource;
use App\Services\AppSettingService;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        protected RegistrationService $registrationService,
        protected AppSettingService $appSettingService
    ) {}

    public function showShopForm(): View
    {
        abort_unless($this->appSettingService->get('shop_registration_enabled', true), 403, 'Shop registration is currently disabled.');

        return view('auth.register-shop');
    }

    public function registerShop(RegisterShopRequest $request): JsonResponse|RedirectResponse
    {
        abort_unless($this->appSettingService->get('shop_registration_enabled', true), 403, 'Shop registration is currently disabled.');
        $result = $this->registrationService->registerShop($request->validated(), false, true);

        $message = 'Registration submitted successfully. Please wait for admin approval.';
        $redirect = route('registration.pending');

        if ($request->expectsJson()) {
            return $this->success([
                'user' => new UserResource($result['user']),
                'shop' => new ShopResource($result['shop']),
                'requires_approval' => true,
                'redirect' => $redirect,
            ], $message);
        }

        return redirect()->route('registration.pending')->with('success', $message);
    }

    public function showRiderForm(): View
    {
        abort_unless($this->appSettingService->get('rider_registration_enabled', true), 403, 'Rider registration is currently disabled.');

        return view('auth.register-rider');
    }

    public function registerRider(RegisterRiderRequest $request): JsonResponse|RedirectResponse
    {
        abort_unless($this->appSettingService->get('rider_registration_enabled', true), 403, 'Rider registration is currently disabled.');
        $result = $this->registrationService->registerRider($request->validated(), false, true);

        $message = 'Registration submitted successfully. Please wait for admin approval.';
        $redirect = route('registration.pending');

        if ($request->expectsJson()) {
            return $this->success([
                'user' => new UserResource($result['user']),
                'rider' => new RiderResource($result['rider']),
                'requires_approval' => true,
                'redirect' => $redirect,
            ], $message);
        }

        return redirect()->route('registration.pending')->with('success', $message);
    }

    public function showPendingPage(): View
    {
        return view('auth.registration-pending');
    }
}
