<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiscipleshipPersonProfile
{
    public static function join(mixed $query, string $peopleAlias = 'people', string $profileAlias = 'person_profile'): void
    {
        // Profile data now lives on the canonical people row itself.
    }

    public static function expression(string $field, string $peopleAlias = 'people', string $profileAlias = 'person_profile'): string
    {
        $column = match ($field) {
            'phone' => 'whatsapp',
            'gender' => 'gender',
            default => $field,
        };

        if (Schema::hasTable('people') && Schema::hasColumn('people', $column)) {
            return "COALESCE(NULLIF({$peopleAlias}.{$column}, ''), '')";
        }

        return "''";
    }

    /**
     * @param  array<int, int>  $personIds
     * @return array<int, string>
     */
    public static function namesByPersonIds(array $personIds): array
    {
        $personIds = array_values(array_filter(array_unique($personIds), static fn (int $id): bool => $id > 0));
        if ($personIds === [] || ! Schema::hasTable('people')) {
            return [];
        }

        $query = DB::table('people as p')
            ->whereIn('p.id', $personIds);

        return $query
            ->selectRaw('p.id, '.self::expression('full_name', 'p').' as full_name')
            ->pluck('full_name', 'id')
            ->map(static fn (mixed $name): string => trim((string) $name))
            ->all();
    }
}
