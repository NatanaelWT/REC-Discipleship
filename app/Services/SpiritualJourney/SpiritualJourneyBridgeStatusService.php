<?php

namespace App\Services\SpiritualJourney;

use App\Models\Person;

class SpiritualJourneyBridgeStatusService
{
    public function update(int $participantId, string $status): bool
    {
        if ($participantId < 1) {
            return false;
        }

        $branchCode = normalize_user_branch(current_user_branch());
        $participant = Person::query()
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->whereKey($participantId)
            ->first();

        if (! $participant instanceof Person) {
            return false;
        }

        $participant->forceFill([
            'journey_bridge_status' => normalize_journey_bridge_status($status),
        ])->save();

        return true;
    }
}
