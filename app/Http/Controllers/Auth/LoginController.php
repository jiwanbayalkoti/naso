<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*')) {
            $deviceName = $request->input('device_name', 'mobile-app');
            $result = $this->authService->apiLogin($request->only('email', 'password'), $deviceName);

            return $this->success([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ], 'Logged in successfully.');
        }

        $result = $this->authService->login(
            $request->only('email', 'password'),
            $request->boolean('remember')
        );

        if ($request->expectsJson()) {
            return $this->success([
                'user' => new UserResource($result['user']),
                'redirect' => route('dashboard'),
            ], 'Logged in successfully.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(): JsonResponse|RedirectResponse
    {
        $this->authService->logout();

        if (request()->is('api/*') || request()->expectsJson()) {
            return $this->success(null, 'Logged out successfully.');
        }

        return redirect()->route('login');
    }
}
