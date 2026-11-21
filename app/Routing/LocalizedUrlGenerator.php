<?php

namespace App\Routing;

use Illuminate\Routing\UrlGenerator;

class LocalizedUrlGenerator extends UrlGenerator
{
    private const PREFIXED_LOCALES = ['ru', 'ka'];

    public function route($name, $parameters = [], $absolute = true)
    {
        $routeName = (string) $name;
        $locale = app()->getLocale();

        if (
            in_array($locale, self::PREFIXED_LOCALES, true)
            && !str_starts_with($routeName, $locale . '.')
            && $this->routes->hasNamedRoute($locale . '.' . $routeName)
        ) {
            $routeName = $locale . '.' . $routeName;
        }

        return parent::route($routeName, $parameters, $absolute);
    }
}