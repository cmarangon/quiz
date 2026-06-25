# Admin Question Comment Field

**Date:** 2026-06-25
**Status:** Approved

## Summary

Add an optional free-text comment field to each question, visible only to the admin (quiz builder and host dashboard). Never exposed to players or the spectator screen.

## Data Layer

- New migration: add nullable `comment TEXT` column to the `questions` table.
- Add `comment` to `Question::$fillable`. No cast needed (string passthrough).

## Quiz Builder

**Component (`QuizBuilder.php`):**
- Add `public string $questionComment = ''` property.
- `editQuestion()`: load `$question->comment ?? ''` into `$this->questionComment`.
- `saveQuestion()`: pass `'comment' => $this->questionComment ?: null` in both the create and update payloads.
- `resetQuestionForm()`: reset `$this->questionComment = ''`.
- No validation rule (field is optional, no length constraint).

**View (`quiz-builder.blade.php`):**
- In the question form (dashed-border panel), add a `<flux:textarea>` for the comment below the question body field. Label: "Admin note (private)". Rows: 2.
- In the question list (bullet list per category), when `$question->comment` is non-empty, render it as a muted italic line beneath the question body text.

## Host Dashboard

**Component (`HostDashboard.php`):**
- No new properties needed. The current question is already available on the component.
- Ensure the `comment` field is loaded when resolving the current question (eager-load or access via the existing model).

**View (`host-dashboard.blade.php`):**
- In the `playing` and `reviewing` phases, when the current question has a non-empty `comment`, render a visually distinct muted info panel labeled "Note:" directly below the question body display.
- The panel is only rendered server-side on the host screen — never broadcast, never on the spectator or player screens.

## Exclusions

- No broadcast of comment to players or spectator.
- No search or filter by comment.
- No per-user access control beyond existing quiz ownership (`user_id` check already in place).
- No comment length validation.
