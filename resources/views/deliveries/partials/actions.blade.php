@php
    use App\Helpers\DeliveryStatus;
@endphp

@props([
    'id',
    'trackingNumber' => '',
    'status' => 'pending',
    'canAssign' => true,
    'canEdit' => true,
    'canDelete' => true,
])

@php
    $statusLabels = DeliveryStatus::labels();
    $statusClass = str_replace('_', '-', $status);
@endphp

<div class="d-flex align-items-center justify-content-end gap-1 flex-wrap">
    <button type="button"
            class="btn btn-sm btn-outline-secondary btn-view"
            data-id="{{ $id }}"
            data-tracking="{{ $trackingNumber }}"
            title="View details">
        <i class="fa-solid fa-eye"></i>
    </button>

    @if($canEdit)
        <button type="button"
                class="btn btn-sm btn-outline-primary btn-edit"
                data-id="{{ $id }}"
                data-tracking="{{ $trackingNumber }}"
                title="Edit">
            <i class="fa-solid fa-pen"></i>
        </button>
    @endif

    @if($canAssign)
        <button type="button"
                class="btn btn-sm btn-outline-info btn-assign"
                data-id="{{ $id }}"
                data-tracking="{{ $trackingNumber }}"
                title="Assign rider">
            <i class="fa-solid fa-motorcycle"></i>
        </button>
    @endif

    <div class="dropdown d-inline-block">
        <button class="btn btn-sm btn-outline-warning dropdown-toggle btn-status"
                type="button"
                data-bs-toggle="dropdown"
                data-id="{{ $id }}"
                data-status="{{ $status }}"
                aria-expanded="false"
                title="Update status">
            <i class="fa-solid fa-arrows-rotate"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            @foreach($statusLabels as $value => $label)
                <li>
                    <button type="button"
                            class="dropdown-item btn-status-option {{ $status === $value ? 'active' : '' }}"
                            data-id="{{ $id }}"
                            data-status="{{ $value }}">
                        <span class="badge-status {{ str_replace('_', '-', $value) }}">{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>

    @if($canDelete)
        <button type="button"
                class="btn btn-sm btn-outline-danger btn-delete"
                data-id="{{ $id }}"
                data-tracking="{{ $trackingNumber }}"
                title="Delete">
            <i class="fa-solid fa-trash"></i>
        </button>
    @endif
</div>
