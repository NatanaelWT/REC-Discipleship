<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private array $columns = [
        'campus',
        'major',
        'occupation',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        $existingColumns = array_values(array_filter(
            $this->columns,
            static fn (string $column): bool => Schema::hasColumn('discipleship_people', $column),
        ));

        if ($existingColumns === []) {
            return;
        }

        Schema::table('discipleship_people', function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('discipleship_people')) {
            return;
        }

        $missingColumns = array_values(array_filter(
            $this->columns,
            static fn (string $column): bool => ! Schema::hasColumn('discipleship_people', $column),
        ));

        if ($missingColumns === []) {
            return;
        }

        Schema::table('discipleship_people', function (Blueprint $table) use ($missingColumns): void {
            foreach ($missingColumns as $column) {
                $table->string($column)->nullable();
            }
        });
    }
};
