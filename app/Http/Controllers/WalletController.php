<?php

namespace App\Http\Controllers;

use App\Http\Resources\ShopResource;
use App\Models\Rider;
use App\Models\Shop;
use App\Models\WalletTransaction;
use App\Services\DeliveryFeeCalculatorService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected DeliveryFeeCalculatorService $feeCalculator
    ) {}

    public function estimateFee(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_id' => ['nullable', 'exists:shops,id'],
            'pickup_address' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'pickup_latitude' => ['nullable', 'numeric'],
            'pickup_longitude' => ['nullable', 'numeric'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $shop = null;
        $user = $request->user();
        if ($user?->hasRole('shop') && $user->shop) {
            $shop = $user->shop;
        } elseif (! empty($data['shop_id'])) {
            $shop = Shop::query()->find($data['shop_id']);
        }

        $estimate = $this->feeCalculator->estimate(
            pickupLat: isset($data['pickup_latitude']) ? (float) $data['pickup_latitude'] : null,
            pickupLng: isset($data['pickup_longitude']) ? (float) $data['pickup_longitude'] : null,
            dropLat: isset($data['latitude']) ? (float) $data['latitude'] : null,
            dropLng: isset($data['longitude']) ? (float) $data['longitude'] : null,
            pickupAddress: $data['pickup_address'] ?? null,
            dropAddress: $data['delivery_address'] ?? null,
            shop: $shop
        );

        return $this->success($estimate);
    }

    public function shopWallet(Request $request, ?string $shop = null): View|JsonResponse
    {
        $user = $request->user();
        $shopModel = $this->resolveShop($user, $shop);

        $transactions = WalletTransaction::query()
            ->where('shop_id', $shopModel->id)
            ->latest()
            ->limit(100)
            ->get();

        if ($request->is('api/*') || $request->wantsJson()) {
            return $this->success([
                'shop' => [
                    'uuid' => $shopModel->uuid,
                    'name' => $shopModel->name,
                    'balance' => (float) $shopModel->balance,
                    'bank_name' => $shopModel->bank_name,
                    'bank_account_name' => $shopModel->bank_account_name,
                    'bank_account_number' => $shopModel->bank_account_number,
                ],
                'transactions' => $transactions->map(fn (WalletTransaction $t) => [
                    'uuid' => $t->uuid,
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'balance_after' => (float) $t->balance_after,
                    'note' => $t->note,
                    'created_at' => $t->created_at?->toIso8601String(),
                ]),
            ]);
        }

        return view('wallets.shop', [
            'shop' => $shopModel,
            'transactions' => $transactions,
        ]);
    }

    public function riderWallet(Request $request, ?string $rider = null): View|JsonResponse
    {
        $user = $request->user();
        $riderModel = $this->resolveRider($user, $rider);

        $transactions = WalletTransaction::query()
            ->where('rider_id', $riderModel->id)
            ->latest()
            ->limit(100)
            ->get();

        if ($request->is('api/*') || $request->wantsJson()) {
            return $this->success([
                'rider' => [
                    'uuid' => $riderModel->uuid,
                    'name' => $riderModel->user?->name,
                    'balance' => (float) $riderModel->balance,
                    'total_deliveries' => (int) $riderModel->total_deliveries,
                    'bank_name' => $riderModel->bank_name,
                    'bank_account_name' => $riderModel->bank_account_name,
                    'bank_account_number' => $riderModel->bank_account_number,
                ],
                'transactions' => $transactions->map(fn (WalletTransaction $t) => [
                    'uuid' => $t->uuid,
                    'type' => $t->type,
                    'amount' => (float) $t->amount,
                    'balance_after' => (float) $t->balance_after,
                    'note' => $t->note,
                    'created_at' => $t->created_at?->toIso8601String(),
                ]),
            ]);
        }

        return view('wallets.rider', [
            'rider' => $riderModel->load('user'),
            'transactions' => $transactions,
        ]);
    }

    protected function resolveShop($user, ?string $shopUuid): Shop
    {
        if ($user->hasRole('shop') && $user->shop) {
            return $user->shop;
        }

        abort_unless($user->hasRole('super_admin'), 403);
        $shop = Shop::query()->where('uuid', $shopUuid)->firstOrFail();

        return $shop;
    }

    protected function resolveRider($user, ?string $riderUuid): Rider
    {
        if ($user->hasRole('rider') && $user->rider) {
            return $user->rider;
        }

        abort_unless($user->hasRole('super_admin'), 403);
        $rider = Rider::query()->where('uuid', $riderUuid)->with('user')->firstOrFail();

        return $rider;
    }
}
