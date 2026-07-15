@extends('layouts.app')

@section('title', 'Rider Earnings - ' . config('app.name'))
@section('page-title', 'Rider Earnings')

@section('content')
    <x-page-header title="Rider Earnings" subtitle="Ride fees after platform commission. Paid out manually by admin." />

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Rider</div>
                    <div class="h5 mb-0">{{ $rider->user?->name ?? 'Rider' }}</div>
                    <div class="small text-muted">{{ (int) $rider->total_deliveries }} completed rides</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="text-muted small">Available balance</div>
                    <div class="h3 mb-0 text-primary">Rs {{ number_format((float) $rider->balance, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">Bank</div>
                    <div class="mb-0">{{ $rider->bank_name ?: '—' }}</div>
                    <div class="small text-muted">{{ $rider->bank_account_name }} {{ $rider->bank_account_number }}</div>
                    @if(auth()->user()->hasRole('rider'))
                        <a href="{{ route('profile.index') }}#bank-details-form" class="btn btn-sm btn-outline-secondary mt-2">Edit bank details</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->hasRole('super_admin'))
        <div class="card mb-4">
            <div class="card-body">
                <form id="rider-payout-form" class="row g-2 align-items-end">
                    <input type="hidden" name="type" value="rider">
                    <input type="hidden" name="uuid" value="{{ $rider->uuid }}">
                    <div class="col-md-3">
                        <label class="form-label">Payout amount</label>
                        <input type="number" step="0.01" min="0.01" max="{{ (float) $rider->balance }}" name="amount" class="form-control" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Note</label>
                        <input type="text" name="note" class="form-control" placeholder="Bank transfer note">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Create payout request</button>
                        <a href="{{ route('payouts.index') }}" class="btn btn-outline-secondary">All payouts</a>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Recent earnings</h5></div>
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
                        <tr><td colspan="5" class="text-center text-muted py-4">No earnings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function ($, AjaxHelper) {
    $('#rider-payout-form').on('submit', function (e) {
        e.preventDefault();
        AjaxHelper.post(@json(route('payouts.store')), $(this).serialize(), {
            success: function (res) {
                NotificationHelper.success(res.message || 'Payout created');
                window.location.href = @json(route('payouts.index'));
            }
        });
    });
}(window.jQuery, window.AjaxHelper));
</script>
@endpush
