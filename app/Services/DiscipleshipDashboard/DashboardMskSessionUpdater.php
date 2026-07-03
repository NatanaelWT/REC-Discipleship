<?php

namespace App\Services\DiscipleshipDashboard;

use App\Models\Person;
use App\Services\MskParticipants\MskParticipantWriter;

class DashboardMskSessionUpdater
{
    public function __construct(
        private readonly MskParticipantWriter $writer,
    ) {}

    /**
     * @param  array<int, int>  $sessionNumbers
     * @return array{auto_converted: bool, error: string}
     */
    public function update(int $participantId, array $sessionNumbers): array
    {
        if ($participantId < 1) {
            return ['auto_converted' => false, 'error' => 'invalid_msk_participant'];
        }

        $participant = Person::query()
            ->where('branch_id', current_user_branch_id())
            ->whereKey($participantId)
            ->first();

        if (! $participant instanceof Person) {
            return ['auto_converted' => false, 'error' => 'invalid_msk_participant'];
        }

        return $this->writer->updateSessions($participant, $sessionNumbers);
    }
}
