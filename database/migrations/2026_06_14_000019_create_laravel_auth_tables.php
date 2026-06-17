<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            $addUsername = ! Schema::hasColumn('users', 'username');
            $addBranchCode = ! Schema::hasColumn('users', 'branch_code');
            $addAccessScope = ! Schema::hasColumn('users', 'access_scope');
            $addLastLoginAt = ! Schema::hasColumn('users', 'last_login_at');

            if ($addUsername || $addBranchCode || $addAccessScope || $addLastLoginAt) {
                Schema::table('users', static function (Blueprint $table) use ($addUsername, $addBranchCode, $addAccessScope, $addLastLoginAt): void {
                    if ($addUsername) {
                        $table->string('username', 120)->nullable()->unique()->after('id');
                    }

                    if ($addBranchCode) {
                        $table->string('branch_code', 40)->default('kutisari')->index()->after('password');
                    }

                    if ($addAccessScope) {
                        $table->string('access_scope', 80)->default('branch')->after('branch_code');
                    }

                    if ($addLastLoginAt) {
                        $table->timestamp('last_login_at')->nullable()->after('access_scope');
                    }
                });
            }
        }

        if (! Schema::hasTable('login_attempts')) {
            Schema::create('login_attempts', static function (Blueprint $table): void {
                $table->id();
                $table->string('attempt_key', 120)->unique();
                $table->unsignedInteger('failed_attempt_count')->default(0);
                $table->timestamp('window_started_at')->nullable();
                $table->timestamp('locked_until_at')->nullable();
                $table->timestamp('last_attempted_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');

        if (! Schema::hasTable('users')) {
            return;
        }

        foreach ([
            ['username', 'dropUnique'],
            ['branch_code', 'dropIndex'],
        ] as [$column, $method]) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            try {
                Schema::table('users', static function (Blueprint $table) use ($column, $method): void {
                    $table->{$method}([$column]);
                });
            } catch (Throwable) {
                // Some database drivers drop indexes together with columns.
            }
        }

        $columns = array_values(array_filter(
            ['username', 'branch_code', 'access_scope', 'last_login_at'],
            static fn (string $column): bool => Schema::hasColumn('users', $column),
        ));

        if ($columns !== []) {
            Schema::table('users', static function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
