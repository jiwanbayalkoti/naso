<?php

namespace App\Services;

use App\Helpers\ActivityType;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService extends BaseService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected ActivityLogService $activityLogService
    ) {}

    /**
     * Authenticate a user and start a session.
     *
     * @return array{user: User, token: null}
     */
    public function login(array $credentials, bool $remember = false): array
    {
        $user = $this->validateCredentials($credentials);

        Auth::login($user, $remember);

        $this->activityLogService->log(
            $user->id,
            ActivityType::USER_LOGIN,
            'User logged in.',
            $user
        );

        $this->restoreRiderPresence($user);

        return [
            'user' => $user->load(['roles', 'rider']),
            'token' => null,
        ];
    }

    /**
     * Authenticate a user via API token.
     *
     * @return array{user: User, token: string}
     */
    public function apiLogin(array $credentials, string $deviceName = 'api'): array
    {
        $user = $this->validateCredentials($credentials);

        $token = $user->createToken($deviceName)->plainTextToken;

        $this->activityLogService->log(
            $user->id,
            ActivityType::USER_LOGIN,
            'User logged in via API.',
            $user
        );

        $this->restoreRiderPresence($user);

        return [
            'user' => $user->load(['roles', 'rider']),
            'token' => $token,
        ];
    }

    /**
     * Log out the authenticated user.
     *
     * Clears live presence only — does not change the rider's Online preference.
     * Manual Offline stays Off after next login; preferred Online restores on login.
     */
    public function logout(): void
    {
        $user = Auth::user();

        if ($user) {
            if ($user->rider || $user->hasRole('rider')) {
                app(RiderService::class)->clearPresenceByUserId($user->id);
            }

            $this->activityLogService->log(
                $user->id,
                ActivityType::USER_LOGOUT,
                'User logged out.',
                $user
            );

            if (request()->bearerToken()) {
                $token = $user->currentAccessToken()
                    ?? PersonalAccessToken::findToken(request()->bearerToken());

                $token?->delete();
                Auth::guard('web')->logout();
            } else {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
        }
    }

    protected function restoreRiderPresence(User $user): void
    {
        $user->loadMissing(['roles', 'rider']);

        if ($user->rider || $user->hasRole('rider')) {
            app(RiderService::class)->restorePresenceOnLogin($user->id);
            $user->unsetRelation('rider');
            $user->load('rider');
        }
    }

    /**
     * Validate user credentials.
     */
    protected function validateCredentials(array $credentials): User
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            $message = $this->resolveInactiveMessage($user);

            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        return $user;
    }

    public function resolveInactiveMessageForUser(User $user): string
    {
        return $this->resolveInactiveMessage($user);
    }

    protected function resolveInactiveMessage(User $user): string
    {
        $user->loadMissing(['shop', 'rider']);

        if ($user->shop?->approval_status === \App\Helpers\ApprovalStatus::PENDING
            || $user->rider?->approval_status === \App\Helpers\ApprovalStatus::PENDING) {
            return 'Your registration is pending admin approval. You will be able to login after approval.';
        }

        if ($user->shop?->approval_status === \App\Helpers\ApprovalStatus::REJECTED) {
            return 'Your shop registration was rejected. '.($user->shop->rejection_reason ?: 'Please contact support.');
        }

        if ($user->rider?->approval_status === \App\Helpers\ApprovalStatus::REJECTED) {
            return 'Your rider registration was rejected. '.($user->rider->rejection_reason ?: 'Please contact support.');
        }

        return 'Your account has been deactivated.';
    }

    /**
     * Create a Sanctum API token for mobile clients.
     */
    public function createApiToken(User $user, string $deviceName = 'api'): string
    {
        return $user->createToken($deviceName)->plainTextToken;
    }
}
