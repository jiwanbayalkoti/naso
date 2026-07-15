<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Requests\Registration\RejectRegistrationRequest;
use App\Http\Resources\RegistrationRequestResource;
use App\Http\Resources\RiderResource;
use App\Http\Resources\ShopResource;
use App\Services\RegistrationApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationApprovalController extends Controller
{
    use HandlesDataTables;

    public function __construct(
        protected RegistrationApprovalService $approvalService
    ) {}

    protected function authorizeRegistrationAbility(string $ability): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->hasRole('super_admin') || $user->can($ability)) {
            return;
        }

        abort(403, 'This action is unauthorized.');
    }

    public function index(Request $request): View|JsonResponse
    {
        $this->authorizeRegistrationAbility('registration-requests.view');

        if ($request->is('api/*')) {
            $paginator = $this->approvalService->listPending($this->mobileListFilters($request));

            return $this->mobilePaginatedResponse(
                $paginator,
                RegistrationRequestResource::collection($paginator->items())
            );
        }

        return view('registration-requests.index', [
            'pendingCount' => $this->approvalService->pendingCount(),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorizeRegistrationAbility('registration-requests.view');

        $paginator = $this->approvalService->listPending($this->dataTableFilters($request));
        $data = RegistrationRequestResource::collection($paginator->items())->resolve();
        $data = $this->appendActions($data, 'registration-requests.partials.actions', fn (array $row) => [
            'id' => $row['uuid'],
            'type' => $row['type'],
            'name' => $row['name'] ?? 'request',
        ]);

        return $this->dataTableResponse($request, $paginator, $data);
    }

    public function show(string $type, string $uuid): JsonResponse
    {
        $this->authorizeRegistrationAbility('registration-requests.view');

        $type = strtolower($type);

        if ($type === 'shop') {
            $record = $this->approvalService->findShopRequest($uuid);

            if (! $record) {
                return $this->error('Shop registration request not found.', 404);
            }

            return $this->success(new ShopResource($record));
        }

        if ($type === 'rider') {
            $record = $this->approvalService->findRiderRequest($uuid);

            if (! $record) {
                return $this->error('Rider registration request not found.', 404);
            }

            return $this->success(new RiderResource($record));
        }

        return $this->error('Invalid registration type.', 422);
    }

    public function approve(string $type, string $uuid): JsonResponse
    {
        $this->authorizeRegistrationAbility('registration-requests.approve');

        $type = strtolower($type);

        $actorId = auth()->id();

        if ($type === 'shop') {
            $shop = $this->approvalService->approveShop($uuid, $actorId);

            return $this->success(new ShopResource($shop), 'Shop registration approved successfully.');
        }

        if ($type === 'rider') {
            $rider = $this->approvalService->approveRider($uuid, $actorId);

            return $this->success(new RiderResource($rider), 'Rider registration approved successfully.');
        }

        return $this->error('Invalid registration type.', 422);
    }

    public function reject(RejectRegistrationRequest $request, string $type, string $uuid): JsonResponse
    {
        $this->authorizeRegistrationAbility('registration-requests.reject');

        $type = strtolower($type);

        $actorId = auth()->id();
        $reason = $request->validated('reason');

        if ($type === 'shop') {
            $shop = $this->approvalService->rejectShop($uuid, $reason, $actorId);

            return $this->success(new ShopResource($shop), 'Shop registration rejected.');
        }

        if ($type === 'rider') {
            $rider = $this->approvalService->rejectRider($uuid, $reason, $actorId);

            return $this->success(new RiderResource($rider), 'Rider registration rejected.');
        }

        return $this->error('Invalid registration type.', 422);
    }
}
