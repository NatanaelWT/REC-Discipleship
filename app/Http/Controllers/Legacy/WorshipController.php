<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Legacy\Concerns\RendersLegacyPages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorshipController extends Controller
{
    use RendersLegacyPages;

    public function penatalayan(Request $request): Response
    {
        return $this->legacy($request, 'worship_penatalayan');
    }

    public function penatalayanImage(Request $request): Response
    {
        return $this->legacy($request, 'worship_penatalayan_image');
    }
}
