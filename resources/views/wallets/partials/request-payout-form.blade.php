@php
    $type = $type ?? 'shop';
    $uuid = $uuid ?? '';
    $balance = (float) ($balance ?? 0);
    $available = (float) ($available ?? $balance);
    $formId = $type === 'shop' ? 'shop-payout-form' : 'rider-payout-form';
    $canRequest = $available > 0 && (
        auth()->user()?->hasRole('super_admin')
        || ($type === 'shop' && auth()->user()?->hasRole('shop'))
        || ($type === 'rider' && auth()->user()?->hasRole('rider'))
    );
@endphp

@if($canRequest)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                {{ auth()->user()->hasRole('super_admin') ? 'Create payout request' : 'Request payment' }}
            </h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">
                Available to request: <strong>Rs {{ number_format($available, 2) }}</strong>
                @if($available < $balance)
                    <span class="text-muted">(wallet Rs {{ number_format($balance, 2) }}, rest locked in pending requests)</span>
                @endif
            </p>
            <form id="{{ $formId }}" class="row g-3">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="uuid" value="{{ $uuid }}">
                <div class="col-12">
                    <label class="form-label">Request type</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="mode" id="{{ $formId }}_mode_full" value="full" checked>
                        <label class="btn btn-outline-primary" for="{{ $formId }}_mode_full">Full payment</label>
                        <input type="radio" class="btn-check" name="mode" id="{{ $formId }}_mode_partial" value="partial">
                        <label class="btn btn-outline-primary" for="{{ $formId }}_mode_partial">Partial payment</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0.01" max="{{ $available }}" name="amount"
                           id="{{ $formId }}_amount" class="form-control"
                           value="{{ number_format($available, 2, '.', '') }}" required readonly>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Note</label>
                    <input type="text" name="note" class="form-control" placeholder="Optional note">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        {{ auth()->user()->hasRole('super_admin') ? 'Create request' : 'Request payment' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @push('scripts')
    <script>
    (function ($, AjaxHelper) {
        const formId = @json($formId);
        const available = {{ json_encode($available) }};
        const $form = $('#' + formId);
        const $amount = $('#' + formId + '_amount');

        function syncMode() {
            const mode = $form.find('input[name="mode"]:checked').val();
            if (mode === 'full') {
                $amount.val(Number(available).toFixed(2)).prop('readonly', true);
            } else {
                $amount.prop('readonly', false);
            }
        }

        $form.find('input[name="mode"]').on('change', syncMode);
        syncMode();

        $form.on('submit', function (e) {
            e.preventDefault();
            AjaxHelper.post(@json(route('payouts.store')), $form.serialize(), {
                success: function (res) {
                    NotificationHelper.success(res.message || 'Payout request submitted');
                    window.location.reload();
                }
            });
        });
    }(window.jQuery, window.AjaxHelper));
    </script>
    @endpush
@elseif($balance > 0 && $available <= 0)
    <div class="alert alert-warning mb-4">
        Your wallet balance is locked in pending payout requests. Wait for admin to pay, or reduce an existing request.
    </div>
@endif
