<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($locale = $request->session()->get('locale')) {
            if (in_array($locale, config('app.supported_locales'), true)) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}
