<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from multiple sources with priority:
        // 1. Query parameter (highest priority)
        // 2. Accept-Language header (for AJAX requests)
        // 3. Session
        // 4. Default config
        $locale = $request->query('locale') ?:
                 $request->header('Accept-Language') ?:
                 $request->session()->get('locale') ?:
                 config('app.locale', 'vi');

        // Validate locale
        if (in_array($locale, ['vi', 'en'])) {
            App::setLocale($locale);
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }
}
