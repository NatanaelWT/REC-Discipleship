<?php

namespace App\Http\Requests\MskParticipants;

class ImportMskParticipantsRequest extends MskParticipantWriteRequest
{
    protected function authorizationAction(): string
    {
        return 'import_pemuridan_excel';
    }
}
