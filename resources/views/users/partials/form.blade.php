<form id="user-form" method="POST" action="{{ route('users.store') }}" novalidate>
    @csrf
    <input type="hidden" name="id" id="user-id" value="">

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="user_name" class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="user_name" placeholder="Full name" required>
            <div class="invalid-feedback d-block" data-field="name"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="user_email" class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" class="form-control" name="email" id="user_email" placeholder="user@example.com" required>
            <div class="invalid-feedback d-block" data-field="email"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="user_phone" class="form-label">Phone</label>
            <input type="text" class="form-control" name="phone" id="user_phone" placeholder="+1 234 567 8900">
            <div class="invalid-feedback d-block" data-field="phone"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="user_role" class="form-label">Role <span class="text-danger">*</span></label>
            <select class="form-select select2" name="role" id="user_role" data-placeholder="Select role" required>
                <option value=""></option>
                @foreach($roles ?? ['admin', 'manager', 'shop', 'rider'] as $role)
                    <option value="{{ is_object($role) ? $role->name : $role }}">
                        {{ ucfirst(is_object($role) ? $role->name : $role) }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-field="role"></div>
        </div>
    </div>

    <div class="password-fields" id="user-password-fields">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="user_password" class="form-label">Password <span class="text-danger create-required">*</span></label>
                <input type="password" class="form-control" name="password" id="user_password" placeholder="Min. 8 characters">
                <div class="invalid-feedback d-block" data-field="password"></div>
            </div>
            <div class="col-md-6 mb-3">
                <label for="user_password_confirmation" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" name="password_confirmation" id="user_password_confirmation" placeholder="Repeat password">
            </div>
        </div>
    </div>

    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="user_is_active" value="1" checked>
        <label class="form-check-label" for="user_is_active">Active</label>
    </div>
</form>
