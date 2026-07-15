@props([
    'type' => 'danger',
    'dismissible' => true,
    'message' => null,
])

@php
    $sessionKey = match ($type) {
        'success' => 'success',
        'warning' => 'warning',
        'info' => 'info',
        default => 'error',
    };

    $displayMessage = $message ?? session($sessionKey) ?? session('status');
@endphp

@if($displayMessage)
    <div {{ $attributes->merge(['class' => 'alert alert-' . $type . ($dismissible ? ' alert-dismissible fade show' : '') . ' mb-4', 'role' => 'alert']) }}>
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        {{ $displayMessage }}
        @if($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        @endif
    </div>
@endif

@if($errors->any() && !$displayMessage)
    <div {{ $attributes->merge(['class' => 'alert alert-danger alert-dismissible fade show mb-4', 'role' => 'alert']) }}>
        <i class="fa-solid fa-circle-exclamation me-2"></i>
        <strong>Please fix the following errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
