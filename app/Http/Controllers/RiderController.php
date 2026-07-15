<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Requests\Rider\StoreRiderRequest;
use App\Http\Requests\Rider\UpdateRiderLocationRequest;
use App\Http\Requests\Rider\UpdateRiderRequest;
use App\Http\Resources\RiderResource;
use App\Models\Rider;
use App\Services\RegistrationService;
use App\Services\RiderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class RiderController extends Controller
{
    use HandlesDataTables;
    use HandlesExports;

    public function __construct(
        protected RiderService $riderService,
        protected RegistrationService $registrationService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Rider::class);

        if ($request->is('api/*')) {
            $filters = $this->mobileListFilters($request);
            $user = auth()->user();

            if ($user->hasRole('rider')) {
                $filters['user_id'] = $user->id;
            }

            $paginator = $this->riderService->list($filters);

            return $this->mobilePaginatedResponse(
                $paginator,
                RiderResource::collection($paginator->items())
            );
        }

        return view('riders.index');
    }

    public function assignable(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Delivery::class);

        $riders = $this->riderService->getAssignable();

        return $this->success($riders->map(fn (Rider $rider) => [
            'id' => $rider->id,
            'rider_id' => $rider->id,
            'name' => $rider->user?->name ?? 'Rider #'.$rider->id,
            'is_online' => $rider->isPresentlyOnline(),
            'is_available' => (bool) $rider->is_available && $rider->isPresentlyOnline(),
        ])->values());
    }

    public function liveMap(): View
    {
        $this->authorize('trackLive', Rider::class);

        return view('riders.live-map', [
            'pollUrl' => route('riders.live-locations'),
            'isShop' => auth()->user()->hasRole('shop'),
        ]);
    }

    public function liveLocations(): JsonResponse
    {
        $this->authorize('trackLive', Rider::class);

        return $this->success([
            'riders' => $this->riderService->getLiveMapLocations(auth()->user()),
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Rider::class);

        $filters = $this->dataTableFilters($request);
        $user = auth()->user();

        if ($user->hasRole('rider')) {
            $filters['user_id'] = $user->id;
        }

        $paginator = $this->riderService->list($filters);

        $data = RiderResource::collection($paginator->items())->resolve();
        $data = $this->appendActions($data, 'riders.partials.actions', fn (array $row) => [
            'id' => $row['uuid'],
            'name' => $row['name'] ?? 'rider',
            'isOnline' => (bool) ($row['is_online'] ?? false),
        ]);

        return $this->dataTableResponse($request, $paginator, $data);
    }

    public function create(): JsonResponse
    {
        $this->authorize('create', Rider::class);

        return $this->success(['html' => '']);
    }

    public function store(StoreRiderRequest $request): JsonResponse
    {
        $this->authorize('create', Rider::class);

        if ($request->filled('email') && $request->filled('password')) {
            $result = $this->registrationService->registerRider($request->validated(), false, false);
            $rider = $result['rider'];
        } else {
            $rider = $this->riderService->create($request->validated(), auth()->id());
        }

        return $this->success(new RiderResource($rider), 'Rider created successfully.', 201);
    }

    public function edit(string $rider): JsonResponse
    {
        return $this->show($rider);
    }

    public function show(string $rider): JsonResponse
    {
        $record = $this->riderService->findByUuid($rider);

        if (! $record) {
            return $this->error('Rider not found.', 404);
        }

        $this->authorize('view', $record);

        return $this->success(new RiderResource($record->load('user')));
    }

    public function update(UpdateRiderRequest $request, string $rider): JsonResponse
    {
        $record = $this->riderService->findByUuid($rider);

        if (! $record) {
            return $this->error('Rider not found.', 404);
        }

        $this->authorize('update', $record);

        $updated = $this->riderService->update($rider, $request->validated(), auth()->id());

        return $this->success(new RiderResource($updated), 'Rider updated successfully.');
    }

    public function destroy(string $rider): JsonResponse
    {
        $record = $this->riderService->findByUuid($rider);

        if (! $record) {
            return $this->error('Rider not found.', 404);
        }

        $this->authorize('delete', $record);

        $this->riderService->delete($rider, auth()->id());

        return $this->success(null, 'Rider deleted successfully.');
    }

    public function toggleOnline(string $rider): JsonResponse
    {
        $record = $this->riderService->findByUuid($rider);

        if (! $record) {
            return $this->error('Rider not found.', 404);
        }

        $this->authorize('toggleOnline', $record);

        $updated = $this->riderService->toggleOnline($rider, auth()->id());

        return $this->success([
            ...(new RiderResource($updated))->resolve(),
            // Toggle intent — keep switch in sync even before next heartbeat.
            'is_online' => (bool) $updated->is_online,
            'wants_online' => (bool) $updated->is_online,
            'is_present' => $updated->isPresentlyOnline(),
        ], 'Rider online status updated.');
    }

    public function heartbeat(string $rider): JsonResponse
    {
        $record = $this->riderService->findByUuid($rider);

        if (! $record) {
            return $this->error('Rider not found.', 404);
        }

        $this->authorize('update', $record);

        $updated = $this->riderService->heartbeat($rider, auth()->id());

        return $this->success([
            'is_online' => (bool) $updated->is_online,
            'wants_online' => (bool) $updated->is_online,
            'is_present' => $updated->isPresentlyOnline(),
            'last_seen_at' => $updated->last_seen_at?->toIso8601String(),
        ], 'Presence updated.');
    }

    public function updateLocation(UpdateRiderLocationRequest $request, string $rider): JsonResponse
    {
        $record = $this->riderService->findByUuid($rider);

        if (! $record) {
            return $this->error('Rider not found.', 404);
        }

        $this->authorize('update', $record);

        $updated = $this->riderService->updateLocation(
            $rider,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
            auth()->id()
        );

        return $this->success(new RiderResource($updated), 'Rider location updated.');
    }

    public function export(string $format, Request $request): Response
    {
        $this->authorize('export', Rider::class);

        $filters = $this->dataTableFilters($request);
        $user = auth()->user();

        if ($user->hasRole('rider')) {
            $filters['user_id'] = $user->id;
        }

        $records = $this->riderService->export($filters);

        return $this->exportData($records, $format, 'riders', [
            'user.name' => 'Name',
            'vehicle_type' => 'Vehicle Type',
            'vehicle_number' => 'Vehicle Number',
            'is_online' => 'Online',
            'is_available' => 'Available',
            'rating' => 'Rating',
            'total_deliveries' => 'Total Deliveries',
        ]);
    }
}
