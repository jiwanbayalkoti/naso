@extends('layouts.app')

@section('title', 'Shop Wallet - ' . config('app.name'))
@section('page-title', 'Shop Wallet')

@section('content')
    <x-page-header title="Shop Wallet" subtitle="COD collections minus delivery fees. Super admin transfers balance manually." />

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Shop</div>
                    <div class="h5 mb-0">{{ $shop->name }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body">
                    <div class="text-muted small">Available balance</div>
                    <div class="h3 mb-0 text-success">Rs {{ number_format((float) $shop->balance, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Bank</div>
                    <div class="mb-0">{{ $shop->bank_name ?: '—' }}</div>
                    <div class="small text-muted">{{ $shop->bank_account_name }} {{ $shop->bank_account_number }}</div>
                    @if(auth()->user()->hasRole('shop'))
                        <a href="{{ route('profile.index') }}#bank-details-form" class="btn btn-sm btn-outline-secondary mt-2">Edit bank details</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('wallets.partials.offers', ['offers' => $offers ?? []])

    @include('wallets.partials.pending-payouts', [
        'pendingPayouts' => $pendingPayouts ?? collect(),
        'focusPayoutUuid' => $focusPayoutUuid ?? null,
    ])

    @include('wallets.partials.request-payout-form', [
        'type' => 'shop',
        'uuid' => $shop->uuid,
        'balance' => (float) $shop->balance,
        'available' => (float) ($availableForPayout ?? $shop->balance),
    ])

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Recent transactions</h5></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance after</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                        <tr>
                            <td>{{ $tx->created_at?->format('M d, Y H:i') }}</td>
                            <td><code>{{ $tx->type }}</code></td>
                            <td class="{{ $tx->amount >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format((float) $tx->amount, 2) }}
                            </td>
                            <td>{{ number_format((float) $tx->balance_after, 2) }}</td>
                            <td>{{ $tx->note }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function ($, AjaxHelper) {
    $(document).on('click', '.btn-mark-paid', function () {
        window.location.href = @json(route('payouts.index'));
    });

    const focusId = @json($focusPayoutUuid ?? null);
    if (focusId) {
        const el = document.getElementById('payout-' + focusId);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}(window.jQuery, window.AjaxHelper));
</script>
@endpush
