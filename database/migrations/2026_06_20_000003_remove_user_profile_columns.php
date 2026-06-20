<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $profileColumns = array_values(array_filter(
            ['name', 'email', 'email_verified_at'],
            static fn (string $column): bool => Schema::hasColumn('users', $column),
        ));
        foreach (Schema::getIndexes('users') as $index) {
            if ($index['primary'] || array_intersect($profileColumns, $index['columns']) === []) {
                continue;
            }

            Schema::table('users', static function (Blueprint $table) use ($index): void {
                if ($index['unique']) {
                    $table->dropUnique($index['name']);
                } else {
                    $table->dropIndex($index['name']);
                }
            });
        }

        Schema::table('users', static function (Blueprint $table) use ($profileColumns): void {
            if ($profileColumns !== []) {
                $table->dropColumn($profileColumns);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', static function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
        });
    }
};
