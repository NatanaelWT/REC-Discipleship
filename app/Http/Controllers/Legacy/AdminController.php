<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Legacy\Concerns\RendersLegacyPages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminController extends Controller
{
    use RendersLegacyPages;

    public function dashboard(Request $request): Response
    {
        return $this->legacy($request, 'dashboard');
    }

    public function settings(Request $request): Response
    {
        return $this->legacy($request, 'settings');
    }
}
