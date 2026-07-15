<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Rider;
use App\Models\Shop;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $payouts = Payout::query()
            ->with(['payable', 'requestedBy', 'processedBy'])
            ->latest()
            ->paginate(25);

        if ($request->is('api/*') || $request->wantsJson()) {
            return $this->success([
                'items' => $payouts->getCollection()->map(fn (Payout $p) => $this->serialize($p)),
                'meta' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page' => $payouts->lastPage(),
                    'per_page' => $payouts->perPage(),
                    'total' => $payouts->total(),
                ],
            ]);
        }

        return view('payouts.index', compact('payouts'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:shop,rider'],
            'uuid' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $payable = null;

        if ($data['type'] === 'shop') {
            if ($user->hasRole('shop') && $user->shop) {
                $payable = $user->shop;
            } else {
                abort_unless($user->hasRole('super_admin'), 403);
                $payable = Shop::query()->where('uuid', $data['uuid'])->firstOrFail();
            }
        } else {
            if ($user->hasRole('rider') && $user->rider) {
                $payable = $user->rider;
            } else {
                abort_unless($user->hasRole('super_admin'), 403);
                $payable = Rider::query()->where('uuid', $data['uuid'])->firstOrFail();
            }
        }

        $payout = $this->walletService->createPayout(
            $payable,
            (float) $data['amount'],
            $user->id,
            $data['note'] ?? null
        );

        return $this->success($this->serialize($payout->load(['payable', 'requestedBy'])), 'Payout created.');
    }

    public function markPaid(Request $request, string $payout): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $model = Payout::query()->where('uuid', $payout)->firstOrFail();
        $model = $this->walletService->markPayoutPaid(
            $model,
            $request->user()->id,
            $data['reference'] ?? null
        );

        return $this->success($this->serialize($model), 'Payout marked as paid. Balance updated.');
    }

    protected function serialize(Payout $payout): array
    {
        $payable = $payout->payable;
        $name = null;
        $type = null;
        $uuid = null;
        $balance = null;

        if ($payable instanceof Shop) {
            $type = 'shop';
            $name = $payable->name;
            $uuid = $payable->uuid;
            $balance = (float) $payable->balance;
        } elseif ($payable instanceof Rider) {
            $type = 'rider';
            $name = $payable->user?->name ?? 'Rider';
            $uuid = $payable->uuid;
            $balance = (float) $payable->balance;
        }

        return [
            'uuid' => $payout->uuid,
            'type' => $type,
            'payable_uuid' => $uuid,
            'payable_name' => $name,
            'payable_balance' => $balance,
            'amount' => (float) $payout->amount,
            'status' => $payout->status,
            'reference' => $payout->reference,
            'note' => $payout->note,
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'created_at' => $payout->created_at?->toIso8601String(),
        ];
    }
}
