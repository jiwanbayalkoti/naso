<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Requests\Menu\StoreMenuRequest;
use App\Http\Requests\Menu\UpdateMenuRequest;
use App\Http\Resources\MenuResource;
use App\Models\Menu;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MenuController extends Controller
{
    use HandlesDataTables;
    use HandlesExports;

    public function __construct(
        protected MenuService $menuService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Menu::class);

        if ($request->is('api/*')) {
            $paginator = $this->menuService->list($this->mobileListFilters($request));

            return $this->mobilePaginatedResponse(
                $paginator,
                MenuResource::collection($paginator->items())
            );
        }

        return view('menus.index', [
            'parentMenus' => $this->menuService->parentOptions(),
            'permissions' => $this->menuService->availablePermissions(),
            'routeNames' => $this->menuService->availableRouteNames(),
        ]);
    }

    public function sidebar(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthenticated.', 401);
        }

        $menus = $this->menuService->getSidebarForUser($user);

        return $this->success(MenuResource::collection($menus)->resolve());
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Menu::class);

        $paginator = $this->menuService->list($this->dataTableFilters($request));

        $data = MenuResource::collection($paginator->items())->resolve();
        $data = $this->appendActions($data, 'menus.partials.actions', fn (array $row) => [
            'id' => $row['uuid'],
            'name' => $row['title'] ?? 'menu',
        ]);

        return $this->dataTableResponse($request, $paginator, $data);
    }

    public function create(): JsonResponse
    {
        $this->authorize('create', Menu::class);

        return $this->success([
            'parents' => MenuResource::collection($this->menuService->parentOptions()),
            'permissions' => $this->menuService->availablePermissions(),
            'route_names' => $this->menuService->availableRouteNames(),
        ]);
    }

    public function store(StoreMenuRequest $request): JsonResponse
    {
        $this->authorize('create', Menu::class);

        $menu = $this->menuService->create($request->validated(), auth()->id());

        return $this->success(new MenuResource($menu), 'Menu created successfully.', 201);
    }

    public function edit(string $menu): JsonResponse
    {
        return $this->show($menu);
    }

    public function show(string $menu): JsonResponse
    {
        $record = $this->menuService->findByUuid($menu);

        if (! $record) {
            return $this->error('Menu not found.', 404);
        }

        $this->authorize('view', $record);

        return $this->success(new MenuResource($record->load('parent')));
    }

    public function update(UpdateMenuRequest $request, string $menu): JsonResponse
    {
        $record = $this->menuService->findByUuid($menu);

        if (! $record) {
            return $this->error('Menu not found.', 404);
        }

        $this->authorize('update', $record);

        $updated = $this->menuService->update($menu, $request->validated(), auth()->id());

        return $this->success(new MenuResource($updated), 'Menu updated successfully.');
    }

    public function destroy(string $menu): JsonResponse
    {
        $record = $this->menuService->findByUuid($menu);

        if (! $record) {
            return $this->error('Menu not found.', 404);
        }

        $this->authorize('delete', $record);

        $this->menuService->delete($menu, auth()->id());

        return $this->success(null, 'Menu deleted successfully.');
    }

    public function export(string $format, Request $request): Response
    {
        $this->authorize('export', Menu::class);

        $records = $this->menuService->export($this->dataTableFilters($request));

        return $this->exportData($records, $format, 'menus', [
            'title' => 'Title',
            'route_name' => 'Route',
            'permission' => 'Permission',
            'sort_order' => 'Order',
            'is_active' => 'Active',
            'created_at' => 'Created At',
        ]);
    }
}
