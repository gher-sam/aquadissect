<?php

namespace App\Http\Controllers;

use App\Support\PortalData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __invoke(Request $request, PortalData $portalData): View
    {
        return view('app', [
            'bootstrap' => $portalData->for($request->user()),
        ]);
    }
}
