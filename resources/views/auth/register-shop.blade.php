@extends('layouts.guest')

@section('title', 'Shop Registration - ' . config('app.name'))
@section('subtitle', 'Register your shop and start delivering')

@section('content')
    <form id="register-shop-form" action="{{ route('register.shop') }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf

        <p class="text-muted small mb-3"><i class="fa-solid fa-user me-1"></i> Owner account</p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="owner_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required>
                <div class="invalid-feedback d-block" data-error="owner_name"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="owner_email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="owner_email" name="owner_email" value="{{ old('owner_email') }}" required>
                <div class="invalid-feedback d-block" data-error="owner_email"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="owner_phone" class="form-label">Phone</label>
                <input type="text" class="form-control" id="owner_phone" name="owner_phone" value="{{ old('owner_phone') }}">
                <div class="invalid-feedback d-block" data-error="owner_phone"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="owner_password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="owner_password" name="owner_password" required>
                <div class="invalid-feedback d-block" data-error="owner_password"></div>
            </div>
        </div>
        <div class="mb-3">
            <label for="owner_password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" class="form-control" id="owner_password_confirmation" name="owner_password_confirmation" required>
            <div class="invalid-feedback d-block" data-error="owner_password_confirmation"></div>
        </div>

        <hr class="my-3">

        <p class="text-muted small mb-3"><i class="fa-solid fa-store me-1"></i> Shop details</p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="name" class="form-label">Shop Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                <div class="invalid-feedback d-block" data-error="name"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="shop_email" class="form-label">Shop Email</label>
                <input type="email" class="form-control" id="shop_email" name="email" value="{{ old('email') }}">
                <div class="invalid-feedback d-block" data-error="email"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="phone" class="form-label">Shop Phone</label>
                <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}">
                <div class="invalid-feedback d-block" data-error="phone"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="city" class="form-label">City</label>
                <input type="text" class="form-control" id="city" name="city" value="{{ old('city') }}">
                <div class="invalid-feedback d-block" data-error="city"></div>
            </div>
        </div>
        <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <textarea class="form-control" id="address" name="address" rows="2">{{ old('address') }}</textarea>
            <div class="invalid-feedback d-block" data-error="address"></div>
        </div>
        <div class="mb-4">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="2">{{ old('description') }}</textarea>
            <div class="invalid-feedback d-block" data-error="description"></div>
        </div>

        <hr class="my-3">

        <p class="text-muted small mb-3"><i class="fa-solid fa-file-circle-check me-1"></i> Verification documents</p>
        <p class="text-muted small">Upload clear photos or PDF scans. Max 5MB each (JPG, PNG, PDF).</p>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="document_pan" class="form-label">PAN Card <span class="text-danger">*</span></label>
                <input type="file" class="form-control" id="document_pan" name="documents[pan]" accept=".jpg,.jpeg,.png,.pdf" required>
                <div class="invalid-feedback d-block" data-error="documents.pan"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="document_nid" class="form-label">National ID (NID) <span class="text-muted">(Optional)</span></label>
                <input type="file" class="form-control" id="document_nid" name="documents[nid]" accept=".jpg,.jpeg,.png,.pdf">
                <div class="invalid-feedback d-block" data-error="documents.nid"></div>
            </div>
        </div>
        <div class="mb-4">
            <label for="document_citizenship" class="form-label">Citizenship <span class="text-danger">*</span></label>
            <input type="file" class="form-control" id="document_citizenship" name="documents[citizenship]" accept=".jpg,.jpeg,.png,.pdf" required>
            <div class="invalid-feedback d-block" data-error="documents.citizenship"></div>
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-3" id="register-submit">
            <span class="btn-text"><i class="fa-solid fa-store me-2"></i>Register Shop</span>
            <span class="btn-spinner d-none">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>Registering...
            </span>
        </button>

        <p class="text-center text-muted small mb-0">
            Already have an account?
            <a href="{{ route('login') }}">Sign in</a>
            &nbsp;|&nbsp;
            <a href="{{ route('register.rider') }}">Register as Rider</a>
        </p>
    </form>
@endsection

@push('styles')
    <style>
        .guest-card { max-width: 820px; }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('js/core/form-helper.js') }}"></script>
    <script src="{{ asset('js/auth/register.js') }}"></script>
@endpush
