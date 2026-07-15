<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Requests\Shop\StoreShopRequest;
use App\Http\Requests\Shop\UpdateShopRequest;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use App\Services\RegistrationService;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ShopController extends Controller
{
    use HandlesDataTables;
    use HandlesExports;

    public function __construct(
        protected ShopService $shopService,
        protected RegistrationService $registrationService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Shop::class);

        if ($request->is('api/*')) {
            $filters = $this->mobileListFilters($request);
            $user = auth()->user();

            if ($user->hasRole('shop')) {
                $filters['user_id'] = $user->id;
            }

            $paginator = $this->shopService->list($filters);

            return $this->mobilePaginatedResponse(
                $paginator,
                ShopResource::collection($paginator->items())
            );
        }

        return view('shops.index', [
            'cities' => $this->shopService->getDistinctCities(),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shop::class);

        $filters = $this->dataTableFilters($request);
        $user = auth()->user();

        if ($user->hasRole('shop')) {
            $filters['user_id'] = $user->id;
        }

        $paginator = $this->shopService->list($filters);

        $data = ShopResource::collection($paginator->items())->resolve();
        $data = $this->appendActions($data, 'shops.partials.actions', fn (array $row) => [
            'id' => $row['uuid'],
            'name' => $row['name'] ?? 'shop',
        ]);

        return $this->dataTableResponse($request, $paginator, $data);
    }

    public function create(): JsonResponse
    {
        $this->authorize('create', Shop::class);

        return $this->success(['html' => '']);
    }

    public function store(StoreShopRequest $request): JsonResponse
    {
        $this->authorize('create', Shop::class);

        if ($request->filled('owner_email')) {
            $result = $this->registrationService->registerShop($request->validated(), false, false);
            $shop = $result['shop'];
        } else {
            $shop = $this->shopService->create($request->validated(), auth()->id());
        }

        return $this->success(new ShopResource($shop), 'Shop created successfully.', 201);
    }

    public function edit(string $shop): JsonResponse
    {
        return $this->show($shop);
    }

    public function show(string $shop): JsonResponse
    {
        $record = $this->shopService->findByUuid($shop);

        if (! $record) {
            return $this->error('Shop not found.', 404);
        }

        $this->authorize('view', $record);

        return $this->success(new ShopResource($record->load('user')));
    }

    public function update(UpdateShopRequest $request, string $shop): JsonResponse
    {
        $record = $this->shopService->findByUuid($shop);

        if (! $record) {
            return $this->error('Shop not found.', 404);
        }

        $this->authorize('update', $record);

        $updated = $this->shopService->update($shop, $request->validated(), auth()->id());

        return $this->success(new ShopResource($updated), 'Shop updated successfully.');
    }

    public function destroy(string $shop): JsonResponse
    {
        $record = $this->shopService->findByUuid($shop);

        if (! $record) {
            return $this->error('Shop not found.', 404);
        }

        $this->authorize('delete', $record);

        $this->shopService->delete($shop, auth()->id());

        return $this->success(null, 'Shop deleted successfully.');
    }

    public function export(string $format, Request $request): Response
    {
        $this->authorize('export', Shop::class);

        $filters = $this->dataTableFilters($request);
        $user = auth()->user();

        if ($user->hasRole('shop')) {
            $filters['user_id'] = $user->id;
        }

        $records = $this->shopService->export($filters);

        return $this->exportData($records, $format, 'shops', [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'city' => 'City',
            'is_active' => 'Active',
            'created_at' => 'Created At',
        ]);
    }
}
