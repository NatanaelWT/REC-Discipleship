<?php

namespace App\Http\Requests\MskParticipants;

class ReactivateMskParticipantRequest extends MskParticipantWriteRequest
{
    protected function authorizationAction(): string
    {
        return 'reactivate_msk_participant';
    }
}
