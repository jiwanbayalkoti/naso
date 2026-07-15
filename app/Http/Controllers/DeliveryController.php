<?php

namespace App\Http\Controllers;

use App\Helpers\ApprovalStatus;
use App\Helpers\DeliveryStatus;
use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Requests\Delivery\DeliveryAssignRequest;
use App\Http\Requests\Delivery\DeliveryStatusRequest;
use App\Http\Requests\Delivery\StoreDeliveryRequest;
use App\Http\Requests\Delivery\UpdateDeliveryRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Services\DeliveryService;
use App\Services\RiderService;
use App\Services\ShopService;
use App\Services\TrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DeliveryController extends Controller
{
    use HandlesDataTables;
    use HandlesExports;

    public function __construct(
        protected DeliveryService $deliveryService,
        protected RiderService $riderService,
        protected ShopService $shopService,
        protected TrackingService $trackingService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Delivery::class);

        if ($request->is('api/*')) {
            $filters = $this->mobileListFilters($request);
            $user = auth()->user();

            if ($user->hasRole('shop') && $user->shop) {
                $filters['shop_id'] = $user->shop->id;
            }

            if ($user->hasRole('rider') && $user->rider) {
                $filters['rider_id'] = $user->rider->id;
            }

            $paginator = $this->deliveryService->list($filters);

            return $this->mobilePaginatedResponse(
                $paginator,
                DeliveryResource::collection($paginator->items())
            );
        }

        $user = auth()->user();
        $riders = $this->riderService->getAssignable();
        $shops = $user->hasRole('super_admin') ? $this->shopService->getActive() : collect();
        $currentShop = $user->hasRole('shop') ? $user->shop : null;
        $isRiderUser = $user->hasRole('rider');
        $assignedCount = 0;

        if ($isRiderUser && $user->rider) {
            $assignedCount = \App\Models\Delivery::query()
                ->where('rider_id', $user->rider->id)
                ->where('status', DeliveryStatus::ASSIGNED)
                ->count();
        }

        return view('deliveries.index', compact(
            'riders',
            'shops',
            'currentShop',
            'isRiderUser',
            'assignedCount'
        ));
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Delivery::class);

        $filters = $this->dataTableFilters($request);
        $user = auth()->user();

        if ($user->hasRole('shop') && $user->shop) {
            $filters['shop_id'] = $user->shop->id;
        }

        if ($user->hasRole('rider') && $user->rider) {
            $filters['rider_id'] = $user->rider->id;
        }

        $paginator = $this->deliveryService->list($filters);

        $data = DeliveryResource::collection($paginator->items())->resolve();
        $actionView = $user->hasRole('rider')
            ? 'deliveries.partials.rider-actions'
            : 'deliveries.partials.actions';

        $data = $this->appendActions($data, $actionView, function (array $row) use ($user) {
            $payload = [
                'id' => $row['uuid'],
                'trackingNumber' => $row['tracking_number'] ?? '',
                'status' => $row['status'] ?? 'pending',
            ];

            if ($user->hasRole('rider')) {
                $payload['allowedStatuses'] = DeliveryStatus::riderActionStatuses($row['status'] ?? 'pending');
            } else {
                $payload['canAssign'] = $user->can('deliveries.assign')
                    && in_array($row['status'] ?? 'pending', [DeliveryStatus::PENDING, DeliveryStatus::ASSIGNED], true);
                $payload['canEdit'] = ! $user->hasRole('rider');
                $payload['canDelete'] = $user->hasRole('super_admin')
                    || ($user->hasRole('shop') && $user->can('deliveries.delete'));
            }

            return $payload;
        });

        return $this->dataTableResponse($request, $paginator, $data);
    }

    public function create(): JsonResponse
    {
        $this->authorize('create', Delivery::class);

        return $this->success(['html' => '']);
    }

    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        $this->authorize('create', Delivery::class);

        $data = $request->validated();

        if (auth()->user()->hasRole('shop') && auth()->user()->shop) {
            $data['shop_id'] = auth()->user()->shop->id;
        }

        $delivery = $this->deliveryService->create($data, auth()->id());

        return $this->success(new DeliveryResource($delivery), 'Delivery created successfully.', 201);
    }

    public function show(string $delivery): View|JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            if (request()->expectsJson()) {
                return $this->error('Delivery not found.', 404);
            }

            abort(404);
        }

        $this->authorize('view', $record);

        $record->load(['shop', 'rider.user', 'statusHistories.changedBy']);

        if (request()->expectsJson()) {
            return $this->success(new DeliveryResource($record));
        }

        $user = auth()->user();
        $riders = $user->hasRole('rider') ? collect() : $this->riderService->getAssignable();
        $isRiderUser = $user->hasRole('rider');

        return view('deliveries.show', [
            'delivery' => $record,
            'riders' => $riders,
            'isRiderUser' => $isRiderUser,
            'tracking' => $this->trackingService->buildDeliveryTracking($record),
        ]);
    }

    public function edit(string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('view', $record);

        return $this->success(new DeliveryResource($record->load(['shop', 'rider.user'])));
    }

    public function update(UpdateDeliveryRequest $request, string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('update', $record);

        $updated = $this->deliveryService->update($delivery, $request->validated(), auth()->id());

        return $this->success(new DeliveryResource($updated), 'Delivery updated successfully.');
    }

    public function destroy(string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('delete', $record);

        $this->deliveryService->delete($delivery, auth()->id());

        return $this->success(null, 'Delivery deleted successfully.');
    }

    public function assign(DeliveryAssignRequest $request, string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('assign', $record);

        $updated = $this->deliveryService->assignRider(
            $delivery,
            (int) $request->validated('rider_id'),
            auth()->id(),
            $request->validated('notes')
        );

        return $this->success(new DeliveryResource($updated), 'Rider assigned successfully.');
    }

    public function rejectAssignment(Request $request, string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('rejectAssignment', $record);

        $updated = $this->deliveryService->rejectAssignment(
            $delivery,
            auth()->id(),
            $request->input('notes')
        );

        return $this->success(new DeliveryResource($updated), 'Delivery assignment rejected.');
    }

    public function availableOffers(): JsonResponse
    {
        $this->authorize('viewAny', Delivery::class);

        $user = auth()->user();
        $rider = $user->rider;

        if (! $user->hasRole('rider') || ! $rider || ! $rider->isPresentlyOnline() || ! $rider->is_available) {
            return $this->success([]);
        }

        if ($rider->approval_status && $rider->approval_status !== ApprovalStatus::APPROVED) {
            return $this->success([]);
        }

        $offers = $this->deliveryService->getAvailableOffers()->map(fn (Delivery $delivery) => [
            'uuid' => $delivery->uuid,
            'tracking_number' => $delivery->tracking_number,
            'shop_name' => $delivery->shop?->name,
            'pickup_address' => $delivery->pickup_address,
            'delivery_address' => $delivery->delivery_address,
            'delivery_fee' => (float) $delivery->delivery_fee,
            'priority' => $delivery->priority,
            'created_at' => $delivery->created_at?->toIso8601String(),
            'offer_expires_at' => $delivery->offer_expires_at?->toIso8601String(),
        ])->values();

        return $this->success($offers);
    }

    public function claim(string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('claim', $record);

        $rider = auth()->user()->rider;

        if (! $rider) {
            return $this->error('Rider profile not found.', 404);
        }

        $updated = $this->deliveryService->claimDelivery($delivery, $rider->id, auth()->id());

        return $this->success(new DeliveryResource($updated), 'Delivery accepted successfully.');
    }

    public function updateStatus(DeliveryStatusRequest $request, string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('updateStatus', $record);

        $updated = $this->deliveryService->updateStatus(
            $delivery,
            $request->validated('status'),
            auth()->id(),
            $request->validated('notes')
        );

        return $this->success(new DeliveryResource($updated), 'Delivery status updated successfully.');
    }

    public function track(string $trackingNumber): View|JsonResponse
    {
        $delivery = $this->deliveryService->findByTrackingNumber($trackingNumber);

        if (! $delivery) {
            if (request()->expectsJson()) {
                return $this->error('Delivery not found.', 404);
            }

            abort(404);
        }

        if (request()->expectsJson()) {
            return $this->success($this->trackingService->buildDeliveryTracking($delivery));
        }

        return view('deliveries.track', [
            'delivery' => $delivery,
            'tracking' => $this->trackingService->buildDeliveryTracking($delivery),
        ]);
    }

    public function tracking(string $delivery): JsonResponse
    {
        $record = $this->deliveryService->findByUuid($delivery);

        if (! $record) {
            return $this->error('Delivery not found.', 404);
        }

        $this->authorize('view', $record);

        return $this->success($this->trackingService->buildDeliveryTracking($record));
    }

    public function export(string $format, Request $request): Response
    {
        $this->authorize('export', Delivery::class);

        $filters = $this->dataTableFilters($request);
        $user = auth()->user();

        if ($user->hasRole('shop') && $user->shop) {
            $filters['shop_id'] = $user->shop->id;
        }

        if ($user->hasRole('rider') && $user->rider) {
            $filters['rider_id'] = $user->rider->id;
        }

        $records = $this->deliveryService->export($filters);

        return $this->exportData($records, $format, 'deliveries', [
            'tracking_number' => 'Tracking Number',
            'customer_name' => 'Customer',
            'customer_phone' => 'Phone',
            'status' => 'Status',
            'priority' => 'Priority',
            'delivery_fee' => 'Fee',
            'created_at' => 'Created At',
        ]);
    }
}
