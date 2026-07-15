<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    public function index(): View
    {
        return view('dashboard.index');
    }

    public function stats(): JsonResponse
    {
        return $this->success($this->dashboardService->getStats());
    }

    public function trends(): JsonResponse
    {
        $days = (int) (request('period') ?? request('days', 30));

        return $this->success($this->dashboardService->getTrends($days));
    }

    public function statusChart(): JsonResponse
    {
        return $this->success($this->dashboardService->getStatusChart());
    }

    public function latestDeliveries(): JsonResponse
    {
        $limit = (int) request('limit', 10);

        return $this->success($this->dashboardService->getLatestDeliveries($limit));
    }

    public function pendingDeliveries(): JsonResponse
    {
        $limit = (int) request('limit', 10);

        return $this->success($this->dashboardService->getPendingDeliveries($limit));
    }

    public function onlineRiders(): JsonResponse
    {
        return $this->success($this->dashboardService->getOnlineRiders());
    }
}
