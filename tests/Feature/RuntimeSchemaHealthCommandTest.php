<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RuntimeSchemaHealthCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('runtime_contract_test');

        parent::tearDown();
    }

    public function test_deployment_health_check_accepts_complete_contract(): void
    {
        Schema::create('runtime_contract_test', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        config(['runtime_schema.tables' => ['runtime_contract_test' => ['id', 'name']]]);

        $this->assertSame(0, Artisan::call('rec:schema-health', ['--json' => true]));
        $this->assertStringContainsString('"healthy":true', Artisan::output());
    }

    public function test_deployment_health_check_rejects_missing_columns(): void
    {
        Schema::create('runtime_contract_test', static function (Blueprint $table): void {
            $table->id();
        });
        config(['runtime_schema.tables' => ['runtime_contract_test' => ['id', 'name']]]);

        $this->assertSame(1, Artisan::call('rec:schema-health', ['--json' => true]));
        $this->assertStringContainsString('runtime_contract_test.name', Artisan::output());
    }
}
