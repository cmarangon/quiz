/**
 * Alpine component backing the match-pairs question's tap-to-pair UI.
 *
 * config: { left: [{kind, value}], right: [{kind, value}] }  // value is text
 * or a fully-resolved image URL — resolved server-side in the Blade partial.
 *
 * Tap a left item, then a right item, to lock a pair. Tapping either side of
 * an already-locked pair unlocks it so the player can redo it. No
 * drag-and-drop: this needs to work reliably on a phone.
 */
export function matchPairs(config) {
    return {
        left: [],
        right: [],
        pairs: [],
        selectedLeft: null,
        submitted: false,

        init() {
            this.left = Array.from(config.left || []);
            this.right = Array.from(config.right || []);
            this.pairs = this.left.map(() => null);

            // Hand the current (possibly incomplete) pairing to the countdown
            // timer so it can be auto-submitted if the player runs out of time.
            this.$dispatch('answer-provider', { provider: () => Array.from(this.pairs) });
        },

        isComplete() {
            return this.pairs.length > 0 && this.pairs.every((v) => v !== null);
        },

        pairedWith(leftIndex) {
            return this.pairs[leftIndex];
        },

        rightUsedAt(rightIndex) {
            return this.pairs.findIndex((v) => v === rightIndex);
        },

        tapLeft(index) {
            if (this.submitted) {
                return;
            }

            if (this.pairs[index] !== null) {
                this.pairs[index] = null;
                this.selectedLeft = null;
                return;
            }

            this.selectedLeft = index;
        },

        tapRight(index) {
            if (this.submitted) {
                return;
            }

            const usedAt = this.rightUsedAt(index);
            if (usedAt !== -1) {
                this.pairs[usedAt] = null;
                if (usedAt === this.selectedLeft) {
                    this.selectedLeft = null;
                }
                return;
            }

            if (this.selectedLeft === null) {
                return;
            }

            this.pairs[this.selectedLeft] = index;
            this.selectedLeft = null;
        },

        colorFor(leftIndex) {
            const palette = ['a', 'b', 'c', 'd'];
            return palette[leftIndex % palette.length];
        },

        submit() {
            if (this.submitted || ! this.isComplete()) {
                return;
            }
            this.submitted = true;
            this.$wire.submitMatches(Array.from(this.pairs));
        },
    };
}

export function registerMatchPairs(Alpine) {
    Alpine.data('matchPairs', matchPairs);
}
