<?php

namespace App\Http\Middleware;

use App\Support\RutasEspanol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirigirRutasAntiguas
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            $camino = $request->getPathInfo();
            $nuevo = RutasEspanol::camino($camino);
            $originales = $request->query->all();
            $publicos = RutasEspanol::consultaPublica($originales);
            if (rtrim($camino, '/') !== rtrim($nuevo, '/') || $publicos !== $originales) {
                $consulta = http_build_query($publicos, '', '&', PHP_QUERY_RFC3986);

                return redirect()->to($request->getBaseUrl().$nuevo.($consulta ? '?'.$consulta : ''), 302);
            }
            $request->query->replace(RutasEspanol::consultaInterna($publicos));
        }

        return $next($request);
    }
}
