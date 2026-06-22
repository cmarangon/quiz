# Match Pairs Question Type — Design

**Date:** 2026-06-21
**Branch:** `feature/match-pairs`

## Problem

The quiz supports four question types (`multiple_choice`, `true_false`, `ordering`, `geo_guesser`), all single-column. There's no question type that asks a player to connect related items across two columns — e.g. "match the flag to its country," or "match the capital to its country." This adds a fifth type, `match_pairs`, fixed at exactly 4 pairs (8 items) for this MVP.

## Decisions

- **Exactly 4 pairs, not a variable-count "matching" type.** Keeps authoring UI and scoring simple; a variable count is a clear future extension if needed.
- **Each side (left/right) of each pair is independently text or image.** Supports flag↔country-name and flag↔flag in the same mechanism — render rule per item is just "image if `kind === 'image'`, else text."
- **Images are uploaded files, not URLs.** No image upload infrastructure exists anywhere in the app yet (`grep` confirms no `WithFileUploads`, no storage symlink, no image columns). This is the first feature to add it.
- **Interaction: tap-to-select pairing**, not drag-and-drop. Tap a left item, then tap a right item to lock the pair; tapping either side of an already-locked pair unlocks it so the player can redo. Most reliable on touch/phone, no extra JS drag plumbing needed.
- **Submit flow: explicit "Submit matches" button**, enabled only once all 4 pairs are locked — mirrors `ordering`'s submit-when-ready pattern, not `multiple_choice`'s instant-submit-on-tap.
- **Scoring: all-or-nothing.** All 4 pairs must be correct to earn any points, same as `ordering`. No partial credit for this MVP.
- **Only the right column is shuffled at save time; the left column keeps the author's entry order.** Shuffling one side is sufficient to stop position-alignment from leaking the answer (if both sides kept author order, "left[i] pairs with right[i]" would just be the literal correct answer), and it's simpler than shuffling both sides and tracking two permutations.

## Data model

No new tables. `questions.type = 'match_pairs'`; `options` and `correct_answer` (existing `json` columns) take a new shape, following the same "never leak the answer via the broadcast `options`" rule `ordering` already follows.

```jsonc
// options — what's broadcast to player/spectator; never reveals the pairing
{
  "left":  [
    {"kind": "text",  "value": "France"},
    {"kind": "image", "value": "questions/9f3a1c2e.webp"},
    {"kind": "text",  "value": "Japan"},
    {"kind": "image", "value": "questions/7b2d4f10.webp"}
  ],
  "right": [   // shuffled at save time relative to the pairing below
    {"kind": "image", "value": "questions/0c88e2aa.webp"},
    {"kind": "text",  "value": "Paris"},
    {"kind": "image", "value": "questions/4a1199de.webp"},
    {"kind": "text",  "value": "Tokyo"}
  ]
}

// correct_answer — correct_answer[leftIndex] = rightIndex; never broadcast until review
[1, 0, 3, 2]
```

`kind` is `'text'` or `'image'`. For `'image'`, `value` is a path on the `public` disk (relative to `storage/app/public`), resolved to a full URL with `Storage::url()` wherever it's rendered (player/spectator blades, quiz builder edit form) — never baked into the broadcast payload as an absolute URL, so the disk URL config stays the single source of truth.

**Submitted answer shape** (mirrors `correct_answer`): a length-4 array where `submitted[leftIndex] = rightIndex`, built progressively client-side as the player locks pairs, sent whole on submit. `validateAnswer` is an exact array comparison, exactly like `OrderingType::validateAnswer`'s `$submitted === $correct`.

## Architecture

### `App\QuestionTypes\MatchPairsType implements QuestionTypeInterface`

