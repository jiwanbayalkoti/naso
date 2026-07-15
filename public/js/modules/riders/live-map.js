/**
 * Fleet live map — polls rider GPS for admin (all) and shop (online + on-delivery).
 */
(function (window, $) {
    'use strict';

    if (!$ || !window.GoogleMapsTracker) {
        return;
    }

    const LiveRiderMap = {
        pollUrl: '',
        map: null,
        markers: {},
        infoWindow: null,
        pollTimer: null,
        selectedUuid: null,

        async init() {
            const $root = $('#rider-live-map-page');
            if (!$root.length) {
                return;
            }

            this.pollUrl = $root.data('poll-url');
            const mapEl = document.getElementById('rider-fleet-map');
            if (!mapEl) {
                return;
            }

            try {
                const maps = await window.GoogleMapsTracker.loadMaps();
                this.map = new maps.Map(mapEl, {
                    zoom: 12,
                    center: { lat: 27.7172, lng: 85.324 },
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                });
                this.infoWindow = new maps.InfoWindow();
            } catch (error) {
                mapEl.innerHTML =
                    '<div class="p-4 text-danger">Google Maps could not load. Check your Maps API key in settings.</div>';
                return;
            }

            $('#rider-map-refresh').on('click', () => this.refresh());
            $('#rider-map-list').on('click', '[data-rider-uuid]', (e) => {
                const uuid = $(e.currentTarget).data('rider-uuid');
                this.focusRider(uuid);
            });

            await this.refresh();
            this.pollTimer = setInterval(() => this.refresh(true), 15000);
        },

        async refresh(silent) {
            try {
                const response = await $.ajax({
                    url: this.pollUrl,
                    method: 'GET',
                    dataType: 'json',
                });

                const riders = response?.data?.riders || [];
                this.renderList(riders);
                this.renderMarkers(riders);
                const at = response?.data?.refreshed_at;
                $('#rider-map-updated').text(
                    at ? 'Updated ' + new Date(at).toLocaleTimeString() : 'Updated just now'
                );
            } catch (error) {
                if (!silent && window.NotificationHelper) {
                    window.NotificationHelper.error('Could not load rider locations.');
                }
            }
        },

        renderList(riders) {
            const $list = $('#rider-map-list');
            $('#rider-map-count').text(riders.length);

            if (!riders.length) {
                $list.html(
                    '<div class="list-group-item text-muted small">No riders with a live location right now.</div>'
                );
                return;
            }

            const html = riders
                .map((rider) => {
                    const onlineClass = rider.is_online ? 'is-online' : 'is-offline';
                    const active = rider.uuid === this.selectedUuid ? ' active' : '';
                    const meta = [rider.vehicle_type, rider.vehicle_number, rider.phone]
                        .filter(Boolean)
                        .join(' · ');

                    return `
                        <button type="button"
                                class="list-group-item list-group-item-action${active}"
                                data-rider-uuid="${rider.uuid}">
                            <div class="d-flex align-items-center gap-2">
                                <span class="rider-map-dot ${onlineClass}"></span>
                                <div class="flex-grow-1 text-start">
                                    <div class="fw-semibold">${this.escape(rider.name)}</div>
                                    <div class="small text-muted">${this.escape(meta || rider.status)}</div>
                                </div>
                                <span class="badge ${rider.is_online ? 'text-bg-success' : 'text-bg-secondary'}">${
                                    rider.is_online ? 'Online' : 'Away'
                                }</span>
                            </div>
                        </button>`;
                })
                .join('');

            $list.html(html);
        },

        renderMarkers(riders) {
            const maps = window.google.maps;
            const seen = new Set();
            const bounds = new maps.LatLngBounds();
            let hasPoints = false;

            riders.forEach((rider) => {
                const lat = Number(rider.latitude);
                const lng = Number(rider.longitude);
                if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                    return;
                }

                seen.add(rider.uuid);
                hasPoints = true;
                const position = { lat, lng };
                bounds.extend(position);

                if (this.markers[rider.uuid]) {
                    this.markers[rider.uuid].setPosition(position);
                    this.markers[rider.uuid].setTitle(rider.name);
                } else {
                    const marker = new maps.Marker({
                        map: this.map,
                        position,
                        title: rider.name,
                        label: {
                            text: (rider.name || 'R').charAt(0).toUpperCase(),
                            color: '#ffffff',
                            fontWeight: '700',
                        },
                        icon: {
                            path: maps.SymbolPath.CIRCLE,
                            scale: 12,
                            fillColor: rider.is_online ? '#16a34a' : '#64748b',
                            fillOpacity: 1,
                            strokeColor: '#ffffff',
                            strokeWeight: 2,
                        },
                    });

                    marker.addListener('click', () => this.focusRider(rider.uuid));
                    this.markers[rider.uuid] = marker;
                }

                const marker = this.markers[rider.uuid];
                marker.setIcon({
                    path: maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: rider.is_online ? '#16a34a' : '#64748b',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 2,
                });
                marker.__rider = rider;
            });

            Object.keys(this.markers).forEach((uuid) => {
                if (!seen.has(uuid)) {
                    this.markers[uuid].setMap(null);
                    delete this.markers[uuid];
                }
            });

            if (hasPoints && !this.selectedUuid) {
                this.map.fitBounds(bounds, 48);
                if (this.map.getZoom() > 15) {
                    this.map.setZoom(15);
                }
            }
        },

        focusRider(uuid) {
            const marker = this.markers[uuid];
            if (!marker) {
                return;
            }

            this.selectedUuid = uuid;
            this.map.panTo(marker.getPosition());
            if (this.map.getZoom() < 14) {
                this.map.setZoom(14);
            }

            const rider = marker.__rider || {};
            this.infoWindow.setContent(
                `<div style="min-width:160px">
                    <strong>${this.escape(rider.name || 'Rider')}</strong><br>
                    <span>${rider.is_online ? 'Online' : 'Away'}</span>
                    ${rider.phone ? `<br>${this.escape(rider.phone)}` : ''}
                </div>`
            );
            this.infoWindow.open({ map: this.map, anchor: marker });

            $('#rider-map-list [data-rider-uuid]').removeClass('active');
            $(`#rider-map-list [data-rider-uuid="${uuid}"]`).addClass('active');
        },

        escape(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },
    };

    $(function () {
        LiveRiderMap.init();
    });
})(window, window.jQuery);
