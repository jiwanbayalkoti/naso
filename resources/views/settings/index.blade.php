@extends('layouts.app')

@section('title', 'System Settings - ' . config('app.name'))
@section('page-title', 'System Settings')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Application Settings</h5>
                    <p class="text-muted small mb-0 mt-1">Super admin only. Changes apply to web and mobile apps.</p>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="settings-logo-preview" id="settings-logo-preview">
                            @if(!empty($settings['app_logo_url']))
                                <img src="{{ $settings['app_logo_url'] }}" alt="App logo" class="settings-logo-image">
                            @else
                                <span class="settings-logo-placeholder"><i class="fa-solid fa-truck-fast"></i></span>
                            @endif
                        </div>
                        <div>
                            <label class="form-label mb-1" for="settings-logo-input">App logo</label>
                            <input type="file" class="form-control form-control-sm" id="settings-logo-input"
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">Shown in sidebar and login screens. Max 2 MB.</div>
                            <div class="invalid-feedback d-block" data-error="logo"></div>
                        </div>
                    </div>

                    <form id="settings-form"
                          data-update-url="{{ route('settings.update') }}"
                          data-logo-url="{{ route('settings.logo') }}">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="settings-app-name">App name</label>
                                <input type="text" class="form-control" id="settings-app-name" name="app_name"
                                       value="{{ $settings['app_name'] ?? config('app.name') }}" required>
                                <div class="invalid-feedback" data-error="app_name"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="settings-refresh">Dashboard refresh (seconds)</label>
                                <input type="number" class="form-control" id="settings-refresh" name="dashboard_refresh_interval"
                                       min="5" max="300"
                                       value="{{ $settings['dashboard_refresh_interval'] ?? 30 }}" required>
                                <div class="invalid-feedback" data-error="dashboard_refresh_interval"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="settings-offer-timeout">Delivery offer timeout (minutes)</label>
                                <input type="number" class="form-control" id="settings-offer-timeout" name="delivery_offer_timeout_minutes"
                                       min="1" max="1440"
                                       value="{{ $settings['delivery_offer_timeout_minutes'] ?? 15 }}" required>
                                <div class="form-text">Unclaimed deliveries auto-cancel after this time. Default: 15 minutes.</div>
                                <div class="invalid-feedback" data-error="delivery_offer_timeout_minutes"></div>
                            </div>

                            @php
                                $pricing = $settings['delivery_pricing'] ?? [];
                                $valley = $pricing['valley'] ?? ['lat' => 27.7172, 'lng' => 85.3240, 'radius_km' => 18];
                                $insideSlabs = $pricing['inside_valley'] ?? [];
                                $outsideSlabs = $pricing['outside_valley'] ?? [];
                                $pricingMode = $pricing['mode'] ?? 'zone_slabs';
                            @endphp

                            <div class="col-12"><hr class="my-2"><h6 class="mb-0">Delivery pricing & commission</h6>
                                <p class="text-muted small mb-0">
                                    Zone slabs: drop location vs valley center+radius → match distance slab → flat fee (floored at minimum).
                                    Rider earning = fee − platform commission %.
                                </p>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="settings-pricing-mode">Pricing mode</label>
                                <select class="form-select" id="settings-pricing-mode" name="delivery_pricing_mode">
                                    <option value="zone_slabs" @selected($pricingMode === 'zone_slabs')>Zone + distance slabs</option>
                                    <option value="linear" @selected($pricingMode === 'linear')>Linear (base + per km)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="settings-min-fee">Minimum fee</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="settings-min-fee" name="delivery_min_fee"
                                       value="{{ $settings['delivery_min_fee'] ?? 50 }}" required>
                                <div class="invalid-feedback" data-error="delivery_min_fee"></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="settings-commission">Platform commission %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control" id="settings-commission" name="platform_commission_percent"
                                       value="{{ $settings['platform_commission_percent'] ?? 20 }}" required>
                                <div class="form-text">Same % for all zones/slabs.</div>
                                <div class="invalid-feedback" data-error="platform_commission_percent"></div>
                            </div>

                            <div class="col-12 pricing-linear-fields {{ $pricingMode === 'linear' ? '' : 'd-none' }}">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="settings-base-fee">Base fee (linear fallback)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="settings-base-fee" name="delivery_base_fee"
                                               value="{{ $settings['delivery_base_fee'] ?? 50 }}" required>
                                        <div class="invalid-feedback" data-error="delivery_base_fee"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="settings-per-km">Fee per km (linear fallback)</label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="settings-per-km" name="delivery_fee_per_km"
                                               value="{{ $settings['delivery_fee_per_km'] ?? 25 }}" required>
                                        <div class="invalid-feedback" data-error="delivery_fee_per_km"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 pricing-zone-fields {{ $pricingMode === 'zone_slabs' ? '' : 'd-none' }}">
                                <div class="border rounded p-3 bg-light-subtle">
                                    <h6 class="mb-2">Kathmandu Valley (center + radius)</h6>
                                    <p class="text-muted small mb-3">Drop point within radius = Inside Valley; otherwise Outside Valley.</p>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label" for="valley-lat">Center latitude</label>
                                            <input type="number" step="0.000001" class="form-control" id="valley-lat"
                                                   value="{{ $valley['lat'] ?? 27.7172 }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="valley-lng">Center longitude</label>
                                            <input type="number" step="0.000001" class="form-control" id="valley-lng"
                                                   value="{{ $valley['lng'] ?? 85.3240 }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label" for="valley-radius">Radius (km)</label>
                                            <input type="number" step="0.1" min="0" class="form-control" id="valley-radius"
                                                   value="{{ $valley['radius_km'] ?? 18 }}">
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                                        <h6 class="mb-0">Inside Valley slabs</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-inside-slab">
                                            <i class="fa-solid fa-plus me-1"></i> Add slab
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0" id="inside-slabs-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:22%">From km</th>
                                                    <th style="width:22%">To km</th>
                                                    <th style="width:28%">Fee (Rs)</th>
                                                    <th style="width:18%"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($insideSlabs as $slab)
                                                    <tr class="pricing-slab-row" data-kind="inside">
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-from" value="{{ $slab['from_km'] }}"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-to" value="{{ $slab['to_km'] }}" placeholder="open"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-fee" value="{{ $slab['fee'] }}"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-slab">Remove</button></td>
                                                    </tr>
                                                @empty
                                                    <tr class="pricing-slab-row" data-kind="inside">
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-from" value="0"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-to" value="10" placeholder="open"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-fee" value="100"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-slab">Remove</button></td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="form-text mt-1">Leave “To km” empty for open-ended (e.g. 20+ km). Ranges are half-open: from ≤ distance &lt; to.</div>

                                    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                                        <h6 class="mb-0">Outside Valley (short / medium / long)</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-outside-slab">
                                            <i class="fa-solid fa-plus me-1"></i> Add category
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0" id="outside-slabs-table">
                                            <thead>
                                                <tr>
                                                    <th style="width:18%">Label</th>
                                                    <th style="width:18%">From km</th>
                                                    <th style="width:18%">To km</th>
                                                    <th style="width:22%">Fee (Rs)</th>
                                                    <th style="width:14%"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($outsideSlabs as $slab)
                                                    <tr class="pricing-slab-row" data-kind="outside">
                                                        <td><input type="text" class="form-control form-control-sm slab-label" value="{{ $slab['label'] ?? '' }}" placeholder="short"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-from" value="{{ $slab['from_km'] }}"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-to" value="{{ $slab['to_km'] }}" placeholder="open"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-fee" value="{{ $slab['fee'] }}"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-slab">Remove</button></td>
                                                    </tr>
                                                @empty
                                                    <tr class="pricing-slab-row" data-kind="outside">
                                                        <td><input type="text" class="form-control form-control-sm slab-label" value="short"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-from" value="0"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-to" value="30" placeholder="open"></td>
                                                        <td><input type="number" step="0.01" min="0" class="form-control form-control-sm slab-fee" value="300"></td>
                                                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-slab">Remove</button></td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="invalid-feedback d-block" data-error="delivery_pricing"></div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="settings-support-email">Support email</label>
                                <input type="email" class="form-control" id="settings-support-email" name="support_email"
                                       value="{{ $settings['support_email'] ?? '' }}">
                                <div class="invalid-feedback" data-error="support_email"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="settings-support-phone">Support phone</label>
                                <input type="text" class="form-control" id="settings-support-phone" name="support_phone"
                                       value="{{ $settings['support_phone'] ?? '' }}">
                                <div class="invalid-feedback" data-error="support_phone"></div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="settings-shop-registration"
                                           name="shop_registration_enabled" value="1"
                                           @checked($settings['shop_registration_enabled'] ?? true)>
                                    <label class="form-check-label" for="settings-shop-registration">Allow shop registration</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="settings-rider-registration"
                                           name="rider_registration_enabled" value="1"
                                           @checked($settings['rider_registration_enabled'] ?? true)>
                                    <label class="form-check-label" for="settings-rider-registration">Allow rider registration</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Google Maps API key</label>
                                <input type="text" class="form-control" value="{{ $settings['google_maps_api_key'] ?? '' }}" disabled>
                                <div class="form-text">Update `GOOGLE_MAPS_API_KEY` in `.env` to change the maps key.</div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save settings
                            </button>
                            <a href="{{ route('profile.index') }}" class="btn btn-outline-secondary">Back to profile</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/settings/index.js') }}"></script>
@endpush
