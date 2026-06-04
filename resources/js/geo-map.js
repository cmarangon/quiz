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

const EARTH_RADIUS_KM = 6371;

function haversineKm(a, b) {
    const dLat = ((b.lat - a.lat) * Math.PI) / 180;
    const dLng = ((b.lng - a.lng) * Math.PI) / 180;
    const lat1 = (a.lat * Math.PI) / 180;
    const lat2 = (b.lat * Math.PI) / 180;
    const h =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return EARTH_RADIUS_KM * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function correctIcon() {
    return L.divIcon({
        className: 'geo-correct-marker',
        html: '<div style="width:18px;height:18px;border-radius:9999px;background:#22c55e;border:3px solid #fff;box-shadow:0 0 0 2px #16a34a;"></div>',
        iconSize: [18, 18],
        iconAnchor: [9, 9],
    });
}

/**
 * Alpine component backing the geo-guesser map.
 *
 * config: {
 *   center: {lat,lng}, zoom: int,
 *   interactive: bool,            // allow the player to drop a pin
 *   guess: {lat,lng}|null,        // a previously placed guess (review)
 *   correct: {lat,lng}|null,      // revealed correct location (review)
 * }
 */
export function geoMap(config) {
    return {
        map: null,
        guessMarker: null,
        line: null,
        guess: config.guess || null,
        distanceKm: null,

        init() {
            this.$nextTick(() => this.setup());

            // Hand the current pin (if any) to the countdown timer so it can be
            // auto-submitted if the player runs out of time before pressing submit.
            this.$dispatch('answer-provider', { provider: () => this.guess });
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

            if (config.interactive) {
                this.map.on('click', (e) =>
                    this.placeGuess(e.latlng.lat, e.latlng.lng),
                );
            }

            if (this.guess) {
                this.placeGuess(this.guess.lat, this.guess.lng, false);
            }

            if (config.correct) {
                this.revealResult();
            }
        },

        placeGuess(lat, lng, recenter = false) {
            this.guess = { lat, lng };

            if (this.guessMarker) {
                this.guessMarker.setLatLng([lat, lng]);
            } else {
                this.guessMarker = L.marker([lat, lng], {
                    draggable: config.interactive,
                }).addTo(this.map);
                if (config.interactive) {
                    this.guessMarker.on('dragend', (e) => {
                        const p = e.target.getLatLng();
                        this.guess = { lat: p.lat, lng: p.lng };
                    });
                }
            }

            if (recenter) {
                this.map.panTo([lat, lng]);
            }
        },

        revealResult() {
            const correct = config.correct;
            L.marker([correct.lat, correct.lng], { icon: correctIcon() })
                .addTo(this.map)
                .bindTooltip('Correct location', { permanent: false });

            if (this.guess) {
                this.line = L.polyline(
                    [
                        [this.guess.lat, this.guess.lng],
                        [correct.lat, correct.lng],
                    ],
                    { color: '#ef4444', weight: 3, dashArray: '6 6' },
                ).addTo(this.map);

                this.distanceKm = Math.round(haversineKm(this.guess, correct));
                this.map.fitBounds(this.line.getBounds(), { padding: [40, 40] });
            } else {
                this.map.panTo([correct.lat, correct.lng]);
            }
        },

        submit() {
            if (!this.guess) {
                return;
            }
            this.$wire.submitGeoGuess(this.guess.lat, this.guess.lng);
        },

        destroy() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },
    };
}

export function registerGeoMap(Alpine) {
    Alpine.data('geoMap', geoMap);
}
