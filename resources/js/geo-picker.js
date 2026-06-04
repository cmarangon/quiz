import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// Bundlers rewrite Leaflet's default image paths, so point them at the
// Vite-resolved URLs explicitly.
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
 * The map lets an author drop/drag a pin to set a geo-guesser question's
 * correct location without looking up coordinates by hand. The pin is kept
 * in sync with the Livewire latitude/longitude fields in both directions:
 * clicking the map writes the fields, and editing the fields moves the pin.
 *
 * config: {
 *   center: {lat,lng}, zoom: int,
 *   latField: string,            // Livewire property name for latitude
 *   lngField: string,            // Livewire property name for longitude
 * }
 */
export function geoPicker(config) {
    return {
        map: null,
        marker: null,
        lat: null,
        lng: null,

        init() {
            this.lat = this.$wire.entangle(config.latField);
            this.lng = this.$wire.entangle(config.lngField);

            this.$nextTick(() => this.setup());

            this.$watch('lat', () => this.reflectFields());
            this.$watch('lng', () => this.reflectFields());
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

            // The container is often hidden/animated on first paint.
            setTimeout(() => this.map && this.map.invalidateSize(), 150);

            this.map.on('click', (e) => {
                this.lat = round(e.latlng.lat);
                this.lng = round(e.latlng.lng);
            });

            const lat = parseFloat(this.lat);
            const lng = parseFloat(this.lng);
            if (Number.isFinite(lat) && Number.isFinite(lng)) {
                this.setMarker(lat, lng);
                this.map.setView([lat, lng], Math.max(zoom, 4));
            }
        },

        reflectFields() {
            const lat = parseFloat(this.lat);
            const lng = parseFloat(this.lng);

            if (!this.map || !Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }

            this.setMarker(lat, lng);
        },

        setMarker(lat, lng) {
            if (this.marker) {
                this.marker.setLatLng([lat, lng]);

                return;
            }

            this.marker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
            this.marker.on('dragend', (e) => {
                const p = e.target.getLatLng();
                this.lat = round(p.lat);
                this.lng = round(p.lng);
            });
        },

        destroy() {
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
