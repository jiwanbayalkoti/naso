/**
 * Google Maps delivery tracking helper.
 */
(function (window) {
    'use strict';

    const GoogleMapsTracker = {
        mapsPromise: null,

        getApiKey() {
            return (
                document.querySelector('meta[name="google-maps-api-key"]')?.content ||
                window.NASO_GOOGLE_MAPS_KEY ||
                ''
            );
        },

        loadMaps() {
            const apiKey = this.getApiKey();

            if (!apiKey) {
                return Promise.reject(new Error('Google Maps API key is not configured.'));
            }

            if (window.google && window.google.maps) {
                return Promise.resolve(window.google.maps);
            }

            if (this.mapsPromise) {
                return this.mapsPromise;
            }

            this.mapsPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}&libraries=places`;
                script.async = true;
                script.defer = true;
                script.onload = () => resolve(window.google.maps);
                script.onerror = () => reject(new Error('Failed to load Google Maps.'));
                document.head.appendChild(script);
            });

            return this.mapsPromise;
        },

        async geocodeAddress(geocoder, address) {
            if (!address) {
                return null;
            }

            return new Promise((resolve) => {
                geocoder.geocode({ address }, (results, status) => {
                    if (status === 'OK' && results && results[0]) {
                        const location = results[0].geometry.location;
                        resolve({
                            lat: location.lat(),
                            lng: location.lng(),
                        });
                    } else {
                        resolve(null);
                    }
                });
            });
        },

        async resolvePointFromBackend(point, fallbackLabel) {
            if (!point?.address || !window.AddressAutocomplete) {
                return null;
            }

            try {
                const response = await window.AddressAutocomplete.apiGet('/geocode/search', {
                    q: point.address,
                });
                const items = response.data || [];

                for (const item of items) {
                    if (item.latitude != null && item.longitude != null) {
                        return {
                            lat: Number(item.latitude),
                            lng: Number(item.longitude),
                            label: point.label || fallbackLabel || 'Location',
                            address: item.address || point.address || '',
                        };
                    }

                    if (item.id && item.provider) {
                        const placeResponse = await window.AddressAutocomplete.apiGet('/geocode/place', {
                            provider: item.provider,
                            id: item.id,
                        });
                        const data = placeResponse.data || {};
                        if (data.latitude != null && data.longitude != null) {
                            return {
                                lat: Number(data.latitude),
                                lng: Number(data.longitude),
                                label: point.label || fallbackLabel || 'Location',
                                address: data.address || point.address || '',
                            };
                        }
                    }
                }
            } catch (_) {}

            return null;
        },

        async resolvePoint(geocoder, point, fallbackLabel) {
            if (!point) {
                return null;
            }

            if (point.latitude != null && point.longitude != null) {
                return {
                    lat: Number(point.latitude),
                    lng: Number(point.longitude),
                    label: point.label || fallbackLabel || 'Location',
                    address: point.address || '',
                };
            }

            const backendPoint = await this.resolvePointFromBackend(point, fallbackLabel);
            if (backendPoint) {
                return backendPoint;
            }

            const geocoded = await this.geocodeAddress(geocoder, point.address);
            if (!geocoded) {
                return null;
            }

            return {
                lat: geocoded.lat,
                lng: geocoded.lng,
                label: point.label || fallbackLabel || 'Location',
                address: point.address || '',
            };
        },

        drawRoadRoute(maps, map, pickup, dropoff, route, bounds) {
            const routePoints = route && Array.isArray(route.points) ? route.points : [];

            if (routePoints.length) {
                const path = routePoints.map((point) => ({
                    lat: Number(point.lat),
                    lng: Number(point.lng),
                }));

                new maps.Polyline({
                    map,
                    path,
                    geodesic: true,
                    strokeColor: '#2563eb',
                    strokeOpacity: 0.9,
                    strokeWeight: 5,
                });

                path.forEach((point) => bounds.extend(point));

                return Promise.resolve(route);
            }

            if (!pickup || !dropoff) {
                return Promise.resolve(null);
            }

            return new Promise((resolve) => {
                const directionsService = new maps.DirectionsService();
                const directionsRenderer = new maps.DirectionsRenderer({
                    map,
                    suppressMarkers: true,
                    polylineOptions: {
                        strokeColor: '#2563eb',
                        strokeOpacity: 0.9,
                        strokeWeight: 5,
                    },
                });

                directionsService.route(
                    {
                        origin: { lat: pickup.lat, lng: pickup.lng },
                        destination: { lat: dropoff.lat, lng: dropoff.lng },
                        travelMode: maps.TravelMode.DRIVING,
                    },
                    (result, status) => {
                        if (status === 'OK') {
                            directionsRenderer.setDirections(result);
                            const routePath = result.routes[0].overview_path || [];
                            routePath.forEach((point) => bounds.extend(point));
                            resolve({
                                distance_text: result.routes[0].legs[0]?.distance?.text || null,
                                duration_text: result.routes[0].legs[0]?.duration?.text || null,
                            });
                        } else {
                            const fallbackPath = [
                                { lat: pickup.lat, lng: pickup.lng },
                                { lat: dropoff.lat, lng: dropoff.lng },
                            ];

                            new maps.Polyline({
                                map,
                                path: fallbackPath,
                                geodesic: true,
                                strokeColor: '#2563eb',
                                strokeOpacity: 0.85,
                                strokeWeight: 5,
                            });

                            fallbackPath.forEach((point) => bounds.extend(point));
                            resolve(null);
                        }
                    }
                );
            });
        },

        renderLegend(container, pickup, dropoff, rider, route) {
            const legend = document.createElement('div');
            legend.className = 'map-tracking-legend';
            legend.innerHTML = `
                <div class="map-tracking-legend-item">
                    <span class="map-tracking-dot map-tracking-dot-pickup"></span>
                    <div>
                        <strong>Pickup</strong>
                        <div class="text-muted small">${pickup?.label || 'Pickup point'}</div>
                    </div>
                </div>
                <div class="map-tracking-legend-item">
                    <span class="map-tracking-dot map-tracking-dot-dropoff"></span>
                    <div>
                        <strong>Drop</strong>
                        <div class="text-muted small">${dropoff?.label || 'Drop point'}</div>
                    </div>
                </div>
                ${
                    rider
                        ? `<div class="map-tracking-legend-item">
                            <span class="map-tracking-dot map-tracking-dot-rider"></span>
                            <div>
                                <strong>Rider</strong>
                                <div class="text-muted small">${rider.label || 'Live location'}${
                                    rider.pending ? ' (waiting for GPS...)' : ''
                                }</div>
                            </div>
                           </div>`
                        : ''
                }
                ${
                    route?.distance_text || route?.duration_text
                        ? `<div class="map-tracking-legend-meta text-muted small">
                            ${route.distance_text ? `Distance: ${route.distance_text}` : ''}
                            ${route.distance_text && route.duration_text ? ' · ' : ''}
                            ${route.duration_text ? `ETA: ${route.duration_text}` : ''}
                           </div>`
                        : ''
                }
            `;

            container.insertAdjacentElement('afterend', legend);
        },

        async render(containerId, tracking, options) {
            const settings = Object.assign(
                {
                    pollUrl: null,
                    pollInterval: 15000,
                    showRoute: true,
                    showLegend: true,
                },
                options || {}
            );

            const container = document.getElementById(containerId);
            if (!container) {
                return null;
            }

            if (!this.getApiKey()) {
                container.innerHTML =
                    '<div class="alert alert-warning mb-0">Google Maps API key is missing. Add GOOGLE_MAPS_API_KEY to your .env file.</div>';
                return null;
            }

            const maps = await this.loadMaps();
            const geocoder = new maps.Geocoder();
            const pickup = await this.resolvePoint(geocoder, tracking.pickup, 'Pickup');
            const dropoff = await this.resolvePoint(geocoder, tracking.dropoff, 'Drop');
            const rider =
                tracking.rider && tracking.rider.latitude != null && tracking.rider.longitude != null
                    ? {
                          lat: Number(tracking.rider.latitude),
                          lng: Number(tracking.rider.longitude),
                          label: tracking.rider.name || 'Rider',
                      }
                    : tracking.rider
                      ? {
                            label: tracking.rider.name || 'Rider',
                            pending: true,
                        }
                      : null;

            const points = [pickup, dropoff, rider].filter(Boolean);
            if (!points.length) {
                container.innerHTML =
                    '<div class="alert alert-info mb-0">Pickup and drop locations are not available yet for this delivery.</div>';
                return null;
            }

            const map = new maps.Map(container, {
                center: { lat: points[0].lat, lng: points[0].lng },
                zoom: 13,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true,
            });

            const bounds = new maps.LatLngBounds();
            const markers = {};

            const addMarker = (point, markerOptions) => {
                const marker = new maps.Marker(
                    Object.assign(
                        {
                            map,
                            position: { lat: point.lat, lng: point.lng },
                            title: point.label,
                        },
                        markerOptions || {}
                    )
                );

                if (point.label || point.address) {
                    const info = new maps.InfoWindow({
                        content: `<strong>${point.label || 'Location'}</strong><br>${point.address || ''}`,
                    });
                    marker.addListener('click', () => info.open({ anchor: marker, map }));
                }

                bounds.extend(marker.getPosition());
                return marker;
            };

            if (pickup) {
                markers.pickup = addMarker(pickup, {
                    label: 'P',
                    title: 'Pickup Point',
                    icon: 'http://maps.google.com/mapfiles/ms/icons/green-dot.png',
                });
            }

            if (dropoff) {
                markers.dropoff = addMarker(dropoff, {
                    label: 'D',
                    title: 'Drop Point',
                    icon: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
                });
            }

            if (rider && rider.lat != null && rider.lng != null) {
                markers.rider = addMarker(rider, {
                    label: 'R',
                    title: 'Rider',
                    icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                });
            }

            let routeMeta = tracking.route || null;
            if (settings.showRoute && pickup && dropoff) {
                routeMeta = await this.drawRoadRoute(maps, map, pickup, dropoff, tracking.route, bounds);
            }

            if (settings.showLegend) {
                this.renderLegend(container, pickup, dropoff, rider, routeMeta);
            }

            map.fitBounds(bounds, 56);

            const instance = {
                map,
                markers,
                update(trackingData) {
                    const nextRider =
                        trackingData.rider &&
                        trackingData.rider.latitude != null &&
                        trackingData.rider.longitude != null
                            ? {
                                  lat: Number(trackingData.rider.latitude),
                                  lng: Number(trackingData.rider.longitude),
                                  label: trackingData.rider.name || 'Rider',
                              }
                            : null;

                    if (!nextRider) {
                        if (markers.rider) {
                            markers.rider.setMap(null);
                            delete markers.rider;
                        }
                        return;
                    }

                    const position = { lat: nextRider.lat, lng: nextRider.lng };
                    if (markers.rider) {
                        markers.rider.setPosition(position);
                    } else {
                        markers.rider = addMarker(nextRider, {
                            label: 'R',
                            title: 'Rider',
                            icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                        });
                    }
                },
            };

            if (settings.pollUrl && tracking.is_live) {
                const poll = () => {
                    const request = window.AjaxHelper
                        ? window.AjaxHelper.get(settings.pollUrl, null, { showLoader: false, preventDuplicate: false })
                        : fetch(settings.pollUrl, {
                              headers: {
                                  Accept: 'application/json',
                                  'X-Requested-With': 'XMLHttpRequest',
                              },
                              credentials: 'same-origin',
                          }).then((response) => response.json());

                    Promise.resolve(request)
                        .then((response) => {
                            const data = response.data || response;
                            if (data) {
                                instance.update(data);
                            }
                        })
                        .catch(() => {});
                };

                poll();
                instance.pollTimer = setInterval(poll, settings.pollInterval);
            }

            return instance;
        },
    };

    window.GoogleMapsTracker = GoogleMapsTracker;
})(window);
