<?php

namespace App\Http\Controllers\Worship;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorshipServiceSchedules\DeleteWorshipServiceScheduleRequest;
use App\Http\Requests\WorshipServiceSchedules\StoreWorshipServiceScheduleRequest;
use App\Services\Routing\CompatibilityRouteMap;
use App\Services\WorshipServiceSchedules\WorshipServiceScheduleBuilder;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceScheduleController extends Controller
{
    public function index(Request $request, WorshipServiceScheduleBuilder $scheduleBuilder): RedirectResponse|View
    {
        $pageQuery = trim((string) $request->query('page', ''));
        if ($pageQuery !== '' && CompatibilityRouteMap::hasPage($pageQuery)) {
            return redirect()->away($request->getSchemeAndHttpHost() . CompatibilityRouteMap::pageUrl($pageQuery, $request->query()));
        }

        RuntimeBootstrap::boot($request);

        if (! is_logged_in()) {
            return redirect()->route('auth.login');
        }

        if (! branch_can_access_page(current_user_branch(), 'worship_penatalayan')) {
            return redirect(CompatibilityRouteMap::pageUrl(branch_home_page(current_user_branch()), ['error' => 'access_denied']));
        }

        $selectedMonth = normalize_month_value((string) $request->query('month', date('Y-m')));
        $schedules = $scheduleBuilder->allRecords();
        $selectedExistingSchedule = $this->recordByMonth($schedules, $selectedMonth);
        $selectedSchedule = $scheduleBuilder->buildSchedule($selectedMonth, $selectedExistingSchedule);
        $selectedWeekDates = is_array($selectedSchedule['week_dates'] ?? null) ? $selectedSchedule['week_dates'] : [];
        $serviceCounts = worship_penatalayan_service_counts($selectedSchedule);
        $historicalNames = worship_penatalayan_historical_service_names($schedules);
        $displayStewardNames = $this->displayStewardNames($historicalNames, $serviceCounts);
        $historicalNamesJson = json_encode(
            array_values($historicalNames),
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT,
        );
        if (! is_string($historicalNamesJson)) {
            $historicalNamesJson = '[]';
        }

        $lastUpdatedAt = trim((string) ($selectedSchedule['updated_at'] ?? ''));
        $lastUpdatedDateValue = normalize_ymd_date($lastUpdatedAt);
        $lastUpdatedStatLabel = $lastUpdatedDateValue !== ''
            ? (format_short_indo_date($lastUpdatedDateValue) . ' ' . substr($lastUpdatedDateValue, 0, 4))
            : 'Belum ada';

        return view('worship.service-schedules.index', [
            'settings' => ['church_name' => app_church_name()],
            'saved' => $request->query->has('saved'),
            'deleted' => $request->query->has('deleted'),
            'errorCode' => trim((string) $request->query('error', '')),
            'selectedMonth' => $selectedMonth,
            'selectedSchedule' => $selectedSchedule,
            'selectedWeekDates' => $selectedWeekDates,
            'selectedExistingSchedule' => $selectedExistingSchedule,
            'serviceCounts' => $serviceCounts,
            'displayStewardNames' => $displayStewardNames,
            'historicalNamesJson' => $historicalNamesJson,
            'totalStewardMonths' => count($schedules),
            'lastUpdatedStatLabel' => $lastUpdatedStatLabel,
            'worshipPenatalayanSchedules' => $schedules,
        ]);
    }

    public function store(
        StoreWorshipServiceScheduleRequest $request,
        WorshipServiceScheduleBuilder $scheduleBuilder,
    ): RedirectResponse {
        if (trim((string) $request->input('action', '')) === 'delete_worship_penatalayan') {
            $month = normalize_month_value((string) $request->input('month', date('Y-m')));

            return $this->deleteByMonth($month, $scheduleBuilder);
        }

        $schedule = $scheduleBuilder->saveRecord($request->scheduleRecord());

        return redirect()->route('worship.penatalayan', [
            'month' => $schedule->month,
            'saved' => 1,
        ]);
    }

    public function destroy(
        DeleteWorshipServiceScheduleRequest $request,
        WorshipServiceScheduleBuilder $scheduleBuilder,
    ): RedirectResponse {
        return $this->deleteByMonth($request->scheduleMonth(), $scheduleBuilder);
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<string, mixed>|null
     */
    private function recordByMonth(array $records, string $month): ?array
    {
        foreach ($records as $record) {
            if ((string) ($record['month'] ?? '') === $month) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $historicalNames
     * @param array<string, int> $serviceCounts
     * @return array<int, string>
     */
    private function displayStewardNames(array $historicalNames, array $serviceCounts): array
    {
        $displayNameMap = [];
        foreach ($historicalNames as $historicalName) {
            $displayNameMap[$historicalName] = true;
        }
        foreach (array_keys($serviceCounts) as $serviceName) {
            $displayNameMap[(string) $serviceName] = true;
        }

        $displayStewardNames = array_keys($displayNameMap);
        usort($displayStewardNames, static function (string $a, string $b) use ($serviceCounts): int {
            $countCompare = ((int) ($serviceCounts[$b] ?? 0)) <=> ((int) ($serviceCounts[$a] ?? 0));
            if ($countCompare !== 0) {
                return $countCompare;
            }

            return strcasecmp($a, $b);
        });

        return $displayStewardNames;
    }

    private function deleteByMonth(
        string $month,
        WorshipServiceScheduleBuilder $scheduleBuilder,
    ): RedirectResponse {
        $month = normalize_month_value($month);
        if (! $scheduleBuilder->deleteMonth($month)) {
            return redirect()->route('worship.penatalayan', [
                'error' => 'invalid_schedule',
                'month' => $month,
            ]);
        }

        return redirect()->route('worship.penatalayan', ['deleted' => 1]);
    }
}
