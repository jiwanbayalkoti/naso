@php
    use App\Helpers\DeliveryStatus;

    $isShopUser = auth()->user()?->hasRole('shop') && ($currentShop ?? auth()->user()?->shop);
    $shopForForm = $currentShop ?? auth()->user()?->shop;
@endphp

<form id="delivery-form" method="POST" action="{{ route('deliveries.store') }}" novalidate>
    @csrf
    <input type="hidden" name="id" id="delivery-id" value="">

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="delivery_shop_id" class="form-label">Shop @unless($isShopUser)<span class="text-danger">*</span>@endunless</label>
            @if($isShopUser && $shopForForm)
                <input type="hidden" name="shop_id" id="delivery_shop_id" value="{{ $shopForForm->id }}"
                       data-default-shop-id="{{ $shopForForm->id }}"
                       data-default-pickup-address="{{ $shopForForm->address }}">
                <input type="text" class="form-control" value="{{ $shopForForm->name }}" readonly>
            @else
                <select class="form-select select2" name="shop_id" id="delivery_shop_id" data-placeholder="Select shop" required>
                    <option value=""></option>
                    @foreach($shops ?? [] as $shop)
                        <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                    @endforeach
                </select>
            @endif
            <div class="invalid-feedback d-block" data-field="shop_id"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="delivery_priority" class="form-label">Priority</label>
            <select class="form-select" name="priority" id="delivery_priority">
                <option value="low">Low</option>
                <option value="normal" selected>Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
            <div class="invalid-feedback d-block" data-field="priority"></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="customer_name" id="customer_name" placeholder="Customer name" required>
            <div class="invalid-feedback d-block" data-field="customer_name"></div>
        </div>
        <div class="col-md-6 mb-3">
            <label for="customer_phone" class="form-label">Customer Phone <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="customer_phone" id="customer_phone" placeholder="+1 234 567 8900" required>
            <div class="invalid-feedback d-block" data-field="customer_phone"></div>
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label for="pickup_address" class="form-label mb-0">Pickup Address <span class="text-danger">*</span></label>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary btn-use-current-location" data-target="pickup">
                    <i class="fa-solid fa-location-crosshairs me-1"></i> Current
                </button>
                <button type="button" class="btn btn-outline-secondary btn-pick-on-map" data-target="pickup">
                    <i class="fa-solid fa-map-pin me-1"></i> Map
                </button>
            </div>
        </div>
        <textarea class="form-control" name="pickup_address" id="pickup_address" rows="2" placeholder="Pickup location" required></textarea>
        <div class="invalid-feedback d-block" data-field="pickup_address"></div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label for="delivery_address" class="form-label mb-0">Delivery Address <span class="text-danger">*</span></label>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary btn-use-current-location" data-target="delivery">
                    <i class="fa-solid fa-location-crosshairs me-1"></i> Current
                </button>
                <button type="button" class="btn btn-outline-secondary btn-pick-on-map" data-target="delivery">
                    <i class="fa-solid fa-map-pin me-1"></i> Map
                </button>
            </div>
        </div>
        <textarea class="form-control" name="delivery_address" id="delivery_address" rows="2" placeholder="Delivery destination" required></textarea>
        <input type="hidden" name="latitude" id="delivery_latitude">
        <input type="hidden" name="longitude" id="delivery_longitude">
        <div class="invalid-feedback d-block" data-field="delivery_address"></div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-3">
            <label for="cod_amount" class="form-label">COD Amount (goods)</label>
            <div class="input-group">
                <span class="input-group-text">Rs</span>
                <input type="number" step="0.01" min="0" class="form-control" name="cod_amount" id="cod_amount" placeholder="0.00" value="0">
            </div>
            <div class="form-text">Cash rider collects from customer.</div>
            <div class="invalid-feedback d-block" data-field="cod_amount"></div>
        </div>
        <div class="col-md-3 mb-3">
            <label for="delivery_fee" class="form-label">Delivery Fee</label>
            <div class="input-group">
                <span class="input-group-text">Rs</span>
                <input type="number" step="0.01" min="0" class="form-control" name="delivery_fee" id="delivery_fee"
                       placeholder="Auto" value="" @unless(auth()->user()?->hasRole('super_admin')) readonly @endunless>
            </div>
            <div class="form-text"><span id="fee-distance-hint">Calculated from distance</span></div>
            <div class="invalid-feedback d-block" data-field="delivery_fee"></div>
        </div>
        <div class="col-md-3 mb-3">
            <label for="payment_method" class="form-label">Payment Method</label>
            <select class="form-select" name="payment_method" id="payment_method">
                <option value="cod" selected>Cash on Delivery</option>
                <option value="cash">Cash (no goods COD)</option>
                <option value="online">Online</option>
                <option value="card">Card</option>
            </select>
            <div class="invalid-feedback d-block" data-field="payment_method"></div>
        </div>
        <div class="col-md-3 mb-3">
            <label for="payment_status" class="form-label">Payment Status</label>
            <select class="form-select" name="payment_status" id="payment_status">
                <option value="pending">Pending</option>
                <option value="paid">Paid</option>
                <option value="failed">Failed</option>
                <option value="refunded">Refunded</option>
            </select>
            <div class="invalid-feedback d-block" data-field="payment_status"></div>
        </div>
    </div>

    <div class="mb-3">
        <label for="delivery_notes" class="form-label">Notes</label>
        <textarea class="form-control" name="notes" id="delivery_notes" rows="2" placeholder="Special instructions"></textarea>
        <div class="invalid-feedback d-block" data-field="notes"></div>
    </div>
</form>