- `renderPlayerComponent()` → `'question-types.match-pairs-player'`
- `renderSpectatorComponent()` → `'question-types.match-pairs-spectator'`
- `validateAnswer($answer, $question)`: normalize `$answer` to a length-4 list of ints (reject non-array, wrong length, non-int/non-numeric entries — same defensiveness as `OrderingType::normalizeSequence`), compare to `$question->correct_answer` for exact equality.
- `scoreFactor`: `1.0` if `validateAnswer`, else `0.0`.
- `calculatePoints`: identical linear time-bonus decay to `OrderingType::calculatePoints` (`base * remaining/limit`, or full base when time bonus disabled).
- `validateOptions(array $options)`: `left` and `right` keys must each be a list of exactly 4 items; each item must be an array with `kind` in `['text', 'image']` and a non-empty string `value`.

Registered in `config/quiz.php`:
```php
'match_pairs' => MatchPairsType::class,
```

### Image storage

New `App\Services\QuestionImageStorage`, a small dedicated class so store/delete logic is unit-testable in isolation rather than embedded in `QuizBuilder`:

- `store(UploadedFile $file): string` → `$file->store('questions', 'public')`, returns the relative path to persist as an item's `value`.
- `delete(string $path): void` → `Storage::disk('public')->delete($path)`, swallows a missing-file edge case (already gone) rather than throwing.

Used by `QuizBuilder` on save (new upload) and on question delete / image replacement (old file cleanup).

### `App\Livewire\QuizBuilder`

Adds `use Livewire\WithFileUploads;`.

New public state, only populated/used when `questionType === 'match_pairs'`:
```php
public array $questionPairs = [
    ['left' => ['kind' => 'text', 'text' => '', 'image' => null, 'existingImage' => null],
     'right' => ['kind' => 'text', 'text' => '', 'image' => null, 'existingImage' => null]],
    // ... 4 rows total, fixed — no add/remove row buttons since the count is fixed at 4
];
```
`image` holds a `Livewire\Features\SupportFileUploads\TemporaryUploadedFile` while the form is open and the author has picked a new file; `existingImage` holds the already-stored relative path when editing a question that already has an image on that slot. A toggle button per side flips `kind` between `text`/`image`.

