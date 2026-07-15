@props([
    'id',
    'type',
    'name' => 'request',
])

<div class="btn-group btn-group-sm" role="group" aria-label="Registration request actions">
    <button type="button"
            class="btn btn-outline-primary btn-review"
            data-id="{{ $id }}"
            data-type="{{ $type }}"
            data-name="{{ $name }}"
            title="Review">
        <i class="fa-solid fa-eye"></i>
    </button>
    <button type="button"
            class="btn btn-outline-success btn-approve"
            data-id="{{ $id }}"
            data-type="{{ $type }}"
            data-name="{{ $name }}"
            title="Approve">
        <i class="fa-solid fa-check"></i>
    </button>
    <button type="button"
            class="btn btn-outline-danger btn-reject"
            data-id="{{ $id }}"
            data-type="{{ $type }}"
            data-name="{{ $name }}"
            title="Reject">
        <i class="fa-solid fa-xmark"></i>
    </button>
</div>
