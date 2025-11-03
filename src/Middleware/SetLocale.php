<?php

namespace AngelitoSystems\FilamentTenancy\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Simplified: Only use APP_LOCALE from .env
        // Language switcher will be implemented later
        
        $locale = config('app.locale', 'en');
        
        // Ensure the locale is valid, otherwise use 'en' as fallback
        $availableLocales = array_keys(\AngelitoSystems\FilamentTenancy\Components\LanguageSwitcher::getAvailableLocales());
        if (!in_array($locale, $availableLocales)) {
            $locale = 'en'; // Fallback to English if locale is not available
        }
        
        App::setLocale($locale);

        return $next($request);
    }

}
