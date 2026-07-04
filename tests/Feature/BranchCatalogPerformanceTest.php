<?php

namespace Tests\Feature;

use App\Services\Branches\BranchCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BranchCatalogPerformanceTest extends TestCase
{
    public function test_repeated_branch_lookups_use_one_catalog_query(): void
    {
        Schema::create('cabang', function (Blueprint $table): void {
            $table->id();
            $table->string('label')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('cabang')->insert([
            ['label' => 'Kutisari', 'is_active' => true],
            ['label' => 'GM', 'is_active' => true],
        ]);

        $catalog = app(BranchCatalog::class);
        $catalog->clearCache();
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        for ($i = 0; $i < 100; $i++) {
            $this->assertSame('kutisari', $catalog->slugForId(1));
            $this->assertSame(2, $catalog->idForSlug('gm'));
            $this->assertSame('Kutisari', $catalog->labelForId(1));
        }

        $this->assertLessThanOrEqual(1, $queries);
    }
}
