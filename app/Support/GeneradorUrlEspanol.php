<?php

namespace App\Support;

use Illuminate\Routing\UrlGenerator;

class GeneradorUrlEspanol extends UrlGenerator
{
    public function toRoute($route, $parameters, $absolute)
    {
        $url = parent::toRoute($route, $parameters, $absolute);

        return in_array('GET', $route->methods(), true) ? RutasEspanol::urlPublica($url) : $url;
    }

    public function to($path, $extra = [], $secure = null)
    {
        $url = parent::to($path, $extra, $secure);

        // Las URL absolutas externas y las peticiones antiguas explícitas se respetan.
        return $this->isValidUrl($path) ? $url : RutasEspanol::urlPublica($url);
    }
}
