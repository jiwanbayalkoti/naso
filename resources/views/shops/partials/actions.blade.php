@props([
    'id',
    'name' => 'shop',
])

<div class="btn-group btn-group-sm" role="group" aria-label="Shop actions">
    <button type="button"
            class="btn btn-outline-primary btn-edit"
            data-id="{{ $id }}"
            data-name="{{ $name }}"
            title="Edit">
        <i class="fa-solid fa-pen"></i>
    </button>
    <button type="button"
            class="btn btn-outline-danger btn-delete"
            data-id="{{ $id }}"
            data-name="{{ $name }}"
            title="Delete">
        <i class="fa-solid fa-trash"></i>
    </button>
</div>
