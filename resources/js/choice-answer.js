/**
 * Alpine component backing the true/false answer (select, then submit).
 *
 * Unlike multiple choice — which submits the instant a player taps an option —
 * true/false works like the ordering and geo-guesser questions: the player
 * picks an option, which only stages the choice, then confirms with a submit
 * button. The staged choice is also handed to the countdown timer so it can be
 * auto-submitted if the player runs out of time before pressing submit.
 *
 * config: { selected?: string|null }
 */
export function choiceAnswer(config = {}) {
    return {
        selected: config.selected ?? null,
        submitted: false,

        init() {
            this.$dispatch('answer-provider', { provider: () => this.selected });
        },

        choose(label) {
            if (this.submitted) {
                return;
            }
            this.selected = label;
        },

        submit() {
            if (this.submitted || this.selected === null) {
                return;
            }
            this.submitted = true;
            this.$wire.submitAnswer(this.selected);
        },
    };
}

export function registerChoiceAnswer(Alpine) {
    Alpine.data('choiceAnswer', choiceAnswer);
}
