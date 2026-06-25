# Geo Guesser Picker Rework — Design Spec
Date: 2026-06-25

## Problem

Three issues exist with the geo guesser question type in the quiz builder:

1. **Map → Livewire sync broken**: Clicking the admin picker map sets Alpine's `lat`/`lng` but the value never reaches the Livewire component. Root cause: `$wire.entangle()` called inside `init()` does not establish a two-way setter — it only reads the current Livewire value once. On save, `questionGeoLat`/`questionGeoLng` are empty, so the question has no correct answer.

2. **Text fields → Map sync broken**: The `$watch('lat', …)` watcher listens to Alpine's local property only. Typing into the `wire:model`-bound text inputs updates Livewire directly, bypassing Alpine, so the map pin never moves.

3. **No visual scoring feedback on admin map**: The admin enters `threshold_km` (full-points radius) and `max_distance_km` (zero-points boundary) as numbers with no spatial reference.

## Scope

Two files change. No PHP backend changes — `QuizBuilder.php` and `GeoGuesserType.php` correctly handle validation, payload building, and loading for edit.

---

## Architecture

### `resources/js/geo-picker.js`

**Replace entangle with explicit `$wire` calls.**

Sync directions:

| Direction | Mechanism |
|---|---|
| Map click / marker drag → Livewire | `this.$wire.set(config.latField, val)` called immediately on each event |
| Livewire (text field) → Map pin | `this.$wire.$watch(config.latField, cb)` registered in `init()` |
| Livewire threshold/max fields → Circles | `this.$wire.$watch(config.thresholdField, cb)` and `$wire.$watch(config.maxDistField, cb)` |

**Scoring circle layers** (Leaflet):

- **Green circle** — radius = `threshold_km * 1000` metres. Represents the full-points zone. Drawn/updated whenever the pin moves or `thresholdField` changes. Hidden when the field is empty.
- **Faded red circle** — radius = `max_distance_km * 1000` metres. Represents the zero-points boundary. Same lifecycle. Hidden when the field is empty.

Both circles are managed as `L.circle()` instances on the map. On update, existing instances are repositioned/resized with `.setLatLng()` / `.setRadius()` rather than recreated, to avoid flicker.

**Config shape** (extended from current):
```js
{
  latField: string,        // Livewire property for latitude
  lngField: string,        // Livewire property for longitude
  thresholdField: string,  // Livewire property for threshold_km
  maxDistField: string,    // Livewire property for max_distance_km
  center: { lat, lng },
  zoom: int,
}
```

**Internal data properties added**:
- `thresholdCircle: null` — L.circle reference for the green threshold ring
- `maxCircle: null` — L.circle reference for the red max-distance ring

**Method changes**:
- `init()`: remove entangle calls; add `$wire.$watch` for all four fields; initial values read from `this.$wire[config.latField]` etc.
- `setup()`: unchanged except circles are drawn after initial marker placement
- Map click handler: call `this.$wire.set()` for lat and lng immediately after updating local properties
- Marker `dragend`: same
- New `reflectCircles()`: draw or reposition both circles from current lat/lng/thresholdKm/maxKm state
- `reflectFields()`: call `reflectCircles()` after marker update
- `destroy()`: unchanged (map.remove() already cleans up layers)

### `resources/views/livewire/quiz-builder.blade.php`

Extend the `geoPicker(…)` call in the geo_guesser section to pass the two new field names:

```html
x-data="geoPicker({
    latField: 'questionGeoLat',
    lngField: 'questionGeoLng',
    thresholdField: 'questionGeoThresholdKm',
    maxDistField: 'questionGeoMaxDistanceKm',
    center: { lat: 20, lng: 0 },
    zoom: 2
})"
```

No other blade changes required.

---

## Data flow (after fix)

```
User clicks map
  → Alpine: this.lat = value, this.lng = value
  → $wire.set(latField, value)      ← NEW — pushes to Livewire
  → $wire.set(lngField, value)      ← NEW
  → reflectFields() → setMarker + reflectCircles()

User types in text field
  → wire:model updates Livewire property
  → $wire.$watch(latField) fires    ← NEW
  → this.lat = newVal → reflectFields() → setMarker + reflectCircles()

User changes threshold_km field
  → wire:model updates Livewire
  → $wire.$watch(thresholdField) fires  ← NEW
  → this.thresholdKm = val → reflectCircles()
```

---

## Error handling / edge cases

- Circles are only drawn when a pin exists AND the km value is > 0. Empty fields → no circle.
- Very large radii (e.g. 5000 km) render as distorted ellipses on Mercator — acceptable for admin visualization.
- `setRadius()` / `setLatLng()` only called when the circle layer already exists; creation and removal are guarded.
- `$wire.$watch` callbacks guard against non-finite values (same as current `reflectFields()`).

---

## Testing

Existing unit tests in `GeoGuesserTypeTest.php` and `GeoGuesserGameFlowTest.php` cover backend logic and are unaffected. No new PHP tests required.

The fix is purely frontend. Manual smoke test:
1. Add a geo guesser question — click the map — verify lat/lng fields populate and the question saves with a correct location.
2. Edit the question — verify the pin loads at the saved location.
3. Type lat/lng into text fields — verify the pin moves on the map.
4. Enter threshold_km and max_distance_km — verify green and red circles appear around the pin and resize live.
5. Move the pin — verify circles follow.
