<?php

namespace App\Services\DiscipleshipPeopleTree;

use App\Models\DiscipleshipFeedback;
use App\Models\DiscipleshipGroup;
use App\Models\DiscipleshipGroupPerson;
use App\Models\DiscipleshipMeetingReport;
use App\Services\Mutation\MutationLifecycle;
use Illuminate\Support\Facades\DB;

class PeopleTreeGroupDeleter
{
    public function __construct(private readonly MutationLifecycle $lifecycle) {}

    public function delete(int $branchId, int $groupId): bool
    {
        if ($branchId < 1 || $groupId < 1) {
            return false;
        }

        return DB::transaction(function () use ($branchId, $groupId): bool {
            $group = DiscipleshipGroup::query()
                ->where('branch_id', $branchId)
                ->whereKey($groupId)
                ->lockForUpdate()
                ->first();
            if (! $group instanceof DiscipleshipGroup) {
                return false;
            }

            $photoPaths = $this->meetingPhotoPaths($groupId);

            DiscipleshipFeedback::query()
                ->where('discipleship_group_id', $groupId)
                ->delete();
            DiscipleshipMeetingReport::query()
                ->where('discipleship_group_id', $groupId)
                ->delete();
            DiscipleshipGroupPerson::query()
                ->where('discipleship_group_id', $groupId)
                ->delete();

            DiscipleshipGroup::query()
                ->where('parent_group_id', $groupId)
                ->update(['parent_group_id' => null, 'updated_at' => now()]);
            DiscipleshipGroup::query()
                ->where('source_group_id', $groupId)
                ->update(['source_group_id' => null, 'updated_at' => now()]);

            $group->delete();
            $this->deleteMeetingPhotosAfterCommit($photoPaths);

            return true;
        });
    }

    /** @return array<int, string> */
    private function meetingPhotoPaths(int $groupId): array
    {
        $paths = [];
        $reports = DiscipleshipMeetingReport::query()
            ->where('discipleship_group_id', $groupId)
            ->get(['photos']);

        foreach ($reports as $report) {
            foreach ($report->photoItems() as $photo) {
                foreach (['path', 'web_path', 'thumbnail_path'] as $key) {
                    $this->rememberMeetingPhotoPath($paths, (string) ($photo[$key] ?? ''));
                }
            }
        }

        return array_keys($paths);
    }

    /** @param array<string, true> $paths */
    private function rememberMeetingPhotoPath(array &$paths, string $path): void
    {
        $safePath = sanitize_relative_upload_path($path);
        if (str_starts_with($safePath, 'uploads/dg_reports/')) {
            $paths[$safePath] = true;
        }
    }

    /** @param array<int, string> $paths */
    private function deleteMeetingPhotosAfterCommit(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $cleanup = function () use ($paths): void {
            $usedPaths = [];
            foreach (DiscipleshipMeetingReport::query()->get(['photos']) as $report) {
                foreach ($report->photoItems() as $photo) {
                    foreach (['path', 'web_path', 'thumbnail_path'] as $key) {
                        $this->rememberMeetingPhotoPath($usedPaths, (string) ($photo[$key] ?? ''));
                    }
                }
            }

            foreach ($paths as $path) {
                if (! isset($usedPaths[$path])) {
                    delete_relative_upload_file($path);
                }
            }
        };

        if ($this->lifecycle->active()) {
            $this->lifecycle->onCommit($cleanup);

            return;
        }

        DB::afterCommit($cleanup);
    }
}
