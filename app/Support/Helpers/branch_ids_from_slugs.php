<?php

function branch_ids_from_slugs(array $branches): array
{
    $ids = array_map(
        static fn (mixed $branch): ?int => branch_id_from_slug((string) $branch),
        $branches,
    );

    return array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null)));
}
