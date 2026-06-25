import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

function round(value) {
    return String(Math.round(value * 1e6) / 1e6);
}

/**
 * Alpine component backing the quiz-builder location picker.
 *
 * config: {
 *   center: {lat,lng}, zoom: int,
 *   latField: string,          // Livewire property name for latitude
 *   lngField: string,          // Livewire property name for longitude
 *   thresholdField: string,    // Livewire property name for threshold_km (full-points radius)
 *   maxDistField: string,      // Livewire property name for max_distance_km (zero-points boundary)
 * }
 */
export function geoPicker(config) {
    return {
        map: null,
        marker: null,
        thresholdCircle: null,
        maxCircle: null,
        lat: null,
        lng: null,
        thresholdKm: null,
        maxKm: null,

        init() {
            const initLat = parseFloat(this.$wire[config.latField]);
            this.lat = Number.isFinite(initLat) ? initLat : null;
            const initLng = parseFloat(this.$wire[config.lngField]);
            this.lng = Number.isFinite(initLng) ? initLng : null;
            this.thresholdKm = parseFloat(this.$wire[config.thresholdField]) || null;
            this.maxKm = parseFloat(this.$wire[config.maxDistField]) || null;

            // Livewire → Alpine: text field changes move the map pin.
            this.$wire.$watch(config.latField, (val) => {
                const n = parseFloat(val);
                if (Number.isFinite(n)) {
                    this.lat = n;
                    this.reflectFields();
                }
            });
            this.$wire.$watch(config.lngField, (val) => {
                const n = parseFloat(val);
                if (Number.isFinite(n)) {
                    this.lng = n;
                    this.reflectFields();
                }
            });

            // Livewire → Alpine: scoring field changes update the circles.
            this.$wire.$watch(config.thresholdField, (val) => {
                this.thresholdKm = parseFloat(val) || null;
                this.reflectCircles();
            });
            this.$wire.$watch(config.maxDistField, (val) => {
                this.maxKm = parseFloat(val) || null;
                this.reflectCircles();
            });

            this.$nextTick(() => this.setup());
        },

        setup() {
            const el = this.$refs.map;
            if (!el || this.map) {
                return;
            }

            const center = config.center || { lat: 20, lng: 0 };
            const zoom = config.zoom ?? 2;

            this.map = L.map(el, {
                worldCopyJump: true,
                minZoom: 1,
            }).setView([center.lat, center.lng], zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 18,
            }).addTo(this.map);

            setTimeout(() => this.map && this.map.invalidateSize(), 150);

            // Alpine → Livewire: map click sets the pin and pushes to Livewire.
            this.map.on('click', (e) => {
                this.setPin(e.latlng.lat, e.latlng.lng);
            });

            const lat = parseFloat(this.lat);
            const lng = parseFloat(this.lng);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                this.setMarker(lat, lng);
                this.reflectCircles();
                this.map.setView([lat, lng], Math.max(zoom, 4));
            }
        },

        // Single entry point for placing/moving the pin.
        // Pushes to Livewire AND updates the local state + map.
        setPin(lat, lng) {
            const rLat = round(lat);
            const rLng = round(lng);
            this.lat = parseFloat(rLat);
            this.lng = parseFloat(rLng);
            this.$wire.set(config.latField, rLat);
            this.$wire.set(config.lngField, rLng);
            this.reflectFields();
        },

        reflectFields() {
            const lat = parseFloat(this.lat);
            const lng = parseFloat(this.lng);

            if (!this.map || !Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            this.setMarker(lat, lng);
            this.reflectCircles();
        },

        reflectCircles() {
            const lat = parseFloat(this.lat);
            const lng = parseFloat(this.lng);

            if (!this.map || !Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            // Green circle: full-points zone (threshold_km).
            const tKm = parseFloat(this.thresholdKm);
            if (Number.isFinite(tKm) && tKm > 0) {
                const r = tKm * 1000;
                if (this.thresholdCircle) {
                    this.thresholdCircle.setLatLng([lat, lng]).setRadius(r);
                } else {
                    this.thresholdCircle = L.circle([lat, lng], {
                        radius: r,
                        color: '#22c55e',
                        fillColor: '#22c55e',
                        fillOpacity: 0.1,
                        weight: 2,
                    }).addTo(this.map);
                }
            } else if (this.thresholdCircle) {
                this.thresholdCircle.remove();
                this.thresholdCircle = null;
            }

            // Red dashed circle: zero-points boundary (max_distance_km).
            const mKm = parseFloat(this.maxKm);
            if (Number.isFinite(mKm) && mKm > 0) {
                const r = mKm * 1000;
                if (this.maxCircle) {
                    this.maxCircle.setLatLng([lat, lng]).setRadius(r);
                } else {
                    this.maxCircle = L.circle([lat, lng], {
                        radius: r,
                        color: '#ef4444',
                        fillColor: '#ef4444',
                        fillOpacity: 0.05,
                        weight: 2,
                        dashArray: '6 4',
                    }).addTo(this.map);
                }
            } else if (this.maxCircle) {
                this.maxCircle.remove();
                this.maxCircle = null;
            }
        },

        setMarker(lat, lng) {
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);

                return;
            }

            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', (e) => {
                const p = e.target.getLatLng();
                this.setPin(p.lat, p.lng);
            });
        },

        destroy() {
            this.thresholdCircle = null;
            this.maxCircle = null;
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },
    };
}

export function registerGeoPicker(Alpine) {
    Alpine.data('geoPicker', geoPicker);
}
