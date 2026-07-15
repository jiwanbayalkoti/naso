@extends('layouts.app')

@section('title', 'My Profile - ' . config('app.name'))
@section('page-title', 'My Profile')

@section('content')
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="profile-avatar-preview" id="profile-avatar-preview">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="Profile photo" class="profile-avatar-image">
                            @else
                                <span class="profile-avatar-initial">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</span>
                            @endif
                        </div>
                        <div>
                            <label class="form-label mb-1">Profile photo</label>
                            <input type="file" class="form-control form-control-sm" id="profile-avatar-input"
                                   accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="form-text">JPG, PNG, GIF, or WEBP up to 2 MB.</div>
                            <div class="invalid-feedback d-block" data-error="avatar"></div>
                        </div>
                    </div>

                    <form id="profile-form"
                          data-update-url="{{ route('profile.update') }}"
                          data-password-url="{{ route('profile.password') }}"
                          data-avatar-url="{{ route('profile.avatar') }}">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="profile-name">Full name</label>
                                <input type="text" class="form-control" id="profile-name" name="name"
                                       value="{{ old('name', $user->name) }}" required>
                                <div class="invalid-feedback" data-error="name"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile-email">Email</label>
                                <input type="email" class="form-control" id="profile-email" name="email"
                                       value="{{ old('email', $user->email) }}" required>
                                <div class="invalid-feedback" data-error="email"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="profile-phone">Phone</label>
                                <input type="text" class="form-control" id="profile-phone" name="phone"
                                       value="{{ old('phone', $user->phone) }}">
                                <div class="invalid-feedback" data-error="phone"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-control" value="{{ $user->roles->first()?->name ?? '-' }}" disabled>
                            </div>
                            @if($shop)
                                <div class="col-12">
                                    <label class="form-label">Linked shop</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control" value="{{ $shop->name }}" disabled>
                                        <a href="{{ route('shops.index') }}" class="btn btn-outline-primary btn-sm">Manage shop</a>
                                    </div>
                                </div>
                            @endif
                            @if($rider)
                                <div class="col-12">
                                    <label class="form-label">Rider profile</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="text" class="form-control"
                                               value="{{ $rider->vehicle_type ?? 'Rider' }} · {{ $rider->vehicle_number ?? 'No vehicle' }}" disabled>
                                        <a href="{{ route('riders.index') }}" class="btn btn-outline-primary btn-sm">Manage rider</a>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @if($rider && auth()->user()->hasRole('rider'))
                @php $riderOnline = (bool) $rider->is_online; @endphp
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Work status</h5>
                    </div>
                    <div class="card-body">
                        <div class="rider-online-toggle"
                             id="rider-online-toggle-profile"
                             data-toggle-url="{{ route('riders.toggle-online', ['rider' => $rider->uuid]) }}"
                             data-online="{{ $riderOnline ? '1' : '0' }}">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <button type="button"
                                                class="btn btn-sm rider-online-btn {{ $riderOnline ? 'is-online' : 'is-offline' }}">
                                            <span class="rider-online-dot" aria-hidden="true"></span>
                                            <span class="rider-online-label">{{ $riderOnline ? 'Online' : 'Offline' }}</span>
                                        </button>
                                    </div>
                                    <div class="fw-semibold rider-online-status-text">
                                        {{ $riderOnline ? 'Preferred Online — live while logged in' : 'Offline — turn Online when you want offers' }}
                                    </div>
                                    <div class="text-muted small mt-1">
                                        Manual Offline stays Off after login. Logout only pauses live presence.
                                    </div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           role="switch"
                                           id="rider-online-switch"
                                           {{ $riderOnline ? 'checked' : '' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form id="password-form">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label" for="current-password">Current password</label>
                            <input type="password" class="form-control" id="current-password" name="current_password" required>
                            <div class="invalid-feedback" data-error="current_password"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new-password">New password</label>
                            <input type="password" class="form-control" id="new-password" name="password" required>
                            <div class="invalid-feedback" data-error="password"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="confirm-password">Confirm new password</label>
                            <input type="password" class="form-control" id="confirm-password" name="password_confirmation" required>
                        </div>

                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fa-solid fa-key me-1"></i> Update password
                        </button>
                    </form>
                </div>
            </div>

            @if($shop || $rider)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bank details</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Admin transfers payouts to this account.</p>
                        <form id="bank-details-form" data-update-url="{{ route('profile.bank-details') }}">
                            @csrf
                            @method('PUT')
                            @php
                                $bankSource = $shop ?? $rider;
                            @endphp
                            <div class="mb-3">
                                <label class="form-label" for="bank-name">Bank name</label>
                                <input type="text" class="form-control" id="bank-name" name="bank_name"
                                       value="{{ old('bank_name', $bankSource->bank_name) }}" placeholder="e.g. Nabil Bank">
                                <div class="invalid-feedback" data-error="bank_name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="bank-account-name">Account name</label>
                                <input type="text" class="form-control" id="bank-account-name" name="bank_account_name"
                                       value="{{ old('bank_account_name', $bankSource->bank_account_name) }}" placeholder="Account holder">
                                <div class="invalid-feedback" data-error="bank_account_name"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="bank-account-number">Account number</label>
                                <input type="text" class="form-control" id="bank-account-number" name="bank_account_number"
                                       value="{{ old('bank_account_number', $bankSource->bank_account_number) }}" placeholder="Account number">
                                <div class="invalid-feedback" data-error="bank_account_number"></div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fa-solid fa-building-columns me-1"></i> Save bank details
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if(auth()->user()->hasRole('super_admin'))
                <div class="card mt-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">System settings</h6>
                            <p class="text-muted small mb-0">Manage app name, support contacts, and registration options.</p>
                        </div>
                        <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary btn-sm">Open settings</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/modules/profile/index.js') }}"></script>
@endpush
