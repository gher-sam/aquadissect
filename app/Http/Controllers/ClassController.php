<?php

namespace App\Http\Controllers;

use App\Support\ClassPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request, ClassPresenter $presenter): JsonResponse
    {
        return response()->json(['classes' => $presenter->for($request->user())]);
    }
}