**Validation** (added to `saveQuestion()`'s rule set when `questionType === 'match_pairs'`):
```php
'questionPairs' => 'required|array|size:4',
'questionPairs.*.left.kind' => 'required|in:text,image',
'questionPairs.*.right.kind' => 'required|in:text,image',
'questionPairs.*.left.text' => 'required_if:questionPairs.*.left.kind,text|string',
'questionPairs.*.right.text' => 'required_if:questionPairs.*.right.kind,text|string',
'questionPairs.*.left.image' => 'required_without:questionPairs.*.left.existingImage|image|mimes:jpeg,png,webp,gif|max:2048',
'questionPairs.*.right.image' => 'required_without:questionPairs.*.right.existingImage|image|mimes:jpeg,png,webp,gif|max:2048',
```
(Exact rule syntax for the wildcard `required_if`/`required_without` combo will be confirmed against Laravel's nested-array validator during implementation — the intent is: text slots need non-empty text, image slots need either a fresh upload or a kept existing image.)

**`buildQuestionPayload()`** gains a `match_pairs` branch:
1. For each of the 4 pairs, resolve the left/right `value`: if `kind === 'image'` and a new `image` upload is present, store it via `QuestionImageStorage::store()` and use the returned path; if `kind === 'image'` and no new upload, keep `existingImage`; if `kind === 'text'`, use the trimmed `text`.
2. Build `left` array in pair order (unshuffled).
3. Build `right` array as a shuffled copy of the same 4 right-side values, using the same bounded-shuffle-with-rotation-fallback technique `OrderingType`/`QuizBuilder::buildQuestionPayload` already uses for `ordering`, to guarantee the shuffled order actually differs from the identity order whenever the values aren't all identical.
4. Compute `correct_answer[leftIndex] = position of that pair's right value in the shuffled right array`.
5. Return `[['left' => ..., 'right' => ...], $correctAnswerArray]`.

**Editing an existing `match_pairs` question** (`editQuestion()`): reconstruct `$this->questionPairs` by reading `options.left[i]` and `options.right[correct_answer[i]]` for `i = 0..3` — this naturally reproduces the 4 original pairs (in left-index order, which may not be the exact order the author first typed them, but is the same set of pairs), setting `existingImage` for any `kind === 'image'` slot and leaving `image` null until the author chooses to replace it.

**Deleting a question** or **replacing an image on edit**: call `QuestionImageStorage::delete()` for every `kind === 'image'` slot's old path that's no longer referenced after the save/delete completes. `Question` has no `deleting` model event currently — add one scoped to `match_pairs` (or a generic one that no-ops for other types) so deleting a question doesn't orphan its uploaded images.

Also add `'match_pairs'` to the `in:` list of allowed types in `saveQuestion()`'s `questionType` rule, and to `questionTypes` wherever the type picker enumerates `config('quiz.question_types')` (already automatic, since that list is read from config).

### Player view

`resources/views/question-types/match-pairs-player.blade.php` (themed + plain variants, matching `ordering-player.blade.php`'s `@if($isThemed)` split) + `resources/js/match-pairs.js`:

```js
export function matchPairs(config) {
  // config: { left: [{kind,value}], right: [{kind,value}] }
  return {
    left: [], right: [],
    selectedLeft: null,
    pairs: [null, null, null, null], // leftIndex -> rightIndex | null
    submitted: false,

    init() { /* seed left/right from config; dispatch answer-provider like orderingList */ },
    tapLeft(index) { /* select, or unlock+reselect if already paired */ },
    tapRight(index) { /* lock pair with selectedLeft, or unlock if index already used */ },
    isComplete() { return this.pairs.every(v => v !== null); },
    submit() { /* $wire.submitMatches(this.pairs) */ },
  };
}
```

Renders two columns side by side (stacked on narrow viewports, consistent with the existing `qz-` theme CSS breakpoints). Each item renders `<img src="...">` when `kind === 'image'` (URL resolved server-side via `Storage::url()` in the blade, same as the spectator view) or the text label otherwise. Locked pairs get a shared color/letter badge, same visual language as `ordering`'s lettered list items. Submit button disabled until `isComplete()`.

Like `orderingList`, dispatches `answer-provider` on init so the countdown-timer's auto-submit-on-timeout path (`PlayerScreen::markTimedOut`) can pull the current (possibly incomplete) `pairs` state — a player who ran out of time with some pairs locked still gets those submitted rather than nothing. An incomplete `pairs` array (containing `null`) is treated as "no answer" by `validateAnswer` since it fails the length/type normalization, same way `ordering` already handles a malformed timeout submission.

### Spectator/host view

`resources/views/question-types/match-pairs-spectator.blade.php`, same `question`/`review` phase split as `ordering-spectator.blade.php`:

- **`question` phase:** both columns shown unpaired (no indication of correct pairing), "Players are matching pairs..." hint, answered-count/timer footer — same layout primitives as `ordering-spectator`.
- **`review` phase:** the 4 correct pairs shown connected — left item and its matched right item rendered adjacent with a shared color badge and a checkmark, plus the leaderboard block (`@if(! empty($scores ?? []))`), copying `ordering-spectator`'s review section structure.

No new broadcast events — reuses `QuestionStarted`/`QuestionEnded` exactly as `ordering` does, since `options`/`correct_answer` already flow through those payloads generically.

### `App\Livewire\PlayerScreen`

New method, directly mirroring `submitOrder`:

```php
public function submitMatches(array $pairs): void
{
    if (! $this->player || ! $this->currentQuestion) {
        return;
    }

    $action = app(SubmitAnswer::class);

    try {
        $this->lastResult = $action->execute(
            $this->session,
            $this->player,
            $this->currentQuestion['question_id'],
            array_values($pairs),
            $this->elapsedMs(),
        );
    } catch (\LogicException $e) {
        $this->timedOut = true;
    }

    $this->phase = 'answered';
}
```

No changes needed to `SubmitAnswer`, `ScoringService`, `QuestionTypeRegistry`, or any broadcast event — the existing generic flow (`$registry->resolve($question->type)` → `validateAnswer`/`scoreFactor`/`calculatePoints`) already handles a new type with zero changes once it's registered in `config/quiz.php`.

### Database factory

`QuestionFactory::matchPairs()` state, following the `ordering()` state's shape, for use in tests.

## Deployment

This is the first feature writing to the `public` disk, which matters given the known Falkenstein production quirks ([[prod-host-falkenstein-quiz]]):

- Add `php artisan storage:link` to `deploy.sh` (idempotent — Laravel skips it if the symlink already exists), right after `composer install`.
- **Flag, don't fix blind:** PHP-FPM on prod runs as user `quiz`; nginx workers run as `www-data`. If nginx serves `/storage/*` directly as static files (bypassing PHP-FPM, which is typical for a Laravel `public` disk symlink), uploaded files written by the `quiz` user must be readable by `www-data`. This needs a manual permissions check on the first prod deploy after this feature ships (e.g. `ls -la storage/app/public/questions` and confirm `www-data` can read it) rather than a blind `chmod` baked into `deploy.sh` without seeing the actual nginx config.

## Testing

**`tests/Unit/QuestionTypes/MatchPairsTypeTest.php`** (Pest, mirroring `OrderingTypeTest.php`):
- exact match validates as correct
- a swapped pair is rejected (all-or-nothing — no partial credit for 3/4 correct)
- wrong length / non-array / null / empty answers rejected
- `validateOptions`: requires exactly 4 left + 4 right items; rejects missing `kind`, invalid `kind`, empty `value`
- `calculatePoints`: time-bonus scaling and the bonus-disabled full-base case, same two cases as `OrderingTypeTest`
- `renderPlayerComponent`/`renderSpectatorComponent` return the right view names

**`tests/Feature/MatchPairsGameFlowTest.php`** (Pest, mirroring `OrderingGameFlowTest.php`):
- a fully correct submission earns full points and increments streak
- an incorrect submission earns zero points and resets streak
- a partially correct (3/4) submission earns zero points (all-or-nothing)

**`tests/Unit/QuestionTypeRegistryTest.php`**: extend the `registered()` list assertion to include `match_pairs`.

**New `tests/Feature/QuestionImageStorageTest.php`** (or fold into a `QuizBuilderMatchPairsTest.php` covering the full authoring flow): uploading an image stores it on the `public` disk and persists the relative path; deleting a question with image pairs removes the stored files; replacing an image on edit deletes the old file and stores the new one. Uses `Storage::fake('public')` per Laravel's standard upload-testing pattern.

No Playwright/e2e coverage is added here — per [[e2e-targets-remote-host]], e2e only runs against the deployed remote, not locally, so manual verification against the existing `match_pairs` flow is the practical check before merging this kind of UI-heavy change.

## i18n

New strings added to `lang/de.json` (English source via `__()`, matching the existing pattern):

| English | German |
|---|---|
| "Tap a card on the left, then its match on the right." | "Tippe eine Karte links an, dann ihre Übereinstimmung rechts." |
| "Submit matches" | "Zuordnung abschicken" |
| "Players are matching pairs..." | "Spieler ordnen Paare zu..." |
| "Correct pairs:" | "Richtige Paare:" |

## Out of scope

- Variable pair counts (more or fewer than 4). A clean follow-up once the fixed-4 mechanism is proven.
- Partial credit scoring (e.g. 75% for 3/4 correct pairs).
- Image URLs as an alternative to upload (decided against — upload-only for this MVP, per the "build file upload support" choice).
- Drag-and-drop interaction (decided against — tap-to-select only).
- A generic/reusable image-upload field for other question types (e.g. illustrating a `multiple_choice` question) — this design only wires up uploads for `match_pairs`; broader reuse is a future refactor if a second type needs images.
