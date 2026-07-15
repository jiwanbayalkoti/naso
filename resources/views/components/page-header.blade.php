@props([
    'title',
    'subtitle' => null,
    'breadcrumb' => [],
])

<div {{ $attributes->merge(['class' => 'page-header mb-4']) }}>
    <div class="row align-items-center g-2">
        <div class="col-12 col-md">
            @if(!empty($breadcrumb))
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        @foreach($breadcrumb as $item)
                            @if($loop->last)
                                <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] ?? $item }}</li>
                            @else
                                <li class="breadcrumb-item">
                                    <a href="{{ $item['url'] ?? '#' }}">{{ $item['label'] ?? $item }}</a>
                                </li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif

            <h2 class="page-header-title mb-0">{{ $title }}</h2>
            @if($subtitle)
                <p class="page-header-subtitle text-muted mb-0 mt-1">{{ $subtitle }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="col-12 col-md-auto">
                <div class="page-header-actions d-flex gap-2 flex-wrap justify-content-md-end">
                    {{ $actions }}
                </div>
            </div>
        @endif
    </div>
</div>
