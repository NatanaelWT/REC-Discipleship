<?php

namespace App\Http\Controllers\Discipleship;

use App\Http\Controllers\Controller;
use App\Services\DiscipleshipGroups\DiscipleshipGroupIndexData;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request, DiscipleshipGroupIndexData $groupIndexData): View
    {
        RuntimeBootstrap::boot($request);

        $pageData = $groupIndexData->forCurrentContext($request);

        return view('discipleship.groups.index', $pageData);
    }

    public function rows(Request $request, DiscipleshipGroupIndexData $groupIndexData): JsonResponse
    {
        RuntimeBootstrap::boot($request);

        $data = $groupIndexData->paginatedRowsForCurrentContext($request);

        return response()->json([
            'html' => view('discipleship.groups.partials.rows', ['groups' => $data['groups']])->render(),
            'has_more' => (bool) ($data['hasMoreGroupRows'] ?? false),
            'next_page' => $data['nextGroupPage'] ?? null,
            'stats' => [
                'total' => (int) ($data['totalGroupRows'] ?? 0),
                'dg1' => (int) ($data['groupsInDg1Count'] ?? 0),
                'dg2' => (int) ($data['groupsInDg2Count'] ?? 0),
                'dg3' => (int) ($data['groupsInDg3Count'] ?? 0),
            ],
            'empty_message' => (string) ($data['groupsEmptyMessage'] ?? 'Kelompok tidak ditemukan.'),
        ]);
    }
}
