<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Controllers\Concerns\HandlesExports;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    use HandlesDataTables;
    use HandlesExports;

    public function __construct(
        protected UserService $userService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', User::class);

        if ($request->is('api/*')) {
            $paginator = $this->userService->list($this->mobileListFilters($request));

            return $this->mobilePaginatedResponse(
                $paginator,
                UserResource::collection($paginator->items())
            );
        }

        return view('users.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $paginator = $this->userService->list($this->dataTableFilters($request));

        $data = UserResource::collection($paginator->items())->resolve();
        $data = $this->appendActions($data, 'users.partials.actions', fn (array $row) => [
            'id' => $row['uuid'],
            'name' => $row['name'] ?? 'user',
        ]);

        return $this->dataTableResponse($request, $paginator, $data);
    }

    public function create(): JsonResponse
    {
        $this->authorize('create', User::class);

        return $this->success(['html' => '']);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->create($request->validated(), auth()->id());

        return $this->success(new UserResource($user), 'User created successfully.', 201);
    }

    public function edit(string $user): JsonResponse
    {
        return $this->show($user);
    }

    public function show(string $user): JsonResponse
    {
        $record = $this->userService->findByUuid($user);

        if (! $record) {
            return $this->error('User not found.', 404);
        }

        $this->authorize('view', $record);

        return $this->success(new UserResource($record->load('roles')));
    }

    public function update(UpdateUserRequest $request, string $user): JsonResponse
    {
        $record = $this->userService->findByUuid($user);

        if (! $record) {
            return $this->error('User not found.', 404);
        }

        $this->authorize('update', $record);

        $updated = $this->userService->update($user, $request->validated(), auth()->id());

        return $this->success(new UserResource($updated), 'User updated successfully.');
    }

    public function destroy(string $user): JsonResponse
    {
        $record = $this->userService->findByUuid($user);

        if (! $record) {
            return $this->error('User not found.', 404);
        }

        $this->authorize('delete', $record);

        $this->userService->delete($user, auth()->id());

        return $this->success(null, 'User deleted successfully.');
    }

    public function export(string $format, Request $request): Response
    {
        $this->authorize('export', User::class);

        $records = $this->userService->export($this->dataTableFilters($request));

        return $this->exportData($records, $format, 'users', [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'is_active' => 'Active',
            'created_at' => 'Created At',
        ]);
    }
}
