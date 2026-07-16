<?php

namespace App\Http\Controllers;

use App\Helpers\OfferType;
use App\Models\Offer;
use App\Services\OfferEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfferController extends Controller
{
    public function __construct(
        protected OfferEngine $offerEngine
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $offers = Offer::query()->orderBy('priority')->orderByDesc('id')->get();

        if ($request->is('api/*') || $request->wantsJson()) {
            return $this->success([
                'items' => $offers->map(fn (Offer $o) => $this->serialize($o)),
                'types' => $this->typeOptions(),
            ]);
        }

        return view('offers.index', [
            'offers' => $offers,
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $data = $this->validated($request);
        $offer = Offer::create($data);

        return $this->success($this->serialize($offer), 'Offer created.');
    }

    public function show(Request $request, string $offer): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $model = Offer::query()->where('uuid', $offer)->firstOrFail();

        return $this->success($this->serialize($model));
    }

    public function update(Request $request, string $offer): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $model = Offer::query()->where('uuid', $offer)->firstOrFail();
        $model->update($this->validated($request, $model));

        return $this->success($this->serialize($model->fresh()), 'Offer updated.');
    }

    public function destroy(Request $request, string $offer): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        $model = Offer::query()->where('uuid', $offer)->firstOrFail();
        $model->delete();

        return $this->success(null, 'Offer deleted.');
    }

    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user?->hasRole('shop') && $user->shop) {
            return $this->success([
                'audience' => 'shop',
                'offers' => $this->offerEngine->progressForShop($user->shop),
            ]);
        }

        if ($user?->hasRole('rider') && $user->rider) {
            return $this->success([
                'audience' => 'rider',
                'offers' => $this->offerEngine->progressForRider($user->rider),
            ]);
        }

        abort(403, 'Only shop and rider accounts can view offer progress.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Offer $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audience' => ['required', Rule::in(['shop', 'rider'])],
            'type' => ['required', Rule::in(OfferType::all())],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'window' => ['required', Rule::in(['lifetime', 'calendar_month'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'config' => ['nullable', 'array'],
            'config.min_completed' => ['nullable', 'integer', 'min:0'],
            'config.every_n' => ['nullable', 'integer', 'min:1'],
            'config.first_n' => ['nullable', 'integer', 'min:1'],
            'config.fee_percent_off' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'config.commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'config.bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'config.weekdays' => ['nullable', 'array'],
            'config.weekdays.*' => ['integer', 'min:1', 'max:7'],
            'config.start_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'config.end_hour' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $audience = $data['audience'];
        $type = $data['type'];
        if ($audience === 'shop' && ! in_array($type, OfferType::shopTypes(), true)) {
            abort(422, 'Type does not match shop audience.');
        }
        if ($audience === 'rider' && ! in_array($type, OfferType::riderTypes(), true)) {
            abort(422, 'Type does not match rider audience.');
        }

        $data['is_active'] = $request->boolean('is_active', $existing?->is_active ?? false);
        $data['priority'] = (int) ($data['priority'] ?? $existing?->priority ?? 100);
        $data['config'] = $data['config'] ?? [];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Offer $offer): array
    {
        return [
            'uuid' => $offer->uuid,
            'name' => $offer->name,
            'audience' => $offer->audience,
            'type' => $offer->type,
            'type_label' => OfferType::label($offer->type),
            'is_active' => (bool) $offer->is_active,
            'priority' => (int) $offer->priority,
            'starts_at' => $offer->starts_at?->toIso8601String(),
            'ends_at' => $offer->ends_at?->toIso8601String(),
            'window' => $offer->window,
            'config' => $offer->config ?? [],
            'description' => $offer->description,
            'created_at' => $offer->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, audience: string}>
     */
    protected function typeOptions(): array
    {
        return array_map(fn (string $type) => [
            'value' => $type,
            'label' => OfferType::label($type),
            'audience' => in_array($type, OfferType::shopTypes(), true) ? 'shop' : 'rider',
        ], OfferType::all());
    }
}
