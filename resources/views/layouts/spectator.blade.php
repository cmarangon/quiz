<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@500;600;700;800&family=Bangers&family=Cabin+Sketch:wght@700&family=Fredoka:wght@400;500;600;700&family=MedievalSharp&family=Nunito:wght@700;800&family=Patrick+Hand&family=Sniglet:wght@400;800&family=Spectral:wght@500;600;700&family=Teko:wght@600;700&display=swap" rel="stylesheet">
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="min-h-svh w-full">
            {{ $slot }}
        </div>
        @fluxScripts
    </body>
</html>
