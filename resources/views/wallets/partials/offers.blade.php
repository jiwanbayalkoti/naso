@php
    /** @var array $offers */
    $offers = $offers ?? [];
@endphp
@if(count($offers))
    <div class="card mb-4">
        <div class="card-header"><strong>Active offers</strong></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($offers as $offer)
                    @php
                        $target = max(1, (int) ($offer['target'] ?? 1));
                        $current = (int) ($offer['current_count'] ?? 0);
                        $pct = min(100, round(($current / $target) * 100));
                        $next = $offer['next_reward_in'] ?? null;
                    @endphp
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold">{{ $offer['name'] }}</div>
                            <div class="small text-muted mb-2">{{ $offer['description'] ?: ($offer['type_label'] ?? '') }}</div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%"></div>
                            </div>
                            <div class="small">
                                Progress: {{ $current }}@if($target)/{{ $target }}@endif
                                @if($next !== null && $next > 0)
                                    · {{ $next }} more to reward
                                @elseif(!empty($offer['unlocked']))
                                    · Unlocked
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
