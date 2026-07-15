<form id="rider-form" method="POST" action="{{ route('riders.store') }}" novalidate>
    @csrf
    <input type="hidden" name="id" id="rider-id" value="">

    <div class="owner-fields" id="rider-owner-fields">
        <p class="text-muted small mb-3"><i class="fa-solid fa-user me-1"></i> Rider account (create only)</p>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="rider_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="name" id="rider_name" placeholder="Full name">
                <div class="invalid-feedback d-block" data-field="name"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="rider_email" class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" id="rider_email" placeholder="rider@example.com">
                <div class="invalid-feedback d-block" data-field="email"></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="rider_phone" class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone" id="rider_phone" placeholder="+1 234 567 8900">
                <div class="invalid-feedback d-block" data-field="phone"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="rider_password" class="form-label">Password <span class="text-danger">*</span></label>
                <input type="password" class="form-control" name="password" id="rider_password" placeholder="Min. 8 characters">
                <div class="invalid-feedback d-block" data-field="password"></div>
            </div>
        </div>
        <hr class="my-3">
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="vehicle_type" class="form-label">Vehicle Type</label>
            <select class="form-select" name="vehicle_type" id="vehicle_type">
                <option value="">Select type</option>
                <option value="motorcycle">Motorcycle</option>
                <option value="bicycle">Bicycle</option>
                <option value="car">Car</option>
                <option value="van">Van</option>
                <option value="scooter">Scooter</option>
            </select>
            <div class="invalid-feedback d-block" data-field="vehicle_type"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="vehicle_number" class="form-label">Vehicle Number</label>
            <input type="text" class="form-control" name="vehicle_number" id="vehicle_number" placeholder="Plate / ID number">
            <div class="invalid-feedback d-block" data-field="vehicle_number"></div>
        </div>
    </div>

    <div class="mb-3">
        <label for="license_number" class="form-label">License Number</label>
        <input type="text" class="form-control" name="license_number" id="license_number" placeholder="Driving license number">
        <div class="invalid-feedback d-block" data-field="license_number"></div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-light border small mb-3">
                Online status is controlled by each rider from the web topbar
                (<strong>Online / Offline</strong>) or mobile app. Admins cannot force a rider online here.
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" name="is_available" id="rider_is_available" value="1" checked>
                <label class="form-check-label" for="rider_is_available">Available for deliveries (when online)</label>
            </div>
        </div>
    </div>
</form>
