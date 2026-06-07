import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { registerChoiceAnswer } from './choice-answer';
import { registerGeoMap } from './geo-map';
import { registerGeoPicker } from './geo-picker';
import { registerOrdering } from './ordering';
import { registerQuestionTimer } from './question-timer';
import { registerReactionBar, registerReactionFloat } from './reactions';

window.Pusher = Pusher;

document.addEventListener('alpine:init', () => {
    registerChoiceAnswer(window.Alpine);
    registerGeoMap(window.Alpine);
    registerGeoPicker(window.Alpine);
    registerOrdering(window.Alpine);
    registerQuestionTimer(window.Alpine);
    registerReactionBar(window.Alpine);
    registerReactionFloat(window.Alpine);
});


const reverbConfigElement = document.getElementById('reverb-config');
const reverbConfig = reverbConfigElement ? JSON.parse(reverbConfigElement.textContent) : {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbConfig.key,
    wsHost: reverbConfig.host,
    wsPort: reverbConfig.port ?? 80,
    wssPort: reverbConfig.port ?? 443,
    forceTLS: (reverbConfig.scheme ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
