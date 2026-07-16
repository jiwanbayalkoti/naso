@extends('layouts.app')

@section('title', 'Offers - ' . config('app.name'))
@section('page-title', 'Loyalty & Rider Offers')

@section('content')
    <x-page-header title="Dynamic Offers" subtitle="Loyalty rules for shops and riders. Enable examples or create your own.">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" id="btn-add-offer">
                <i class="fa-solid fa-plus me-1"></i> Add offer
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="offers-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Audience</th>
                        <th>Type</th>
                        <th>Window</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($offers as $offer)
                        <tr data-uuid="{{ $offer->uuid }}">
                            <td>
                                <div class="fw-semibold">{{ $offer->name }}</div>
                                <div class="small text-muted">{{ $offer->description }}</div>
                            </td>
                            <td>{{ ucfirst($offer->audience) }}</td>
                            <td><span class="badge text-bg-light">{{ \App\Helpers\OfferType::label($offer->type) }}</span></td>
                            <td>{{ $offer->window }}</td>
                            <td>{{ $offer->priority }}</td>
                            <td>
                                @if($offer->is_active)
                                    <span class="badge text-bg-success">Active</span>
                                @else
                                    <span class="badge text-bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @php
                                    $offerEditPayload = [
                                        'uuid' => $offer->uuid,
                                        'name' => $offer->name,
                                        'audience' => $offer->audience,
                                        'type' => $offer->type,
                                        'type_label' => \App\Helpers\OfferType::label($offer->type),
                                        'is_active' => (bool) $offer->is_active,
                                        'priority' => (int) $offer->priority,
                                        'window' => $offer->window,
                                        'description' => $offer->description,
                                        'config' => $offer->config ?? [],
                                        'starts_at' => optional($offer->starts_at)->format('Y-m-d\TH:i'),
                                        'ends_at' => optional($offer->ends_at)->format('Y-m-d\TH:i'),
                                        'starts_at_display' => optional($offer->starts_at)->format('M j, Y g:i A'),
                                        'ends_at_display' => optional($offer->ends_at)->format('M j, Y g:i A'),
                                    ];
                                @endphp
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-view-offer"
                                        data-uuid="{{ $offer->uuid }}"
                                        data-offer="{{ e(json_encode($offerEditPayload)) }}">View details</button>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-offer"
                                        data-offer="{{ e(json_encode($offerEditPayload)) }}">Edit</button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-offer" data-uuid="{{ $offer->uuid }}">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No offers yet. Seed defaults or add one.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="offer-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form id="offer-form">
                    <div class="modal-header">
                        <h5 class="modal-title" id="offer-modal-title">Add offer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="offer-uuid" value="">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" id="offer-name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Priority (lower = wins)</label>
                                <input type="number" class="form-control" name="priority" id="offer-priority" value="100" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Audience</label>
                                <select class="form-select" name="audience" id="offer-audience" required>
                                    <option value="shop">Shop</option>
                                    <option value="rider">Rider</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Type</label>
                                <select class="form-select" name="type" id="offer-type" required>
                                    @foreach($types as $type)
                                        <option value="{{ $type['value'] }}" data-audience="{{ $type['audience'] }}">{{ $type['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Window</label>
                                <select class="form-select" name="window" id="offer-window">
                                    <option value="calendar_month">Calendar month</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="offer-is-active" value="1">
                                    <label class="form-check-label" for="offer-is-active">Active</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Starts at</label>
                                <input type="datetime-local" class="form-control" name="starts_at" id="offer-starts-at">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ends at</label>
                                <input type="datetime-local" class="form-control" name="ends_at" id="offer-ends-at">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="offer-description" rows="2"></textarea>
                            </div>

                            <div class="col-12"><hr class="my-1"><h6 class="mb-0">Config</h6></div>
                            <div class="col-md-4 config-field" data-keys="min_completed">
                                <label class="form-label">Min completed</label>
                                <input type="number" class="form-control" id="cfg-min-completed" min="0" value="5">
                            </div>
                            <div class="col-md-4 config-field" data-keys="every_n">
                                <label class="form-label">Every N</label>
                                <input type="number" class="form-control" id="cfg-every-n" min="1" value="5">
                            </div>
                            <div class="col-md-4 config-field" data-keys="first_n">
                                <label class="form-label">First N</label>
                                <input type="number" class="form-control" id="cfg-first-n" min="1" value="5">
                            </div>
                            <div class="col-md-4 config-field" data-keys="fee_percent_off">
                                <label class="form-label">Fee % off</label>
                                <input type="number" class="form-control" id="cfg-fee-percent-off" min="0" max="100" step="0.01" value="50">
                            </div>
                            <div class="col-md-4 config-field" data-keys="commission_percent">
                                <label class="form-label">Commission %</label>
                                <input type="number" class="form-control" id="cfg-commission-percent" min="0" max="100" step="0.01" value="10">
                            </div>
                            <div class="col-md-4 config-field" data-keys="bonus_amount">
                                <label class="form-label">Bonus amount (Rs)</label>
                                <input type="number" class="form-control" id="cfg-bonus-amount" min="0" step="0.01" value="200">
                            </div>
                            <div class="col-md-4 config-field" data-keys="start_hour">
                                <label class="form-label">Peak start hour</label>
                                <input type="number" class="form-control" id="cfg-start-hour" min="0" max="23" value="17">
                            </div>
                            <div class="col-md-4 config-field" data-keys="end_hour">
                                <label class="form-label">Peak end hour</label>
                                <input type="number" class="form-control" id="cfg-end-hour" min="1" max="24" value="22">
                            </div>
                            <div class="col-md-4 config-field" data-keys="weekdays">
                                <label class="form-label">Weekdays (ISO 1–7, comma)</label>
                                <input type="text" class="form-control" id="cfg-weekdays" value="5,6" placeholder="5,6 = Fri,Sat">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save offer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="offer-detail-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="offer-detail-title">Offer details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="offer-detail-loading" class="text-center text-muted py-4 d-none">Loading…</div>
                    <div id="offer-detail-content">
                        <div class="mb-3">
                            <div class="fw-semibold fs-5" data-field="name">—</div>
                            <div class="mt-1" data-field="status"></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small">Audience</div>
                                <div class="fw-medium" data-field="audience">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Priority</div>
                                <div class="fw-medium" data-field="priority">—</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Type</div>
                                <div class="fw-medium" data-field="type">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Window</div>
                                <div class="fw-medium" data-field="window">—</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small">Schedule</div>
                                <div class="fw-medium" data-field="schedule">—</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small">Description</div>
                                <div class="fw-medium" data-field="description">—</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted small mb-1">Config / rules</div>
                                <div class="border rounded p-3 bg-light" data-field="config">—</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="offer-detail-edit">Edit offer</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const routes = {
        store: @json(route('offers.store')),
        update: @json(url('/offers')),
    };
    const typeConfigKeys = {
        rider_commission_reduce: ['min_completed', 'commission_percent'],
        rider_milestone_bonus: ['min_completed', 'bonus_amount'],
        rider_peak_bonus: ['commission_percent', 'start_hour', 'end_hour', 'weekdays'],
        shop_nth_free: ['every_n'],
        shop_fee_percent_off: ['min_completed', 'fee_percent_off'],
        shop_first_n_discount: ['first_n', 'fee_percent_off'],
    };

    function filterTypes() {
        const audience = $('#offer-audience').val();
        $('#offer-type option').each(function () {
            const show = $(this).data('audience') === audience;
            $(this).toggle(show);
        });
        const visible = $('#offer-type option:visible').first().val();
        if (!$('#offer-type option:selected').is(':visible')) {
            $('#offer-type').val(visible);
        }
        toggleConfigFields();
    }

    function toggleConfigFields() {
        const keys = typeConfigKeys[$('#offer-type').val()] || [];
        $('.config-field').each(function () {
            const fieldKeys = String($(this).data('keys')).split(',');
            $(this).toggle(fieldKeys.some((k) => keys.includes(k)));
        });
    }

    function buildConfig() {
        const keys = typeConfigKeys[$('#offer-type').val()] || [];
        const cfg = {};
        if (keys.includes('min_completed')) cfg.min_completed = Number($('#cfg-min-completed').val() || 0);
        if (keys.includes('every_n')) cfg.every_n = Number($('#cfg-every-n').val() || 1);
        if (keys.includes('first_n')) cfg.first_n = Number($('#cfg-first-n').val() || 1);
        if (keys.includes('fee_percent_off')) cfg.fee_percent_off = Number($('#cfg-fee-percent-off').val() || 0);
        if (keys.includes('commission_percent')) cfg.commission_percent = Number($('#cfg-commission-percent').val() || 0);
        if (keys.includes('bonus_amount')) cfg.bonus_amount = Number($('#cfg-bonus-amount').val() || 0);
        if (keys.includes('start_hour')) cfg.start_hour = Number($('#cfg-start-hour').val() || 0);
        if (keys.includes('end_hour')) cfg.end_hour = Number($('#cfg-end-hour').val() || 24);
        if (keys.includes('weekdays')) {
            cfg.weekdays = String($('#cfg-weekdays').val() || '')
                .split(',')
                .map((v) => parseInt(v.trim(), 10))
                .filter((n) => !isNaN(n));
        }
        return cfg;
    }

    const configLabels = {
        min_completed: 'Min completed',
        every_n: 'Every N',
        first_n: 'First N',
        fee_percent_off: 'Fee % off',
        commission_percent: 'Commission %',
        bonus_amount: 'Bonus amount (Rs)',
        start_hour: 'Peak start hour',
        end_hour: 'Peak end hour',
        weekdays: 'Weekdays',
    };

    let detailOffer = null;

    function parseOfferFromButton(el) {
        const raw = el.getAttribute('data-offer');
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (err) {
            const cached = $(el).data('offer');
            return cached && typeof cached === 'object' ? cached : null;
        }
    }

    function formatDateDisplay(value) {
        if (!value) return null;
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return String(value);
        return d.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit',
        });
    }

    function toLocalInput(value) {
        if (!value) return '';
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) return value;
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '';
        const pad = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())
            + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function formatConfig(config, type) {
        const cfg = config && typeof config === 'object' ? config : {};
        const keys = (typeConfigKeys[type] || Object.keys(cfg)).filter((k) => cfg[k] != null && cfg[k] !== '');
        if (!keys.length) {
            const allKeys = Object.keys(cfg);
            if (!allKeys.length) return '<span class="text-muted">No config rules</span>';
            return allKeys.map((key) => {
                let value = cfg[key];
                if (Array.isArray(value)) value = value.join(', ');
                return '<div><span class="text-muted">' + (configLabels[key] || key) + ':</span> <strong>' + value + '</strong></div>';
            }).join('');
        }
        return keys.map((key) => {
            let value = cfg[key];
            if (Array.isArray(value)) value = value.join(', ');
            return '<div><span class="text-muted">' + (configLabels[key] || key) + ':</span> <strong>' + value + '</strong></div>';
        }).join('');
    }

    function fillDetailModal(offer) {
        detailOffer = Object.assign({}, offer, {
            starts_at: toLocalInput(offer.starts_at || offer.starts_at_display),
            ends_at: toLocalInput(offer.ends_at || offer.ends_at_display),
        });

        const start = formatDateDisplay(offer.starts_at) || offer.starts_at_display;
        const end = formatDateDisplay(offer.ends_at) || offer.ends_at_display;
        let schedule = 'No schedule limit';
        if (start || end) {
            schedule = (start || 'Anytime') + ' → ' + (end || 'No end');
        }

        $('#offer-detail-title').text(offer.name || 'Offer details');
        $('#offer-detail-content [data-field="name"]').text(offer.name || '—');
        $('#offer-detail-content [data-field="status"]').html(
            offer.is_active
                ? '<span class="badge text-bg-success">Active</span>'
                : '<span class="badge text-bg-secondary">Inactive</span>'
        );
        $('#offer-detail-content [data-field="audience"]').text(
            offer.audience ? offer.audience.charAt(0).toUpperCase() + offer.audience.slice(1) : '—'
        );
        $('#offer-detail-content [data-field="type"]').text(offer.type_label || offer.type || '—');
        $('#offer-detail-content [data-field="window"]').text(offer.window || '—');
        $('#offer-detail-content [data-field="priority"]').text(offer.priority ?? '—');
        $('#offer-detail-content [data-field="schedule"]').text(schedule);
        $('#offer-detail-content [data-field="description"]').text(offer.description || '—');
        $('#offer-detail-content [data-field="config"]').html(formatConfig(offer.config || {}, offer.type));
    }

    function openDetailModal(uuid, fallbackOffer) {
        const $loading = $('#offer-detail-loading');
        const $content = $('#offer-detail-content');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('offer-detail-modal')).show();

        if (fallbackOffer) {
            fillDetailModal(fallbackOffer);
        }

        if (!uuid) return;

        $loading.removeClass('d-none');
        if (!fallbackOffer) $content.addClass('d-none');

        window.AjaxHelper.get(routes.update + '/' + uuid, null, {
            showLoader: false,
            success(response) {
                const offer = response.data || response;
                fillDetailModal(offer);
                $loading.addClass('d-none');
                $content.removeClass('d-none');
            },
            error() {
                $loading.addClass('d-none');
                $content.removeClass('d-none');
                if (!fallbackOffer && window.NotificationHelper) {
                    window.NotificationHelper.error('Could not load offer details.');
                }
            },
        });
    }

    function openModal(offer) {
        const isEdit = !!offer;
        $('#offer-modal-title').text(isEdit ? 'Edit offer' : 'Add offer');
        $('#offer-uuid').val(offer?.uuid || '');
        $('#offer-name').val(offer?.name || '');
        $('#offer-audience').val(offer?.audience || 'shop');
        filterTypes();
        $('#offer-type').val(offer?.type || $('#offer-type option:visible').first().val());
        toggleConfigFields();
        $('#offer-window').val(offer?.window || 'calendar_month');
        $('#offer-priority').val(offer?.priority ?? 100);
        $('#offer-is-active').prop('checked', !!offer?.is_active);
        $('#offer-description').val(offer?.description || '');
        $('#offer-starts-at').val(toLocalInput(offer?.starts_at) || '');
        $('#offer-ends-at').val(toLocalInput(offer?.ends_at) || '');
        const cfg = offer?.config || {};
        $('#cfg-min-completed').val(cfg.min_completed ?? 5);
        $('#cfg-every-n').val(cfg.every_n ?? 5);
        $('#cfg-first-n').val(cfg.first_n ?? 5);
        $('#cfg-fee-percent-off').val(cfg.fee_percent_off ?? 50);
        $('#cfg-commission-percent').val(cfg.commission_percent ?? 10);
        $('#cfg-bonus-amount').val(cfg.bonus_amount ?? 200);
        $('#cfg-start-hour').val(cfg.start_hour ?? 17);
        $('#cfg-end-hour').val(cfg.end_hour ?? 22);
        $('#cfg-weekdays').val(Array.isArray(cfg.weekdays) ? cfg.weekdays.join(',') : '5,6');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('offer-modal')).show();
    }

    $('#btn-add-offer').on('click', () => openModal(null));
    $(document).on('click', '.btn-view-offer', function () {
        const uuid = $(this).data('uuid') || $(this).attr('data-uuid');
        const fallback = parseOfferFromButton(this);
        openDetailModal(uuid, fallback);
    });
    $(document).on('click', '.btn-edit-offer', function () {
        openModal(parseOfferFromButton(this));
    });
    $('#offer-detail-edit').on('click', function () {
        const modal = bootstrap.Modal.getInstance(document.getElementById('offer-detail-modal'));
        if (modal) modal.hide();
        if (detailOffer) openModal(detailOffer);
    });
    $('#offer-audience').on('change', filterTypes);
    $('#offer-type').on('change', toggleConfigFields);

    $('#offer-form').on('submit', function (e) {
        e.preventDefault();
        const uuid = $('#offer-uuid').val();
        const payload = {
            name: $('#offer-name').val(),
            audience: $('#offer-audience').val(),
            type: $('#offer-type').val(),
            window: $('#offer-window').val(),
            priority: Number($('#offer-priority').val() || 100),
            is_active: $('#offer-is-active').is(':checked') ? 1 : 0,
            description: $('#offer-description').val(),
            starts_at: $('#offer-starts-at').val() || null,
            ends_at: $('#offer-ends-at').val() || null,
            config: buildConfig(),
        };
        const url = uuid ? (routes.update + '/' + uuid) : routes.store;
        const method = uuid ? 'PUT' : 'POST';
        AjaxHelper[method === 'PUT' ? 'put' : 'post'](url, payload, {
            success() {
                NotificationHelper.success('Offer saved');
                window.location.reload();
            },
        });
    });

    $(document).on('click', '.btn-delete-offer', function () {
        const uuid = $(this).data('uuid');
        NotificationHelper.confirm({
            title: 'Delete offer?',
            text: 'This cannot be undone.',
            icon: 'warning',
            confirmButtonText: 'Delete',
        }).then((res) => {
            if (!res.isConfirmed) return;
            AjaxHelper.delete(routes.update + '/' + uuid, null, {
                success() {
                    NotificationHelper.success('Deleted');
                    window.location.reload();
                },
            });
        });
    });

    filterTypes();
})();
</script>
@endpush
