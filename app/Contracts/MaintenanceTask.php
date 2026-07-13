<?php

namespace App\Contracts;

interface MaintenanceTask
{
    public function key(): string;

    public function label(): string;

    /** @return array<string, mixed> */
    public function preview(): array;

    /**
     * @param  array<string, mixed>  $cursor
     * @return array{complete:bool,cursor:array<string, mixed>,summary:array<string, mixed>}
     */
    public function run(array $cursor, int $batchSize, float $deadline): array;
}
