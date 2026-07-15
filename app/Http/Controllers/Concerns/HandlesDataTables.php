<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait HandlesDataTables
{
    /**
     * Build standardized DataTables filters from the request.
     *
     * @return array<string, mixed>
     */
    protected function dataTableFilters(Request $request): array
    {
        $columns = $request->input('columns', []);
        $order = $request->input('order', []);
        $sortBy = 'created_at';
        $sortDirection = 'desc';

        if (! empty($order[0]['column']) && isset($columns[$order[0]['column']]['data'])) {
            $sortBy = $columns[$order[0]['column']]['data'];
            $sortDirection = ($order[0]['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        }

        return array_merge($request->only([
            'shop_id',
            'rider_id',
            'status',
            'priority',
            'is_active',
            'is_online',
            'is_available',
            'activity_type',
            'event',
            'city',
            'role',
            'date_from',
            'date_to',
            'type',
        ]), [
            'search' => $request->input('search_filter') ?: $request->input('search.value'),
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
            'per_page' => (int) $request->input('length', 15),
            'page' => ((int) $request->input('start', 0) / max((int) $request->input('length', 15), 1)) + 1,
        ]);
    }

    /**
     * Return a standardized DataTables JSON response.
     */
    protected function dataTableResponse(
        Request $request,
        LengthAwarePaginator $paginator,
        mixed $data
    ): JsonResponse {
        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $paginator->total(),
            'recordsFiltered' => $paginator->total(),
            'data' => $data,
        ]);
    }

    /**
     * Build mobile-friendly list filters from query parameters.
     *
     * @return array<string, mixed>
     */
    protected function mobileListFilters(Request $request): array
    {
        return array_merge($request->only([
            'shop_id',
            'rider_id',
            'status',
            'priority',
            'is_active',
            'is_online',
            'is_available',
            'activity_type',
            'event',
            'city',
            'role',
            'date_from',
            'date_to',
            'type',
        ]), [
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_direction' => $request->input('sort_direction', 'desc'),
            'per_page' => min(max((int) $request->input('per_page', 15), 1), 100),
            'page' => max((int) $request->input('page', 1), 1),
        ]);
    }

    /**
     * Return a standardized paginated JSON response for mobile clients.
     */
    protected function mobilePaginatedResponse(LengthAwarePaginator $paginator, mixed $items): JsonResponse
    {
        if ($items instanceof \Illuminate\Http\Resources\Json\ResourceCollection) {
            $items = $items->resolve();
        }

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Append rendered action buttons to each DataTable row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $viewData
     * @return array<int, array<string, mixed>>
     */
    protected function appendActions(array $rows, string $view, callable $viewData): array
    {
        return collect($rows)->map(function (array $row) use ($view, $viewData) {
            $row['actions'] = view($view, $viewData($row))->render();

            return $row;
        })->all();
    }
}
