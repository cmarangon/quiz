// Player-side: a button row that throttles taps so a player cannot flood the
// spectator screen. One accepted tap per THROTTLE_MS; extra taps are ignored.
const THROTTLE_MS = 500;

export function reactionBar() {
    return {
        lastTap: 0,
        react(emoji) {
            const now = Date.now();
            if (now - this.lastTap < THROTTLE_MS) {
                return;
            }
            this.lastTap = now;
            this.$wire.react(emoji);
        },
    };
}

export function registerReactionBar(Alpine) {
    Alpine.data('reactionBar', reactionBar);
}

// Spectator-side: subscribe directly to the Reverb channel (NOT through
// Livewire) and float each reaction up the screen. Bypassing Livewire keeps
// the TV smooth when reactions arrive in bursts.
const FLOAT_MS = 5000;        // keep in sync with the qz-react-float animation
const MAX_CONCURRENT = 40;    // bound the DOM on the long-lived spectator page

export function reactionFloat(sessionId) {
    return {
        channelName: 'game.' + sessionId,
        channel: null,
        init() {
            // window.Echo is assigned synchronously in app.js before Alpine
            // walks the DOM, so it is normally present here; the guard only
            // protects against a future load-order change.
            if (!window.Echo) {
                return;
            }
            this.channel = window.Echo.channel(this.channelName);
            this.channel.listen('.reaction.sent', (e) => this.spawn(e.emoji));
        },
        destroy() {
            // Remove only our reaction listener so a Livewire re-mount of the
            // long-lived spectator page does not stack duplicate handlers. We
            // must NOT leaveChannel(): Echo caches one channel instance per
            // name, and the spectator's Livewire component shares this exact
            // 'game.{id}' channel for its lifecycle events — tearing the whole
            // channel down would kill all real-time spectator updates.
            if (this.channel) {
                this.channel.stopListening('.reaction.sent');
                this.channel = null;
            }
        },
        spawn(emoji) {
            const layer = this.$el;

            while (layer.children.length >= MAX_CONCURRENT) {
                layer.removeChild(layer.firstElementChild);
            }

            const node = document.createElement('span');
            node.className = 'qz-react';
            node.textContent = emoji;
            // Random horizontal start and a small drift target for variety.
            node.style.left = (5 + Math.random() * 90) + '%';
            node.style.setProperty('--qz-react-drift', (Math.random() * 80 - 40) + 'px');

            let removed = false;
            const remove = () => {
                if (removed) {
                    return;
                }
                removed = true;
                node.removeEventListener('animationend', remove);
                node.remove();
            };
            node.addEventListener('animationend', remove);
            // Fallback in case animationend never fires (tab backgrounded, etc.).
            setTimeout(remove, FLOAT_MS + 500);

            layer.appendChild(node);
        },
    };
}

export function registerReactionFloat(Alpine) {
    Alpine.data('reactionFloat', reactionFloat);
}
