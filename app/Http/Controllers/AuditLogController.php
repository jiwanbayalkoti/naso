<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesDataTables;
use App\Http\Resources\AuditLogResource;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    use HandlesDataTables;

    public function __construct(
        protected AuditLogRepositoryInterface $auditLogRepository
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', \App\Models\AuditLog::class);

        if ($request->is('api/*')) {
            $paginator = $this->auditLogRepository->datatable($this->mobileListFilters($request));

            return $this->mobilePaginatedResponse(
                $paginator,
                AuditLogResource::collection($paginator->items())
            );
        }

        return view('audit-logs.index');
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\AuditLog::class);

        $paginator = $this->auditLogRepository->datatable($this->dataTableFilters($request));

        return $this->dataTableResponse(
            $request,
            $paginator,
            AuditLogResource::collection($paginator->items())->resolve()
        );
    }
}
