<?php

namespace App\Services\SpiritualJourney;

use App\Models\MskParticipant;
use Illuminate\Support\Facades\Schema;

class SpiritualJourneyBridgeStatusService
{
    public function update(string $participantPublicId, string $status): bool
    {
        $participantPublicId = trim($participantPublicId);
        if ($participantPublicId === '' || ! Schema::hasTable('msk_participants')) {
            return false;
        }

        $branchCode = normalize_public_branch_code(current_user_branch());
        $participant = MskParticipant::query()
            ->where('branch_code', $branchCode)
            ->where('public_id', $participantPublicId)
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
