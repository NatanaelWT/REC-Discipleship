<?php

namespace App\Http\Controllers\PublicPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\DgMeetingReports\StoreDgMeetingReportRequest;
use App\Models\DiscipleshipMeetingReport;
use App\Services\DgMeetingReports\DgMeetingReportFormData;
use App\Services\DgMeetingReports\DgMeetingReportPhotoUploader;
use App\Support\RuntimeBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class DgMeetingReportController extends Controller
{
    public function redirectToBranchReport(Request $request): RedirectResponse
    {
        RuntimeBootstrap::boot($request);

        $branchRaw = trim((string) $request->query('cabang', $request->query('branch', '')));
        if ($branchRaw === '') {
            if (! is_logged_in()) {
                return redirect()->route('public.dg.branch');
            }

            $branch = current_user_branch();
        } elseif (! is_known_public_branch_code($branchRaw)) {
            return redirect()->route('public.dg.branch', ['error' => 'invalid_branch']);
        } else {
            $branch = normalize_public_branch_code($branchRaw);
        }

        $params = ['branch' => $branch];
        if ($request->query->has('submitted')) {
            $params['submitted'] = 1;
        }

        return redirect()->route('public.dg.report', $params);
    }

    public function create(
        Request $request,
        DgMeetingReportFormData $formData,
        string $branch,
    ): View|RedirectResponse {
        RuntimeBootstrap::boot($request);

        if (! is_known_public_branch_code($branch)) {
            return redirect()->route('public.dg.branch', ['error' => 'invalid_branch']);
        }

        $publicBranch = normalize_public_branch_code($branch);
        $old = is_array(session('public_dg_report_old')) ? session('public_dg_report_old') : [];
        $old['public_cabang'] = $publicBranch;

        $publicDgReportError = trim((string) session('public_dg_report_error', ''));
        session()->forget('public_dg_report_error');

        $branchFormData = $formData->forBranch($publicBranch);

        return view('public.dg-reports.create', [
            'settings' => ['church_name' => app_church_name()],
            'old' => $old,
            'publicBranch' => $publicBranch,
            'publicBranchLabel' => public_branch_label($publicBranch),
            'leaderOptions' => $branchFormData['leaders'],
            'groupOptions' => $branchFormData['groups'],
            'groupMap' => $branchFormData['group_map'],
            'materialOptions' => $branchFormData['material_options'],
            'submitted' => $request->query->has('submitted'),
            'publicDgReportError' => $publicDgReportError,
        ]);
    }

    public function store(
        StoreDgMeetingReportRequest $request,
        DgMeetingReportPhotoUploader $photoUploader,
        string $branch = '',
    ): RedirectResponse {
        $publicBranch = $request->publicBranch();
        $uploadResult = $photoUploader->uploadFromPhpFiles();
        if ($uploadResult['error_message'] !== '') {
            session()->put('public_dg_report_error', $uploadResult['error_message']);

            return redirect()->route('public.dg.report', ['branch' => $publicBranch]);
        }

        $meetingPhotos = $uploadResult['photos'];

        try {
            DB::transaction(function () use ($request, $meetingPhotos): void {
                $reportData = [
                    'public_id' => $this->generatePublicId(),
                    'branch_id' => branch_id_from_slug($request->publicBranch()),
                    'leader_person_id' => $request->leaderPersonId(),
                    'leader_person_public_id' => $request->leaderPublicId(),
                    'leader_name_snapshot' => $request->leaderName(),
                    'discipleship_group_id' => $request->discipleshipGroupId(),
                    'discipleship_group_public_id' => $request->groupPublicId(),
                    'group_name_snapshot' => $request->groupName(),
                    'meeting_date' => $request->meetingDate(),
                    'material_topic' => $request->materialLabel(),
                    'group_progress_snapshot' => $request->groupProgress(),
                    'absence_reason' => $request->absenceReason(),
                    'additional_notes' => $request->additionalNotes(),
                    'meditation_min_times' => $request->meditationMinTimes(),
                    'sharing_openness_score' => $request->sharingOpennessScore(),
                    'prepared_material' => $request->preparedMaterial(),
                    'prayed_for_members' => $request->prayedForMembers(),
                    'shared_meditation' => $request->sharedMeditation(),
                    'relationally_contacted' => $request->relationallyContacted(),
                    'source' => 'public_form',
                ];

                if (Schema::hasColumn('discipleship_meeting_reports', 'absences')) {
                    $reportData['absences'] = array_map(static fn (string $memberId) => [
                        'person_id' => $request->memberRecordId($memberId),
                        'person_public_id' => $memberId,
                        'person_name_snapshot' => $request->memberName($memberId),
                    ], $request->absentMemberIds());
                }
                if (Schema::hasColumn('discipleship_meeting_reports', 'meditation_sharers')) {
                    $reportData['meditation_sharers'] = array_map(static fn (string $memberId) => [
                        'person_id' => $request->memberRecordId($memberId),
                        'person_public_id' => $memberId,
                        'person_name_snapshot' => $request->memberName($memberId),
                    ], $request->meditationSharerIds());
                }
                if (Schema::hasColumn('discipleship_meeting_reports', 'photos')) {
                    $reportData['photos'] = array_values(array_filter(array_map(static function (array $photo): array {
                        $relativePath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));

                        return $relativePath === '' ? [] : [
                            'path' => $relativePath,
                            'name' => trim((string) ($photo['name'] ?? '')) ?: null,
                        ];
                    }, $meetingPhotos)));
                }

                $report = DiscipleshipMeetingReport::query()->create($reportData);

                if (Schema::hasTable('discipleship_meeting_report_absences')) {
                    foreach ($request->absentMemberIds() as $memberId) {
                        $report->absences()->create([
                            'person_id' => $request->memberRecordId($memberId),
                            'person_public_id' => $memberId,
                            'person_name_snapshot' => $request->memberName($memberId),
                        ]);
                    }
                }

                if (Schema::hasTable('discipleship_meeting_report_meditation_sharers')) {
                    foreach ($request->meditationSharerIds() as $memberId) {
                        $report->meditationSharers()->create([
                            'person_id' => $request->memberRecordId($memberId),
                            'person_public_id' => $memberId,
                            'person_name_snapshot' => $request->memberName($memberId),
                        ]);
                    }
                }

                if (Schema::hasTable('discipleship_meeting_report_photos')) {
                    foreach (array_values($meetingPhotos) as $sortOrder => $photo) {
                        $relativePath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
                        if ($relativePath === '') {
                            continue;
                        }

                        $report->photos()->create([
                            'relative_path' => $relativePath,
                            'original_file_name' => trim((string) ($photo['name'] ?? '')) ?: null,
                            'sort_order' => $sortOrder,
                        ]);
                    }
                }

                $report->fresh();
            });
        } catch (Throwable) {
            $photoUploader->cleanup($meetingPhotos);
            session()->put('public_dg_report_error', 'Laporan pertemuan DG gagal disimpan. Coba ulangi lagi.');

            return redirect()->route('public.dg.report', ['branch' => $publicBranch]);
        }

        session()->forget(['public_dg_report_old', 'public_dg_report_error']);

        return redirect()->route('public.dg.report', [
            'branch' => $publicBranch,
            'submitted' => 1,
        ]);
    }

    private function generatePublicId(): string
    {
        do {
            $id = function_exists('generate_id')
                ? generate_id('dg_report')
                : 'dg_report_'.bin2hex(random_bytes(4));
        } while ($this->publicIdExists($id));

        return $id;
    }

    private function publicIdExists(string $publicId): bool
    {
        return DiscipleshipMeetingReport::query()->where('public_id', $publicId)->exists();
    }
}
