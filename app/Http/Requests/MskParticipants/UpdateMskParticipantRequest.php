<?php

namespace App\Http\Requests\MskParticipants;

class UpdateMskParticipantRequest extends MskParticipantWriteRequest
{
    protected function authorizationAction(): string
    {
        return 'save_msk_participant';
    }
}
