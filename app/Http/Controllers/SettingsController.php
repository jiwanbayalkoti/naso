<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Http\Requests\Settings\UploadLogoRequest;
use App\Services\AppSettingService;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected AppSettingService $appSettingService,
        protected ImageUploadService $imageUploadService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        if ($request->is('api/*')) {
            return $this->show($request);
        }

        return view('settings.index', [
            'settings' => $this->appSettingService->publicPayload(),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorizeSuperAdmin($request);

        return $this->success($this->appSettingService->publicPayload());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $this->appSettingService->updateMany(
            $request->validated(),
            $request->user()->id
        );

        return $this->success($this->appSettingService->publicPayload(), 'Settings updated successfully.');
    }

    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        $currentLogo = $this->appSettingService->get('app_logo');
        $path = $this->imageUploadService->storeImage(
            $request->file('logo'),
            'settings',
            is_string($currentLogo) ? $currentLogo : null,
            field: 'logo'
        );

        $this->appSettingService->updateMany(['app_logo' => $path], $request->user()->id);

        return $this->success($this->appSettingService->publicPayload(), 'App logo updated successfully.');
    }

    protected function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
    }
}
