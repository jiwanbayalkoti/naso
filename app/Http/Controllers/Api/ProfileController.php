<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ChangePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\RiderResource;
use App\Http\Resources\ShopResource;
use App\Http\Resources\UserResource;
use App\Services\ImageUploadService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected ImageUploadService $imageUploadService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'shop', 'rider.user']);

        return $this->success([
            'user' => new UserResource($user),
            'shop' => $user->shop ? new ShopResource($user->shop) : null,
            'rider' => $user->rider ? new RiderResource($user->rider) : null,
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $updated = $this->userService->update($user->uuid, $request->validated(), $user->id);

        return $this->success([
            'user' => new UserResource($updated->load('roles')),
            'shop' => $user->shop ? new ShopResource($user->shop) : null,
            'rider' => $user->rider ? new RiderResource($user->rider) : null,
        ], 'Profile updated successfully.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->userService->update($user->uuid, [
            'password' => $request->input('password'),
        ], $user->id);

        return $this->success(null, 'Password changed successfully.');
    }

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        $user = $request->user();
        $path = $this->imageUploadService->storeImage(
            $request->file('avatar'),
            'avatars/'.$user->uuid,
            $user->avatar,
            field: 'avatar'
        );

        $updated = $this->userService->update($user->uuid, ['avatar' => $path], $user->id);

        return $this->success([
            'user' => new UserResource($updated->load('roles')),
            'shop' => $user->shop ? new ShopResource($user->shop) : null,
            'rider' => $user->rider ? new RiderResource($user->rider) : null,
        ], 'Profile photo updated successfully.');
    }
}
