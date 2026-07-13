<?php

namespace App\Http\Controllers\Worship;

use App\Http\Controllers\Controller;
use App\Services\WorshipServiceSchedules\WorshipServiceScheduleBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ServiceScheduleImageController extends Controller
{
    public function download(
        Request $request,
        WorshipServiceScheduleBuilder $scheduleBuilder,
    ): RedirectResponse|Response {
        $selectedMonth = normalize_month_value((string) $request->query('month', date('Y-m')));
        $selectedExistingSchedule = $scheduleBuilder->recordForMonth($selectedMonth);
        if ($selectedExistingSchedule === null) {
            return redirect()->route('worship.penatalayan', [
                'error' => 'invalid_schedule',
                'month' => $selectedMonth,
            ]);
        }

        $schedule = $scheduleBuilder->buildSchedule($selectedMonth, $selectedExistingSchedule);
        $pngContent = render_worship_penatalayan_schedule_png($schedule);
        if (! is_string($pngContent) || $pngContent === '') {
            return redirect()->route('worship.penatalayan', [
                'error' => 'invalid_schedule',
                'month' => $selectedMonth,
            ]);
        }

        $downloadName = default_worship_penatalayan_title($selectedMonth).'.png';
        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $downloadName) ?? 'Jadwal Pelayanan Ibadah Umum.png';
        if ($downloadName === '') {
            $downloadName = 'Jadwal Pelayanan Ibadah Umum.png';
        }

        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'Jadwal_Pelayanan_Ibadah_Umum.png';
        if ($asciiDownloadName === '') {
            $asciiDownloadName = 'Jadwal_Pelayanan_Ibadah_Umum.png';
        }

        return response($pngContent, 200, [
            'Content-Type' => 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Content-Disposition' => 'attachment; filename="'.$asciiDownloadName.'"; filename*=UTF-8\'\''.rawurlencode($downloadName),
        ]);
    }
}
