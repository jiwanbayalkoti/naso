@php
    $pendingPayouts = $pendingPayouts ?? collect();
    $focusPayoutUuid = $focusPayoutUuid ?? null;
    $isAdmin = auth()->user()?->hasRole('super_admin');
@endphp

@if($pendingPayouts->isNotEmpty())
    <div class="card mb-4 border-warning" id="pending-payouts">
        <div class="card-header bg-warning-subtle">
            <h5 class="mb-0">Pending payout requests</h5>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Requested</th>
                        <th>Amount</th>
                        <th>Note</th>
                        @if($isAdmin)
                            <th class="text-end">Action</th>
                        @else
                            <th>Status</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingPayouts as $payout)
                        <tr class="{{ $focusPayoutUuid === $payout->uuid ? 'table-warning' : '' }}"
                            id="payout-{{ $payout->uuid }}">
                            <td>{{ $payout->created_at?->format('M d, Y H:i') }}</td>
                            <td><strong>Rs {{ number_format((float) $payout->amount, 2) }}</strong></td>
                            <td>{{ $payout->note ?: '—' }}</td>
                            @if($isAdmin)
                                <td class="text-end">
                                    <a href="{{ route('payouts.index') }}" class="btn btn-sm btn-success">
                                        Pay on Payouts
                                    </a>
                                </td>
                            @else
                                <td><span class="badge text-bg-warning">pending</span></td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($isAdmin)
            <div class="card-footer small text-muted">
                Transfer to the bank account shown above, then mark paid with the transfer reference.
            </div>
        @endif
    </div>
@endif
