@extends('layouts.app')

@section('title', 'System Settings - ' . config('app.name'))
@section('page-title', 'System Settings')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-8">
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
