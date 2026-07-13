<?php

use App\Services\Maintenance\ActivityRetentionMaintenanceTask;
use App\Services\Maintenance\ExpiredRuntimeFilesMaintenanceTask;
use App\Services\Maintenance\MediaInventoryMaintenanceTask;
use App\Services\Maintenance\PendingMaterialTextExtractionTask;
use App\Services\Maintenance\PublicMaterialChecksumTask;

$defaultRuntimeRoot = env('APP_ENV') === 'testing'
    ? storage_path('framework/testing-maintenance')
    : storage_path('framework');

return [
    /*
     * Additional media/PDF/cache tasks can implement MaintenanceTask and be
     * appended here. The runner persists an independent cursor for each task.
     */
    'tasks' => [
        ActivityRetentionMaintenanceTask::class,
        ExpiredRuntimeFilesMaintenanceTask::class,
        MediaInventoryMaintenanceTask::class,
        PublicMaterialChecksumTask::class,
        PendingMaterialTextExtractionTask::class,
    ],
    'compiled_view_retention_days' => max(1, (int) env('MAINTENANCE_COMPILED_VIEW_RETENTION_DAYS', 7)),
    'runtime_root' => env('MAINTENANCE_RUNTIME_ROOT', $defaultRuntimeRoot),
];
