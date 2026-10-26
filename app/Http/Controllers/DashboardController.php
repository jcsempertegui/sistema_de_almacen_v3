<?php

namespace App\Http\Controllers;

use App\Services\DashboardCharts;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardCharts $charts): View
    {
        return view('dashboard', ['charts' => $charts->forUser($request->user())]);
    }
}
