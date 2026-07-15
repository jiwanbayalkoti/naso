@extends('layouts.app')

@section('title', 'Payouts - ' . config('app.name'))
@section('page-title', 'Payouts')

@section('content')
    <x-page-header title="Payouts" subtitle="Mark bank transfers as paid — this deducts shop/rider wallet balance." />

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Created</th>
                        <th>Type</th>
                        <th>Account</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th></th>
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
                        @endphp
                        <tr>
                            <td>{{ $payout->created_at?->format('M d, Y H:i') }}</td>
                            <td>{{ strtoupper($type) }}</td>
                            <td>{{ $label }}</td>
                            <td>Rs {{ number_format((float) $payout->amount, 2) }}</td>
                            <td>
                                <span class="badge text-bg-{{ $payout->status === 'paid' ? 'success' : ($payout->status === 'pending' ? 'warning' : 'secondary') }}">
                                    {{ $payout->status }}
                                </span>
                            </td>
                            <td>{{ $payout->reference ?: '—' }}</td>
                            <td class="text-end">
                                @if($payout->status === 'pending')
                                    <button type="button" class="btn btn-sm btn-success btn-mark-paid"
                                            data-url="{{ route('payouts.mark-paid', $payout->uuid) }}">
                                        Mark paid
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No payouts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($payouts->hasPages())
            <div class="card-footer">{{ $payouts->links() }}</div>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function ($, AjaxHelper) {
    $(document).on('click', '.btn-mark-paid', function () {
        const url = $(this).data('url');
        const reference = window.prompt('Bank transfer reference (optional):', '');
        if (reference === null) return;
        AjaxHelper.post(url, { reference: reference || '' }, {
            success: function (res) {
                NotificationHelper.success(res.message || 'Marked paid');
                window.location.reload();
            }
        });
    });
}(window.jQuery, window.AjaxHelper));
</script>
@endpush
