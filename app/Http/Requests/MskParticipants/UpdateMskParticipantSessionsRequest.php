<?php

namespace App\Http\Requests\MskParticipants;

class UpdateMskParticipantSessionsRequest extends MskParticipantWriteRequest
{
    protected function authorizationAction(): string
    {
        return 'save_msk_sessions';
    }
}
