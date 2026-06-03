<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@php
    $reverbConfig = [
        'key' => config('broadcasting.connections.reverb.key'),
        'host' => config('broadcasting.connections.reverb.options.host'),
        'port' => (int) config('broadcasting.connections.reverb.options.port', 443),
        'scheme' => config('broadcasting.connections.reverb.options.scheme', 'https'),
    ];
@endphp
<script type="application/json" id="reverb-config">
    @json($reverbConfig)
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
