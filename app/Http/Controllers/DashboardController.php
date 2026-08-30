<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $templateView = [];
        $templateView['fechaHoy'] = Carbon::now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY');

        return view('app.dashboard', $templateView);
    }
}
