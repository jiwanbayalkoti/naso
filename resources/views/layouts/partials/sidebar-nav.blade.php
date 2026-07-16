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

    @if(auth()->user()->hasRole('shop'))
        <a href="{{ route('wallet.shop') }}"
           class="sidebar-link {{ request()->routeIs('wallet.shop') ? 'active' : '' }}">
            <i class="fa-solid fa-wallet"></i>
            <span>My Wallet</span>
        </a>
    @endif

    @if(auth()->user()->hasRole('rider'))
        <a href="{{ route('wallet.rider') }}"
           class="sidebar-link {{ request()->routeIs('wallet.rider') ? 'active' : '' }}">
            <i class="fa-solid fa-wallet"></i>
            <span>My Earnings</span>
        </a>
    @endif

    @if(auth()->user()->hasRole('super_admin'))
        <a href="{{ route('offers.index') }}"
           class="sidebar-link {{ request()->routeIs('offers.*') ? 'active' : '' }}">
            <i class="fa-solid fa-tags"></i>
            <span>Offers</span>
        </a>
        <a href="{{ route('payouts.index') }}"
           class="sidebar-link {{ request()->routeIs('payouts.*') ? 'active' : '' }}">
            <i class="fa-solid fa-money-bill-transfer"></i>
            <span>Payouts</span>
        </a>
    @endif
</nav>
