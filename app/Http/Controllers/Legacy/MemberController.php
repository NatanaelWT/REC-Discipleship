<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Legacy\Concerns\RendersLegacyPages;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MemberController extends Controller
{
    use RendersLegacyPages;

    public function dashboard(Request $request): Response
    {
        return $this->legacy($request, 'member_dashboard');
    }

    public function index(Request $request): Response
    {
        return $this->legacy($request, 'members');
    }

    public function completeness(Request $request): Response
    {
        return $this->legacy($request, 'member_completeness');
    }

    public function families(Request $request): Response
    {
        return $this->legacy($request, 'member_families');
    }

    public function birthdays(Request $request): Response
    {
        return $this->legacy($request, 'member_birthdays');
    }
}
