<form id="menu-form" method="POST" action="{{ route('menus.store') }}" novalidate>
    @csrf
    <input type="hidden" name="id" id="menu-id" value="">

    <div class="row">
        <div class="col-md-8 mb-3">
            <label for="menu_title" class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="title" id="menu_title" placeholder="Menu title" required>
            <div class="invalid-feedback d-block" data-field="title"></div>
        </div>
        <div class="col-md-4 mb-3">
            <label for="menu_sort_order" class="form-label">Sort Order</label>
            <input type="number" class="form-control" name="sort_order" id="menu_sort_order" min="0" value="0">
            <div class="invalid-feedback d-block" data-field="sort_order"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="menu_icon" class="form-label">Icon Class</label>
            <input type="text" class="form-control" name="icon" id="menu_icon" placeholder="fa-solid fa-home">
            <div class="form-text">Font Awesome class, e.g. <code>fa-solid fa-store</code></div>
            <div class="invalid-feedback d-block" data-field="icon"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="menu_parent_id" class="form-label">Parent Menu</label>
            <select class="form-select select2" name="parent_id" id="menu_parent_id" data-placeholder="None (top level)">
                <option value=""></option>
                @foreach($parentMenus ?? [] as $parent)
                    <option value="{{ $parent->id }}">{{ $parent->title }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-field="parent_id"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="menu_route_name" class="form-label">Route Name</label>
            <select class="form-select select2" name="route_name" id="menu_route_name" data-placeholder="Select route">
                <option value=""></option>
                @foreach($routeNames ?? [] as $routeName)
                    <option value="{{ $routeName }}">{{ $routeName }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-field="route_name"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="menu_route_pattern" class="form-label">Active Route Pattern</label>
            <input type="text" class="form-control" name="route_pattern" id="menu_route_pattern" placeholder="shops.*">
            <div class="form-text">Used to highlight active menu item</div>
            <div class="invalid-feedback d-block" data-field="route_pattern"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="menu_url" class="form-label">Custom URL</label>
            <input type="text" class="form-control" name="url" id="menu_url" placeholder="https://example.com">
            <div class="form-text">Optional fallback if route name is empty</div>
            <div class="invalid-feedback d-block" data-field="url"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="menu_permission" class="form-label">Permission</label>
            <select class="form-select select2" name="permission" id="menu_permission" data-placeholder="Visible to all">
                <option value=""></option>
                @foreach($permissions ?? [] as $permission)
                    <option value="{{ $permission }}">{{ $permission }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback d-block" data-field="permission"></div>
        </div>
    </div>

    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="menu_is_active" value="1" checked>
        <label class="form-check-label" for="menu_is_active">Active</label>
    </div>
</form>
