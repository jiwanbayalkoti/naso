<nav class="sidebar-nav">
    @forelse($sidebarMenus ?? [] as $menu)
        @continue($menu->route_name === 'riders.live-map')
        <a href="{{ $menu->resolved_url }}"
           class="sidebar-link {{ $menu->isActiveRoute() ? 'active' : '' }}">
            @if($menu->icon)
                <i class="{{ $menu->icon }}"></i>
            @endif
            <span>{{ $menu->title }}</span>
        </a>
    @empty
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Dashboard</span>
        </a>
    @endforelse

    @if(auth()->user()->hasAnyRole(['super_admin', 'shop']))
        <a href="{{ route('riders.live-map') }}"
           class="sidebar-link {{ request()->routeIs('riders.live-map') ? 'active' : '' }}">
            <i class="fa-solid fa-location-dot"></i>
            <span>Live Riders</span>
        </a>
    @endif
</nav>
