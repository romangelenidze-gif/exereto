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
        $locale = $request->route('locale');

        if (in_array($locale, ['ru', 'ka'], true)) {
            App::setLocale($locale);
            URL::defaults(['locale' => $locale]);
        } else {
            // Если префикса нет (null) — это English
            App::setLocale('en');
            URL::defaults(['locale' => null]);
        }

        // Убираем параметр, чтобы он не прокидывался в контроллеры и не ломал их параметры
        if ($request->route()) {
            $request->route()->forgetParameter('locale');
        }

        return $next($request);
    }
}