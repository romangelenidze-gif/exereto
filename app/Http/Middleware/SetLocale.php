<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->segment(1);
        $supportedLocales = ['ru', 'en', 'ka']; // ru - по умолчанию, en - English, ka - Georgian

        // Если в URL нет языка (например exereto.com/product-search) — редиректим на язык по умолчанию (/ru/product-search)
        if (!in_array($locale, $supportedLocales, true)) {
            $segments = $request->segments();
            array_unshift($segments, 'ru'); // Язык по умолчанию
            return redirect()->to(implode('/', $segments) . ($request->getQueryString() ? '?' . $request->getQueryString() : ''));
        }

        // Устанавливаем язык для приложения
        App::setLocale($locale);

        // МАГИЯ: Заставляем Laravel автоматически подставлять этот язык во ВСЕ функции route()
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}