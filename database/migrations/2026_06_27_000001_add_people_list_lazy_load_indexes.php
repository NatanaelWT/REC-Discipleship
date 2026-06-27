<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'discipleship_people';

    private string $index = 'dp_branch_status_name_id_idx';

    /** @var array<int, string> */
    private array $columns = ['branch_id', 'status', 'full_name', 'id'];

    public function up(): void
    {
        if (! Schema::hasTable($this->table) || $this->missingColumns() || $this->hasEquivalentIndex()) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            $table->index($this->columns, $this->index);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable($this->table) || ! Schema::hasIndex($this->table, $this->index)) {
            return;
        }

        Schema::table($this->table, function (Blueprint $table): void {
            $table->dropIndex($this->index);
        });
    }

    private function missingColumns(): bool
    {
        foreach ($this->columns as $column) {
            if (! Schema::hasColumn($this->table, $column)) {
                return true;
            }
        }

        return false;
    }

    private function hasEquivalentIndex(): bool
    {
        foreach (Schema::getIndexes($this->table) as $index) {
            if (($index['columns'] ?? []) === $this->columns) {
                return true;
            }
        }

        return false;
    }
};
