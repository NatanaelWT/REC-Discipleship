<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiscipleshipPersonProfile
{
    public static function join(mixed $query, string $peopleAlias = 'discipleship_people', string $profileAlias = 'person_profile'): void
    {
        if (! Schema::hasTable('msk_participants')) {
            return;
        }

        $query->leftJoin('msk_participants as '.$profileAlias, $profileAlias.'.discipleship_person_id', '=', $peopleAlias.'.id');
    }

    public static function expression(string $field, string $peopleAlias = 'discipleship_people', string $profileAlias = 'person_profile'): string
    {
        $legacyColumn = match ($field) {
            'full_name' => 'full_name',
            'phone' => 'phone',
            'gender' => 'gender',
            default => $field,
        };

        $profileColumn = match ($field) {
            'phone' => 'whatsapp',
            default => $field,
        };

        $parts = [];
        if (Schema::hasTable('msk_participants')) {
            $parts[] = "NULLIF({$profileAlias}.{$profileColumn}, '')";
        }
        if (Schema::hasTable('discipleship_people') && Schema::hasColumn('discipleship_people', $legacyColumn)) {
            $parts[] = "NULLIF({$peopleAlias}.{$legacyColumn}, '')";
        }

        if ($parts === []) {
            return "''";
        }

        return 'COALESCE('.implode(', ', $parts).", '')";
    }

    /**
     * @param  array<int, int>  $personIds
     * @return array<int, string>
     */
    public static function namesByPersonIds(array $personIds): array
    {
        $personIds = array_values(array_filter(array_unique($personIds), static fn (int $id): bool => $id > 0));
        if ($personIds === [] || ! Schema::hasTable('discipleship_people')) {
            return [];
        }

        $query = DB::table('discipleship_people as p')
            ->whereIn('p.id', $personIds);
        self::join($query, 'p', 'profile');

        return $query
            ->selectRaw('p.id, '.self::expression('full_name', 'p', 'profile').' as full_name')
            ->pluck('full_name', 'id')
            ->map(static fn (mixed $name): string => trim((string) $name))
            ->all();
    }
}
