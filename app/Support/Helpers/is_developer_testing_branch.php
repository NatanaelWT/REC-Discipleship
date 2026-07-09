<?php

use App\Services\Auth\CurrentUserContext;

function is_developer_testing_branch(): bool
{
    return function_exists('is_developer_experiment_branch')
        ? is_developer_experiment_branch()
        : app(CurrentUserContext::class)->isDeveloperExperimentBranch();
}
