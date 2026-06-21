<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\TestCase;

class TimezoneConfigurationTest extends TestCase
{
    public function test_application_boots_in_the_configured_jakarta_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertSame('Asia/Jakarta', now()->getTimezone()->getName());
        $this->assertSame('+07:00', CarbonImmutable::now()->format('P'));
    }
}
