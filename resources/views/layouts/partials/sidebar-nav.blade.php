<nav class="sidebar-nav">
    @forelse($sidebarMenus ?? [] as $menu)
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
</nav>
