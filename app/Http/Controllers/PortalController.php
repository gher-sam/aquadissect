<?php

namespace App\Http\Controllers;

use App\Support\PortalData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    public function __invoke(Request $request, PortalData $portalData)
    {
        $page = $request->route('page', 'login');
        if ($page === 'login' && $request->user()) return redirect()->route('portal.home');
        return response()->view('app', [
            'page' => $page,
            'bootstrap' => $portalData->for($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }
}
