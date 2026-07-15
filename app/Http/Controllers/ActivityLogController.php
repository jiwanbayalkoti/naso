<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Resources\ActivityLogResource;
use App\Repositories\Contracts\ActivityLogRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    use HandlesDataTables;

    public function __construct(
        protected ActivityLogRepositoryInterface $activityLogRepository
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', \App\Models\ActivityLog::class);

        if ($request->is('api/*')) {
            $paginator = $this->activityLogRepository->datatable($this->mobileListFilters($request));

            return $this->mobilePaginatedResponse(
                $paginator,
                ActivityLogResource::collection($paginator->items())
            );
        }

        return view('activity-logs.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\ActivityLog::class);

        $paginator = $this->activityLogRepository->datatable($this->dataTableFilters($request));

        return $this->dataTableResponse(
            $request,
            $paginator,
            ActivityLogResource::collection($paginator->items())->resolve()
        );
    }
}
