<?php

namespace App\Services\SpiritualJourney;

use App\Models\MskParticipant;
use Illuminate\Support\Facades\Schema;

class SpiritualJourneyBridgeStatusService
{
    public function update(int $participantId, string $status): bool
    {
        if ($participantId < 1 || ! Schema::hasTable('msk_participants')) {
            return false;
        }

        $branchCode = normalize_public_branch_code(current_user_branch());
        $participant = MskParticipant::query()
            ->where('branch_id', branch_id_from_slug($branchCode))
            ->whereKey($participantId)
            ->first();

        if (! $participant instanceof MskParticipant) {
            return false;
        }

        $participant->forceFill([
            'journey_bridge_status' => normalize_journey_bridge_status($status),
        ])->save();

        return true;
    }
}
