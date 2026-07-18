<?php

namespace App\Http\Requests\MskParticipants;

class PermanentlyDeleteMskParticipantRequest extends MskParticipantWriteRequest
{
    protected function authorizationAction(): string
    {
        return 'permanently_delete_msk_participant';
    }
}
