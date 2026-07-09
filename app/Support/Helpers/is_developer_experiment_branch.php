<?php

use App\Services\Auth\CurrentUserContext;

function is_developer_experiment_branch(): bool
{
    return app(CurrentUserContext::class)->isDeveloperExperimentBranch();
}
