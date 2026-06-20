<?php

namespace App\Services\DiscipleshipDashboard;

use Illuminate\Http\Request;

class DashboardPageData
{
    public function __construct(
        private readonly DiscipleshipDashboardSummaryQuery $summary,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCurrentContext(Request $request): array
    {
        $data = $this->summary->get();
        $data['settings'] = ['church_name' => app_church_name()];

        return $data;
    }
}
