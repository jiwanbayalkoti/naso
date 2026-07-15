@php
    use App\Helpers\DeliveryStatus;

    $actionLabels = DeliveryStatus::riderActionLabels();
@endphp

@props([
    'id',
    'trackingNumber' => '',
    'status' => 'pending',
    'allowedStatuses' => [],
])

<div class="d-flex align-items-center justify-content-end gap-1 flex-wrap">
    <button type="button"
            class="btn btn-sm btn-outline-secondary btn-view"
            data-id="{{ $id }}"
            data-tracking="{{ $trackingNumber }}"
            title="View details">
        <i class="fa-solid fa-eye"></i>
    </button>

    @if($status === DeliveryStatus::ASSIGNED)
        <button type="button"
                class="btn btn-sm btn-success btn-rider-accept"
                data-id="{{ $id }}"
                data-tracking="{{ $trackingNumber }}"
                title="Accept delivery">
            <i class="fa-solid fa-check me-1"></i> Accept
        </button>
        <button type="button"
                class="btn btn-sm btn-outline-danger btn-rider-reject"
                data-id="{{ $id }}"
                data-tracking="{{ $trackingNumber }}"
                title="Reject assignment">
            <i class="fa-solid fa-xmark me-1"></i> Reject
        </button>
    @endif

    @foreach($allowedStatuses as $nextStatus)
        @continue($status === DeliveryStatus::ASSIGNED)
        <button type="button"
                class="btn btn-sm btn-primary btn-rider-status"
                data-id="{{ $id }}"
                data-status="{{ $nextStatus }}"
                data-tracking="{{ $trackingNumber }}"
                title="{{ $actionLabels[$nextStatus] ?? ucfirst(str_replace('_', ' ', $nextStatus)) }}">
            {{ $actionLabels[$nextStatus] ?? ucfirst(str_replace('_', ' ', $nextStatus)) }}
        </button>
    @endforeach
</div>
