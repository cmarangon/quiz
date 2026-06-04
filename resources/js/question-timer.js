/**
 * Alpine component backing the player's question countdown.
 *
 * config: { limit: number (seconds), startedAt: number|null (epoch ms) }
 *
 * The countdown is purely visual — the server is authoritative for enforcing
 * the time limit. When time runs out it asks the Livewire component to record a
 * timeout (markTimedOut). If the player had a pending selection (registered by a
 * nested answer component via the `answer-provider` event), that selection is
 * passed along so it can be auto-submitted instead of wasting the round.
 */
export function questionTimer(config) {
    return {
        limitMs: (config.limit || 30) * 1000,
        startedAt: config.startedAt || Date.now(),
        remaining: config.limit || 30,
        fraction: 0,
        expired: false,
        answerProvider: null,
        _timer: null,

        init() {
            // Defer the first tick so nested answer components have a chance to
            // register their selection provider before a possible instant expiry.
            this.$nextTick(() => {
                this.tick();
                this._timer = setInterval(() => this.tick(), 200);
            });
        },

        tick() {
            const elapsed = Date.now() - this.startedAt;
            this.fraction = Math.min(1, Math.max(0, elapsed / this.limitMs));
            this.remaining = Math.max(0, Math.ceil((this.limitMs - elapsed) / 1000));

            if (this.fraction >= 1 && !this.expired) {
                this.expired = true;
                this.stop();
                if (this.$wire) {
                    const answer = this.answerProvider ? this.answerProvider() : null;
                    this.$wire.markTimedOut(answer);
                }
            }
        },

        stop() {
            if (this._timer) {
                clearInterval(this._timer);
                this._timer = null;
            }
        },

        destroy() {
            this.stop();
        },
    };
}

export function registerQuestionTimer(Alpine) {
    Alpine.data('questionTimer', questionTimer);
}
