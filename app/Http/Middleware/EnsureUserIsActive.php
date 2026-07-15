<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            $message = app(\App\Services\AuthService::class)->resolveInactiveMessageForUser($user);

            if ($request->bearerToken() && method_exists($user, 'currentAccessToken')) {
                $user->currentAccessToken()?->delete();

                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()->route('login')
                ->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
