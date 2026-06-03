<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Anton&family=Baloo+2:wght@500;600;700;800&family=Bangers&family=Cabin+Sketch:wght@700&family=Fredoka:wght@400;500;600;700&family=MedievalSharp&family=Nunito:wght@700;800&family=Patrick+Hand&family=Sniglet:wght@400;800&family=Spectral:wght@500;600;700&family=Teko:wght@600;700&display=swap" rel="stylesheet">
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="fixed top-4 right-4 z-50">
            <a href="{{ route('locale.switch', app()->getLocale() === 'de' ? 'en' : 'de') }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium text-zinc-600 dark:text-zinc-300 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition-colors">
                {{ app()->getLocale() === 'de' ? 'EN' : 'DE' }}
            </a>
        </div>
        <div class="flex min-h-svh flex-col items-center justify-center p-6">
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </div>
        @fluxScripts
    </body>
</html>
