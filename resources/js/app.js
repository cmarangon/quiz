import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { registerGeoMap } from './geo-map';
import { registerOrdering } from './ordering';

window.Pusher = Pusher;

document.addEventListener('alpine:init', () => {
    registerGeoMap(window.Alpine);
    registerOrdering(window.Alpine);
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
