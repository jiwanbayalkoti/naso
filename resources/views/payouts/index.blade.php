@extends('layouts.app')

@php
    $isAdmin = $isAdmin ?? auth()->user()?->hasRole('super_admin');
    $pageTitle = $isAdmin ? 'Payouts' : 'Payment History';
@endphp

@section('title', $pageTitle . ' - ' . config('app.name'))
@section('page-title', $pageTitle)

@section('content')
    <x-page-header
        title="{{ $pageTitle }}"
        subtitle="{{ $isAdmin ? 'See requested amount and bank details. Pay full or partial, then mark paid.' : 'Your payout requests and paid transfers.' }}"
    />

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        @if($isAdmin)
                            <th>Account</th>
                            <th>Bank details</th>
                        @endif
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th>Note</th>
                        @if($isAdmin)
                            <th class="text-end">Pay</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($payouts as $payout)
                        @php
                            $payable = $payout->payable;
                            $label = $payable instanceof \App\Models\Shop
                                ? ($payable->name ?? 'Shop')
                                : ($payable?->user?->name ?? 'Rider');
                            $type = $payable instanceof \App\Models\Shop ? 'shop' : 'rider';
                            $amount = (float) $payout->amount;
                        @endphp
                        <tr>
                            <td>
                                <div>{{ $payout->created_at?->format('M d, Y H:i') }}</div>
                                @if($payout->paid_at)
                                    <div class="small text-muted">Paid {{ $payout->paid_at->format('M d, Y') }}</div>
                                @endif
                            </td>
                            @if($isAdmin)
                                <td>
                                    <div class="fw-semibold">{{ $label }}</div>
                                    <div class="small text-muted text-uppercase">{{ $type }}</div>
                                </td>
                                <td class="small">
                                    @if($payable?->bank_name || $payable?->bank_account_number)
                                        <div class="fw-semibold">{{ $payable->bank_name ?: '—' }}</div>
                                        <div>{{ $payable->bank_account_name }}</div>
                                        <div class="font-monospace">{{ $payable->bank_account_number }}</div>
                                    @else
                                        <span class="text-danger">No bank details</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                <div class="fw-semibold">Rs {{ number_format($amount, 2) }}</div>
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $payout->status === 'paid' ? 'success' : ($payout->status === 'pending' ? 'warning' : 'secondary') }}">
                                    {{ $payout->status }}
                                </span>
                            </td>
                            <td>{{ $payout->reference ?: '—' }}</td>
                            <td class="small">{{ $payout->note ?: '—' }}</td>
                            @if($isAdmin)
                                <td class="text-end text-nowrap">
                                    @if($payout->status === 'pending')
                                        <button type="button"
                                                class="btn btn-sm btn-outline-success btn-pay-payout"
                                                data-url="{{ route('payouts.mark-paid', $payout->uuid) }}"
                                                data-amount="{{ number_format($amount, 2, '.', '') }}"
                                                data-name="{{ $label }}"
                                                data-bank="{{ $payable?->bank_name }}"
                                                data-account-name="{{ $payable?->bank_account_name }}"
                                                data-account-number="{{ $payable?->bank_account_number }}">
                                            Pay
                                        </button>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 8 : 5 }}" class="text-center text-muted py-4">
                                No payment history yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payouts->hasPages())
            <div class="card-footer">{{ $payouts->links() }}</div>
        @endif
    </div>

    @if($isAdmin)
        <div class="modal fade" id="payPayoutModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Pay payout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-1"><strong id="pay-account-name">—</strong></p>
                        <p class="small text-muted mb-3" id="pay-bank-line">—</p>
                        <div class="mb-3">
                            <label class="form-label">Payment type</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="pay_mode" id="pay_mode_full" value="full" checked>
                                <label class="btn btn-outline-primary" for="pay_mode_full">Full payment</label>
                                <input type="radio" class="btn-check" name="pay_mode" id="pay_mode_partial" value="partial">
                                <label class="btn btn-outline-primary" for="pay_mode_partial">Partial payment</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pay-amount">Amount to pay</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="pay-amount">
                            <div class="form-text">Requested: Rs <span id="pay-requested">0</span></div>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="pay-reference">Bank transfer reference (optional)</label>
                            <input type="text" class="form-control" id="pay-reference" placeholder="e.g. TXN123">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="pay-confirm-btn">Confirm paid</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if($isAdmin)
@push('scripts')
<script>
(function ($, AjaxHelper) {
    let payUrl = '';
    let requestedAmount = 0;

    function syncAmountField() {
        const mode = $('input[name="pay_mode"]:checked').val();
        const $amount = $('#pay-amount');
        if (mode === 'full') {
            $amount.val(requestedAmount.toFixed(2)).prop('readonly', true);
        } else {
            $amount.prop('readonly', false);
        }
    }

    $(document).on('click', '.btn-pay-payout', function () {
        const $btn = $(this);
        payUrl = $btn.data('url');
        requestedAmount = parseFloat($btn.data('amount')) || 0;
        $('#pay-account-name').text($btn.data('name') || '—');
        const bank = $btn.data('bank') || '—';
        const accName = $btn.data('account-name') || '';
        const accNo = $btn.data('account-number') || '';
        $('#pay-bank-line').text([bank, accName, accNo].filter(Boolean).join(' · '));
        $('#pay-requested').text(requestedAmount.toFixed(2));
        $('#pay_mode_full').prop('checked', true);
        $('#pay-reference').val('');
        syncAmountField();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('payPayoutModal')).show();
    });

    $('input[name="pay_mode"]').on('change', syncAmountField);

    $('#pay-confirm-btn').on('click', function () {
        const mode = $('input[name="pay_mode"]:checked').val();
        const amount = parseFloat($('#pay-amount').val());
        if (!amount || amount <= 0) {
            NotificationHelper.error('Enter a valid amount');
            return;
        }
        if (amount > requestedAmount) {
            NotificationHelper.error('Amount cannot exceed requested payout');
            return;
        }
        AjaxHelper.post(payUrl, {
            mode: mode,
            amount: amount,
            reference: $('#pay-reference').val() || ''
        }, {
            success: function (res) {
                NotificationHelper.success(res.message || 'Marked paid');
                window.location.reload();
            }
        });
    });
}(window.jQuery, window.AjaxHelper));
</script>
@endpush
@endif
