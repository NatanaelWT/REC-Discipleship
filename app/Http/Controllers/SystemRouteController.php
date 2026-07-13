<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SystemRouteController extends Controller
{
    public function legacyIndex(): RedirectResponse
    {
        return redirect()->route('home');
    }

    public function legacyPeople(Request $request): RedirectResponse
    {
        return redirect()->route('discipleship.tree', $request->query());
    }

    public function notFound(): never
    {
        abort(404);
    }
}
