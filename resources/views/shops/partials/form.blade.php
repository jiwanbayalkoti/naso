@php
    $shop = $shop ?? null;
@endphp

<form id="shop-form" method="POST" action="{{ route('shops.store') }}" novalidate>
    @csrf
    <input type="hidden" name="id" id="shop-id" value="">

    <div class="owner-fields" id="shop-owner-fields">
        <p class="text-muted small mb-3"><i class="fa-solid fa-user me-1"></i> Shop owner account (create only)</p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="owner_name" class="form-label">Owner Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="owner_name" id="owner_name" placeholder="Full name">
                <div class="invalid-feedback d-block" data-field="owner_name"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="owner_email" class="form-label">Owner Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="owner_email" id="owner_email" placeholder="owner@example.com">
                <div class="invalid-feedback d-block" data-field="owner_email"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="owner_phone" class="form-label">Owner Phone</label>
                <input type="text" class="form-control" name="owner_phone" id="owner_phone" placeholder="+1 234 567 8900">
                <div class="invalid-feedback d-block" data-field="owner_phone"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="owner_password" class="form-label">Owner Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="owner_password" id="owner_password" placeholder="Min. 8 characters">
                <div class="invalid-feedback d-block" data-field="owner_password"></div>
            </div>
        </div>
        <hr class="my-3">
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="shop_name" class="form-label">Shop Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="shop_name" placeholder="Shop name" required>
            <div class="invalid-feedback d-block" data-field="name"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="shop_email" class="form-label">Shop Email</label>
            <input type="email" class="form-control" name="email" id="shop_email" placeholder="shop@example.com">
            <div class="invalid-feedback d-block" data-field="email"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="shop_phone" class="form-label">Phone</label>
            <input type="text" class="form-control" name="phone" id="shop_phone" placeholder="+1 234 567 8900">
            <div class="invalid-feedback d-block" data-field="phone"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="shop_city" class="form-label">City</label>
            <input type="text" class="form-control" name="city" id="shop_city" placeholder="City" list="shop-cities-list">
            <datalist id="shop-cities-list">
                @foreach($cities ?? [] as $city)
                    <option value="{{ $city }}">
                @endforeach
            </datalist>
            <div class="invalid-feedback d-block" data-field="city"></div>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label for="shop_address" class="form-label mb-0">Address</label>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary btn-use-current-location" data-target="shop">
                    <i class="fa-solid fa-location-crosshairs me-1"></i> Current
                </button>
                <button type="button" class="btn btn-outline-secondary btn-pick-on-map" data-target="shop">
                    <i class="fa-solid fa-map-pin me-1"></i> Map
                </button>
            </div>
        </div>
        <textarea class="form-control" name="address" id="shop_address" rows="2" placeholder="Street address"></textarea>
        <input type="hidden" name="latitude" id="shop_latitude">
        <input type="hidden" name="longitude" id="shop_longitude">
        <div class="invalid-feedback d-block" data-field="address"></div>
    </div>

    <div class="mb-3">
        <label for="shop_description" class="form-label">Description</label>
        <textarea class="form-control" name="description" id="shop_description" rows="3" placeholder="Brief description"></textarea>
        <div class="invalid-feedback d-block" data-field="description"></div>
    </div>

    <hr class="my-3">
    <p class="text-muted small mb-3"><i class="fa-solid fa-building-columns me-1"></i> Bank details (for payout transfer)</p>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="shop_bank_name" class="form-label">Bank name</label>
            <input type="text" class="form-control" name="bank_name" id="shop_bank_name" placeholder="e.g. Nabil Bank">
            <div class="invalid-feedback d-block" data-field="bank_name"></div>
        </div>
        <div class="col-md-4 mb-3">
            <label for="shop_bank_account_name" class="form-label">Account name</label>
            <input type="text" class="form-control" name="bank_account_name" id="shop_bank_account_name" placeholder="Account holder">
            <div class="invalid-feedback d-block" data-field="bank_account_name"></div>
        </div>
        <div class="col-md-4 mb-3">
            <label for="shop_bank_account_number" class="form-label">Account number</label>
            <input type="text" class="form-control" name="bank_account_number" id="shop_bank_account_number" placeholder="Account number">
            <div class="invalid-feedback d-block" data-field="bank_account_number"></div>
        </div>
    </div>

    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="shop_is_active" value="1" checked>
        <label class="form-check-label" for="shop_is_active">Active</label>
    </div>
</form>
