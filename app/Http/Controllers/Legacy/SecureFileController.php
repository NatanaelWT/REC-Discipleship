<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Legacy\Concerns\RendersLegacyPages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SecureFileController extends Controller
{
    use RendersLegacyPages;

    public function show(Request $request): Response
    {
        return $this->legacy($request, 'secure_file');
    }
}
