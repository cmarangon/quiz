// Player-side: a button row that throttles taps so a player cannot flood the
// spectator screen. One accepted tap per THROTTLE_MS; extra taps are ignored.
const THROTTLE_MS = 500;

export function registerReactionBar(Alpine) {
    Alpine.data('reactionBar', () => ({
        lastTap: 0,
        react(emoji) {
            const now = Date.now();
            if (now - this.lastTap < THROTTLE_MS) {
                return;
            }
            this.lastTap = now;
            this.$wire.react(emoji);
        },
    }));
}

// Spectator-side: subscribe directly to the Reverb channel (NOT through
// Livewire) and float each reaction up the screen. Bypassing Livewire keeps
// the TV smooth when reactions arrive in bursts.
const FLOAT_MS = 5000;        // keep in sync with the qz-react-float animation
const MAX_CONCURRENT = 40;    // bound the DOM on the long-lived spectator page

export function registerReactionFloat(Alpine) {
    Alpine.data('reactionFloat', (sessionId) => ({
        init() {
            if (!window.Echo) {
                return;
            }
            window.Echo.channel('game.' + sessionId)
                .listen('.reaction.sent', (e) => this.spawn(e.emoji));
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

            const remove = () => node.remove();
            node.addEventListener('animationend', remove);
            // Fallback in case animationend never fires (tab backgrounded, etc.).
            setTimeout(remove, FLOAT_MS + 500);

            layer.appendChild(node);
        },
    }));
}
