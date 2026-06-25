# Geo Guesser Picker Rework — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the admin picker map so lat/lng saves correctly, keep map and text fields in bidirectional sync, and show scoring radius circles on the admin map.

**Architecture:** Replace the broken `$wire.entangle()` pattern in `geo-picker.js` with explicit `$wire.set()` (map→Livewire) and `$wire.$watch()` (Livewire→map) calls. Add `reflectCircles()` to draw/update Leaflet `L.circle()` overlays for the threshold and max-distance scoring zones. Pass two new field-name keys to the `geoPicker` config in the blade.

**Tech Stack:** Leaflet (already imported), Alpine.js via Livewire 3's `$wire` API, Vite (build via `npm run dev`).

## Global Constraints

- No PHP backend changes — `QuizBuilder.php` and `GeoGuesserType.php` are correct as-is.
- `$wire.set(prop, val)` and `$wire.$watch(prop, cb)` are the Livewire 3 Alpine APIs for pushing/watching Livewire properties. Do NOT use `$wire.entangle()` inside `init()`.
- Circles drawn with `L.circle([lat, lng], { radius: metres })` — radius is in **metres**, not km.
- Keep `round()` helper (rounds to 6 decimal places, returns a string) — it is used to normalise coordinates.

---

## File Map

| File | Change |
|---|---|
| `resources/js/geo-picker.js` | Replace entangle sync; add circle properties and `reflectCircles()` |
| `resources/views/livewire/quiz-builder.blade.php` | Add `thresholdField` and `maxDistField` to the `geoPicker(…)` config call |

---

### Task 1: Fix lat/lng bidirectional sync

**Files:**
- Modify: `resources/js/geo-picker.js`

**What this task does:**

Removes the broken `$wire.entangle()` calls from `init()` and replaces them with:
- Initial read from `$wire[config.latField]`
- `$wire.$watch()` to receive changes from the text fields (Livewire → Alpine → map)
- `$wire.set()` on map click and marker drag (map → Livewire)

Introduces `setPin(lat, lng)` as the single method that does both the local update and the Livewire push — avoids the duplication of setting `this.lat`/`this.lng` and calling `$wire.set` in two places.

- [ ] **Step 1: Replace `geo-picker.js` with the fixed version**

Replace the entire file content:

```js
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
 *   latField: string,       // Livewire property name for latitude
 *   lngField: string,       // Livewire property name for longitude
 * }
 */
export function geoPicker(config) {
    return {
        map: null,
        marker: null,
        lat: null,
        lng: null,

        init() {
            this.lat = parseFloat(this.$wire[config.latField]) || null;
            this.lng = parseFloat(this.$wire[config.lngField]) || null;

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
```

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Expected: exits 0, no errors. (Or keep `npm run dev` running — either works.)

- [ ] **Step 3: Smoke-test the fix manually**

Open the quiz builder in the browser. Add or edit a geo guesser question.

Verify:
1. Click the map → the lat/lng text fields fill in immediately.
2. Drag the marker → the fields update.
3. Clear the fields, type `48.8584` in Latitude and `2.2945` in Longitude → the pin moves to the Eiffel Tower.
4. Click Save Question → reload the page, edit the question → the pin is at the saved location.

- [ ] **Step 4: Commit**

```bash
git add resources/js/geo-picker.js
git commit -m "fix: restore bidirectional sync in geo picker — replace broken entangle with explicit \$wire.set/\$watch"
```

---

### Task 2: Add scoring radius circles to the admin picker

**Files:**
- Modify: `resources/js/geo-picker.js`
- Modify: `resources/views/livewire/quiz-builder.blade.php`

**What this task does:**

Extends `geoPicker` with two new data properties (`thresholdCircle`, `maxCircle`, `thresholdKm`, `maxKm`) and a new `reflectCircles()` method. Watches `thresholdField` and `maxDistField` Livewire properties so the circles update live as the admin types. Updates the blade config to pass the two new field names.

- [ ] **Step 1: Replace `geo-picker.js` with the circles version**

Replace the entire file:

```js
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
            this.lat = parseFloat(this.$wire[config.latField]) || null;
            this.lng = parseFloat(this.$wire[config.lngField]) || null;
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
```

- [ ] **Step 2: Update the blade config to pass the new field names**

In `resources/views/livewire/quiz-builder.blade.php`, find the `geoPicker` call (around line 238) and replace it:

Old:
```html
x-data="geoPicker({ latField: 'questionGeoLat', lngField: 'questionGeoLng', center: { lat: 20, lng: 0 }, zoom: 2 })"
```

New:
```html
x-data="geoPicker({ latField: 'questionGeoLat', lngField: 'questionGeoLng', thresholdField: 'questionGeoThresholdKm', maxDistField: 'questionGeoMaxDistanceKm', center: { lat: 20, lng: 0 }, zoom: 2 })"
```

- [ ] **Step 3: Build assets**

```bash
npm run build
```

Expected: exits 0.

- [ ] **Step 4: Smoke-test circles manually**

In the quiz builder, add a geo guesser question:

1. Click the map to place a pin (e.g. somewhere in Europe).
2. Enter `50` in the "Full-points radius (km)" field → a **green circle** appears around the pin with ~50 km radius.
3. Enter `500` in the "Zero-points distance (km)" field → a **faded red dashed circle** appears, much larger.
4. Drag the pin to a different location → both circles follow.
5. Clear the threshold field → green circle disappears.
6. Clear the max-distance field → red circle disappears.
7. Enter threshold `100` and max `50` → the form should show a validation error ("threshold must be less than max distance") on save — circles are drawn but the save is blocked.

- [ ] **Step 5: Commit**

```bash
git add resources/js/geo-picker.js resources/views/livewire/quiz-builder.blade.php
git commit -m "feat: show scoring radius circles on admin geo picker map"
```
