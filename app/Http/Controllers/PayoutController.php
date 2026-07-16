<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use App\Models\Rider;
use App\Models\Shop;
use App\Services\PayoutRequestNotifier;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected PayoutRequestNotifier $payoutRequestNotifier
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $query = Payout::query()
            ->with(['payable.user', 'requestedBy', 'processedBy'])
            ->latest();

        $isAdmin = $user->hasRole('super_admin');

        if ($isAdmin) {
            // All payouts
        } elseif ($user->hasRole('shop') && $user->shop) {
            $query->where('payable_type', Shop::class)->where('payable_id', $user->shop->id);
        } elseif ($user->hasRole('rider') && $user->rider) {
            $query->where('payable_type', Rider::class)->where('payable_id', $user->rider->id);
        } else {
            abort(403);
        }

        $payouts = $query->paginate(25);

        if ($request->is('api/*') || $request->wantsJson()) {
            return $this->success([
                'items' => $payouts->getCollection()->map(fn (Payout $p) => $this->serialize($p)),
                'meta' => [
                    'current_page' => $payouts->currentPage(),
                    'last_page' => $payouts->lastPage(),
                    'per_page' => $payouts->perPage(),
                    'total' => $payouts->total(),
                ],
                'is_admin' => $isAdmin,
            ]);
        }

        return view('payouts.index', [
            'payouts' => $payouts,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:shop,rider'],
            'uuid' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:500'],
            'mode' => ['nullable', 'in:full,partial'],
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

        $available = $this->walletService->availableForPayout($payable);
        $amount = (float) $data['amount'];
        if (($data['mode'] ?? null) === 'full') {
            $amount = $available;
        }

        $payout = $this->walletService->createPayout(
            $payable,
            $amount,
            $user->id,
            $data['note'] ?? null
        );

        $payout->load(['payable.user', 'requestedBy']);
        $this->payoutRequestNotifier->notifyAdmins($payout);

        return $this->success($this->serialize($payout), 'Payout request submitted. Admin will process the transfer.');
    }

    public function markPaid(Request $request, string $payout): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'mode' => ['nullable', 'in:full,partial'],
        ]);

        $model = Payout::query()->where('uuid', $payout)->firstOrFail();
        $requested = (float) $model->amount;
        $paidAmount = null;

        if (($data['mode'] ?? 'full') === 'partial' || isset($data['amount'])) {
            $paidAmount = isset($data['amount']) ? (float) $data['amount'] : $requested;
        }

        $result = $this->walletService->markPayoutPaid(
            $model,
            $request->user()->id,
            $data['reference'] ?? null,
            $paidAmount
        );

        /** @var Payout $paid */
        $paid = $result['paid'];
        $remainder = $result['remainder'];
        $wasPartial = (bool) $result['was_partial'];

        $this->payoutRequestNotifier->notifyPaid(
            $paid,
            $wasPartial,
            $remainder ? (float) $remainder->amount : null
        );

        $message = $wasPartial
            ? 'Partial payment recorded. Remaining amount kept as a pending request.'
            : 'Payout marked as paid. Balance updated.';

        return $this->success([
            'paid' => $this->serialize($paid),
            'remainder' => $remainder ? $this->serialize($remainder) : null,
            'was_partial' => $wasPartial,
        ], $message);
    }

    protected function serialize(Payout $payout): array
    {
        $payable = $payout->payable;
        $name = null;
        $type = null;
        $uuid = null;
        $balance = null;
        $available = null;

        if ($payable instanceof Shop) {
            $type = 'shop';
            $name = $payable->name;
            $uuid = $payable->uuid;
            $balance = (float) $payable->balance;
            $available = $this->walletService->availableForPayout($payable);
        } elseif ($payable instanceof Rider) {
            $type = 'rider';
            $name = $payable->user?->name ?? 'Rider';
            $uuid = $payable->uuid;
            $balance = (float) $payable->balance;
            $available = $this->walletService->availableForPayout($payable);
        }

        return [
            'uuid' => $payout->uuid,
            'type' => $type,
            'payable_uuid' => $uuid,
            'payable_name' => $name,
            'payable_balance' => $balance,
            'available_for_payout' => $available,
            'bank_name' => $payable?->bank_name,
            'bank_account_name' => $payable?->bank_account_name,
            'bank_account_number' => $payable?->bank_account_number,
            'amount' => (float) $payout->amount,
            'status' => $payout->status,
            'reference' => $payout->reference,
            'note' => $payout->note,
            'paid_at' => $payout->paid_at?->toIso8601String(),
            'created_at' => $payout->created_at?->toIso8601String(),
        ];
    }
}
