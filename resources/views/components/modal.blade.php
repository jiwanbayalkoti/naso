@props([
    'id',
    'title' => '',
    'size' => '',
    'centered' => true,
    'scrollable' => false,
    'static' => false,
    'showFooter' => true,
])

@php
    $dialogClasses = collect([
        'modal-dialog',
        $size ? 'modal-' . $size : '',
        $centered ? 'modal-dialog-centered' : '',
        $scrollable ? 'modal-dialog-scrollable' : '',
    ])->filter()->implode(' ');
@endphp

<div
    class="modal fade"
    id="{{ $id }}"
    tabindex="-1"
    aria-labelledby="{{ $id }}-label"
    aria-hidden="true"
    @if($static) data-bs-backdrop="static" data-bs-keyboard="false" @endif
    {{ $attributes }}
>
    <div class="{{ $dialogClasses }}">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}-label">{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                {{ $body ?? $slot }}
            </div>

            @if($showFooter)
                <div class="modal-footer">
                    @isset($footer)
                        {{ $footer }}
                    @else
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary modal-submit-btn">Save</button>
                    @endisset
                </div>
            @endif
        </div>
    </div>
</div>
