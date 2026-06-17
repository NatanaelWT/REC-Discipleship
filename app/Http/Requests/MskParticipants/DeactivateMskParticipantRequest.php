<?php

namespace App\Http\Requests\MskParticipants;

class DeactivateMskParticipantRequest extends MskParticipantWriteRequest
{
    protected function authorizationAction(): string
    {
        return 'delete_msk_participant';
    }
}
