<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $firstSegment = $request->segment(1);

        if (in_array($firstSegment, ['ru', 'ka'], true)) {
            App::setLocale($firstSegment);
        } else {
            App::setLocale('en');
        }

        return $next($request);
    }
}