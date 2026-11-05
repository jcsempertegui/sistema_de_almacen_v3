<?php

namespace App\Http\Controllers;

use App\Services\GraficasPanel;
use App\Services\OperacionesPanel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControladorPanel extends Controller
{
    public function __invoke(Request $request, GraficasPanel $charts, OperacionesPanel $operations): View
    {
        return view('panel', ['charts' => $charts->paraUsuario($request->user()), 'operations' => $operations->paraUsuario($request->user())]);
    }
}
