// Player identity persistence + presence heartbeat.
//
// `playerSession` runs on the play screen. It persists player_id to
// localStorage so a re-scan of the join code resumes the same identity, and
// pings the stateless heartbeat endpoint on an interval so the host can see who
// has dropped (a locked phone stops the interval → goes stale → "dropped";
// unlocking resumes it). When the server could not resolve the stored player_id
// (a reset game, or a code reused for a new session) it clears the stale key
// and bounces back to a clean join form.
//
// `joinResume` runs on the join screen: if a stored identity exists for this
// code, it redirects straight to the play screen instead of minting a new
// player.

const HEARTBEAT_MS = 5000;

export function playerSession(config) {
    return {
        timer: null,

        init() {
            if (config.resumeFailed) {
                // The id we arrived with no longer maps to a player here.
                localStorage.removeItem(config.storageKey);
                window.location.replace(config.joinUrl);
                return;
            }

            if (!config.playerId) {
                return; // A spectator / unjoined client — nothing to persist.
            }

            localStorage.setItem(config.storageKey, String(config.playerId));

            this.beat();
            this.timer = setInterval(() => this.beat(), HEARTBEAT_MS);
        },

        beat() {
            // sendBeacon survives a backgrounding/pagehide and needs no CSRF
            // token (the route is excluded). Best-effort: a dropped beat just
            // makes the next one count.
            try {
                navigator.sendBeacon(config.heartbeatUrl);
            } catch (e) {
                // Ignore — the staleness model self-heals on the next beat.
            }
        },

        forget() {
            // "Not you?" — drop the saved identity and start a fresh join.
            localStorage.removeItem(config.storageKey);
            window.location.assign(config.joinUrl);
        },

        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
            }
        },
    };
}

export function joinResume(config) {
    return {
        init() {
            const stored = localStorage.getItem(config.storageKey);
            if (stored) {
                window.location.replace(
                    config.playUrl + '?player_id=' + encodeURIComponent(stored),
                );
            }
        },
    };
}

export function registerPlayerSession(Alpine) {
    Alpine.data('playerSession', playerSession);
    Alpine.data('joinResume', joinResume);
}
