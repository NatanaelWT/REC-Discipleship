<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

class ValidateCsrfTokenAndRecordActivity extends ValidateCsrfToken
{
    public function __construct(
        Application $app,
        Encrypter $encrypter,
        private readonly RecordActivityRequest $activity,
    ) {
        parent::__construct($app, $encrypter);
    }

    public function handle($request, Closure $next)
    {
        return $this->activity->handle(
            $request,
            fn ($innerRequest) => parent::handle($innerRequest, $next),
        );
    }
}
