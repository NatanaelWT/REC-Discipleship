<?php

use App\Support\PersonNameNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orang') || ! Schema::hasColumn('orang', 'full_name')) {
            return;
        }

        DB::table('orang')
            ->select(['id', 'full_name'])
            ->orderBy('id')
            ->chunkById(500, function ($people): void {
                DB::transaction(function () use ($people): void {
                    foreach ($people as $person) {
                        $currentName = $person->full_name !== null ? (string) $person->full_name : null;
                        $normalizedName = PersonNameNormalizer::normalize($currentName);

                        if ($normalizedName === $currentName) {
                            continue;
                        }

                        $query = DB::table('orang')->where('id', $person->id);

                        // MySQL may treat case-only and trailing-space changes as
                        // unchanged under a case-insensitive, pad-space collation.
                        $query->update(['full_name' => '__normalizing_person_name_'.(string) $person->id]);
                        $query->update(['full_name' => $normalizedName]);
                    }
                });
            }, 'id');
    }

    public function down(): void
    {
        // Original capitalization cannot be reconstructed safely.
    }
};
