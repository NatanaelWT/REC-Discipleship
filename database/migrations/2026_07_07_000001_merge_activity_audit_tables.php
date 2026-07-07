<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createUnifiedTable();
        $this->copyRequests();
        $this->mergePageViews();
        $this->mergeEvents();

        Schema::disableForeignKeyConstraints();
        try {
            Schema::dropIfExists('kunjungan_halaman');
            Schema::dropIfExists('peristiwa_aktivitas');
            Schema::dropIfExists('permintaan_aktivitas');
            Schema::dropIfExists('website_page_views');
            Schema::dropIfExists('activity_events');
            Schema::dropIfExists('activity_requests');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        $this->createLegacyRequestTable();
        $this->createLegacyEventTable();
        $this->createLegacyPageViewTable();

        if (Schema::hasTable('aktivitas')) {
            foreach (DB::table('aktivitas')->orderBy('started_at')->orderBy('id')->cursor() as $row) {
                $this->restoreRequest($row);
                $this->restoreEvents($row);
                $this->restorePageView($row);
            }
        }

        Schema::dropIfExists('aktivitas');
    }

    private function createUnifiedTable(): void
    {
        if (Schema::hasTable('aktivitas')) {
            return;
        }

        Schema::create('aktivitas', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('actor_type', 20)->default('anonymous')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username', 120)->nullable();
            $table->string('role', 80)->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('branch_label', 160)->nullable();
            $table->unsignedBigInteger('impersonator_user_id')->nullable()->index();
            $table->string('impersonator_username', 120)->nullable();
            $table->string('impersonator_role', 80)->nullable();
            $table->char('visitor_hash', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('method', 12)->default('GET')->index();
            $table->string('route_name', 180)->nullable();
            $table->text('path');
            $table->string('category', 60)->default('request');
            $table->string('action', 180)->default('request')->index();
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 191)->nullable()->index();
            $table->json('query_data')->nullable();
            $table->json('input_data')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->string('outcome', 30)->default('pending')->index();
            $table->text('redirect_to')->nullable();
            $table->string('response_content_type', 180)->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->decimal('duration_ms', 14, 3)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->string('error_type', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at', 6)->index();
            $table->dateTime('completed_at', 6)->nullable();

            $table->boolean('is_page_view')->default(false)->index();
            $table->ulid('session_id')->nullable()->index();
            $table->string('identity_source', 30)->nullable();
            $table->string('segment', 30)->nullable()->index();
            $table->string('referer_host', 255)->nullable();
            $table->string('language_code', 20)->nullable()->index();
            $table->string('language_name', 100)->nullable();
            $table->string('device_type', 30)->nullable()->index();
            $table->string('browser_name', 120)->nullable();
            $table->string('os_name', 120)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->boolean('is_prefetch')->default(false)->index();
            $table->decimal('response_ms', 14, 3)->nullable();
            $table->dateTime('occurred_at', 6)->nullable()->index();

            $table->json('event_entries')->nullable();
            $table->unsignedInteger('events_count')->default(0);
            $table->json('event_categories')->nullable();
            $table->json('event_actions')->nullable();
            $table->json('event_subject_types')->nullable();
            $table->json('event_subject_ids')->nullable();
            $table->longText('event_text')->nullable();

            $table->index(['username', 'started_at']);
            $table->index(['category', 'started_at']);
            $table->index(['route_name', 'started_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['is_page_view', 'is_bot', 'is_prefetch', 'started_at'], 'aktivitas_page_view_human_time_idx');
            $table->index(['visitor_hash', 'occurred_at']);
            $table->index(['session_id', 'occurred_at']);
            $table->index(['segment', 'occurred_at']);
            $table->index(['route_name', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    private function copyRequests(): void
    {
        $source = $this->firstExistingTable(['permintaan_aktivitas', 'activity_requests']);
        if ($source === null) {
            return;
        }

        foreach (DB::table($source)->orderBy('started_at')->orderBy('id')->cursor() as $row) {
            DB::table('aktivitas')->updateOrInsert(
                ['id' => (string) $row->id],
                [
                    'actor_type' => $this->string($row->actor_type ?? 'anonymous', 'anonymous'),
                    'user_id' => $row->user_id ?? null,
                    'username' => $row->username ?? null,
                    'role' => $row->role ?? null,
                    'branch_id' => $row->branch_id ?? null,
                    'branch_label' => $row->branch_label ?? null,
                    'impersonator_user_id' => $row->impersonator_user_id ?? null,
                    'impersonator_username' => $row->impersonator_username ?? null,
                    'impersonator_role' => $row->impersonator_role ?? null,
                    'visitor_hash' => $row->visitor_hash ?? null,
                    'ip_address' => $row->ip_address ?? null,
                    'method' => $this->string($row->method ?? 'GET', 'GET'),
                    'route_name' => $row->route_name ?? null,
                    'path' => $this->string($row->path ?? '/', '/'),
                    'category' => $this->string($row->category ?? 'request', 'request'),
                    'action' => $this->string($row->action ?? 'request', 'request'),
                    'subject_type' => $row->subject_type ?? null,
                    'subject_id' => $row->subject_id ?? null,
                    'query_data' => $this->jsonColumn($row->query_data ?? null),
                    'input_data' => $this->jsonColumn($row->input_data ?? null),
                    'http_status' => $row->http_status ?? null,
                    'outcome' => $this->string($row->outcome ?? 'pending', 'pending'),
                    'redirect_to' => $row->redirect_to ?? null,
                    'response_content_type' => $row->response_content_type ?? null,
                    'response_size' => $row->response_size ?? null,
                    'duration_ms' => $row->duration_ms ?? null,
                    'user_agent' => $row->user_agent ?? null,
                    'referer' => $row->referer ?? null,
                    'error_type' => $row->error_type ?? null,
                    'error_message' => $row->error_message ?? null,
                    'started_at' => $this->datetime($row->started_at ?? null),
                    'completed_at' => $row->completed_at ?? null,
                    'event_entries' => $this->encode([]),
                    'events_count' => 0,
                ],
            );
        }
    }

    private function mergePageViews(): void
    {
        $source = $this->firstExistingTable(['kunjungan_halaman', 'website_page_views']);
        if ($source === null) {
            return;
        }

        $hasLanguage = Schema::hasColumn($source, 'language_code');
        $hasCountry = Schema::hasColumn($source, 'country_code');

        foreach (DB::table($source)->orderBy('occurred_at')->orderBy('request_id')->cursor() as $row) {
            $requestId = (string) $row->request_id;
            $occurredAt = $this->datetime($row->occurred_at ?? null);
            $base = [
                'id' => $requestId,
                'actor_type' => $this->string($row->actor_type ?? 'anonymous', 'anonymous'),
                'user_id' => $row->user_id ?? null,
                'username' => $row->username ?? null,
                'visitor_hash' => $row->visitor_hash ?? null,
                'method' => 'GET',
                'route_name' => $row->route_name ?? null,
                'path' => $this->string($row->path ?? '/', '/'),
                'category' => 'request',
                'action' => 'request.page_view',
                'http_status' => $row->http_status ?? 200,
                'outcome' => 'succeeded',
                'duration_ms' => $row->response_ms ?? null,
                'started_at' => $occurredAt,
                'completed_at' => $occurredAt,
                'event_entries' => $this->encode([]),
                'events_count' => 0,
            ];

            if (! DB::table('aktivitas')->where('id', $requestId)->exists()) {
                DB::table('aktivitas')->insert($base);
            }

            DB::table('aktivitas')->where('id', $requestId)->update([
                'is_page_view' => true,
                'session_id' => $row->session_id ?? null,
                'identity_source' => $row->identity_source ?? null,
                'visitor_hash' => $row->visitor_hash ?? null,
                'user_id' => $row->user_id ?? null,
                'username' => $row->username ?? null,
                'actor_type' => $this->string($row->actor_type ?? $base['actor_type'], 'anonymous'),
                'segment' => $row->segment ?? null,
                'route_name' => $row->route_name ?? $base['route_name'],
                'path' => $this->string($row->path ?? $base['path'], '/'),
                'referer_host' => $row->referer_host ?? null,
                'language_code' => $hasLanguage ? ($row->language_code ?? null) : ($hasCountry ? ($row->country_code ?? null) : null),
                'language_name' => $hasLanguage ? ($row->language_name ?? null) : ($hasCountry ? ($row->country_name ?? null) : null),
                'device_type' => $row->device_type ?? null,
                'browser_name' => $row->browser_name ?? null,
                'os_name' => $row->os_name ?? null,
                'is_bot' => (bool) ($row->is_bot ?? false),
                'is_prefetch' => (bool) ($row->is_prefetch ?? false),
                'response_ms' => $row->response_ms ?? null,
                'occurred_at' => $occurredAt,
            ]);
        }
    }

    private function mergeEvents(): void
    {
        $source = $this->firstExistingTable(['peristiwa_aktivitas', 'activity_events']);
        if ($source === null) {
            return;
        }

        foreach (DB::table($source)->orderBy('request_id')->orderBy('id')->cursor() as $row) {
            $requestId = (string) $row->request_id;
            $activity = DB::table('aktivitas')->where('id', $requestId)->first();
            if ($activity === null) {
                $occurredAt = $this->datetime($row->occurred_at ?? null);
                DB::table('aktivitas')->insert([
                    'id' => $requestId,
                    'actor_type' => 'anonymous',
                    'method' => 'GET',
                    'path' => '/',
                    'category' => $this->string($row->category ?? 'request', 'request'),
                    'action' => $this->string($row->action ?? 'request.event', 'request.event'),
                    'outcome' => 'succeeded',
                    'started_at' => $occurredAt,
                    'completed_at' => $occurredAt,
                    'event_entries' => $this->encode([]),
                    'events_count' => 0,
                ]);
                $activity = DB::table('aktivitas')->where('id', $requestId)->first();
            }

            $entries = $this->decode($activity->event_entries ?? null);
            $entries[] = [
                'id' => isset($row->id) ? (int) $row->id : count($entries) + 1,
                'request_id' => $requestId,
                'category' => $this->string($row->category ?? 'data', 'data'),
                'action' => $this->string($row->action ?? 'changed', 'changed'),
                'subject_type' => $row->subject_type ?? null,
                'subject_id' => $row->subject_id ?? null,
                'subject_label' => $row->subject_label ?? null,
                'description' => $row->description ?? null,
                'before_values' => $this->decodeNullable($row->before_values ?? null),
                'after_values' => $this->decodeNullable($row->after_values ?? null),
                'changed_values' => $this->decodeNullable($row->changed_values ?? null),
                'metadata' => $this->decodeNullable($row->metadata ?? null),
                'occurred_at' => $this->datetime($row->occurred_at ?? null),
            ];

            DB::table('aktivitas')->where('id', $requestId)->update($this->eventSummary($entries));
        }
    }

    private function restoreRequest(object $row): void
    {
        DB::table('permintaan_aktivitas')->updateOrInsert(
            ['id' => (string) $row->id],
            [
                'actor_type' => $this->string($row->actor_type ?? 'anonymous', 'anonymous'),
                'user_id' => $row->user_id ?? null,
                'username' => $row->username ?? null,
                'role' => $row->role ?? null,
                'branch_id' => $row->branch_id ?? null,
                'branch_label' => $row->branch_label ?? null,
                'impersonator_user_id' => $row->impersonator_user_id ?? null,
                'impersonator_username' => $row->impersonator_username ?? null,
                'impersonator_role' => $row->impersonator_role ?? null,
                'visitor_hash' => $row->visitor_hash ?? null,
                'ip_address' => $row->ip_address ?? null,
                'method' => $this->string($row->method ?? 'GET', 'GET'),
                'route_name' => $row->route_name ?? null,
                'path' => $this->string($row->path ?? '/', '/'),
                'category' => $this->string($row->category ?? 'request', 'request'),
                'action' => $this->string($row->action ?? 'request', 'request'),
                'subject_type' => $row->subject_type ?? null,
                'subject_id' => $row->subject_id ?? null,
                'query_data' => $this->jsonColumn($row->query_data ?? null),
                'input_data' => $this->jsonColumn($row->input_data ?? null),
                'http_status' => $row->http_status ?? null,
                'outcome' => $this->string($row->outcome ?? 'pending', 'pending'),
                'redirect_to' => $row->redirect_to ?? null,
                'response_content_type' => $row->response_content_type ?? null,
                'response_size' => $row->response_size ?? null,
                'duration_ms' => $row->duration_ms ?? null,
                'user_agent' => $row->user_agent ?? null,
                'referer' => $row->referer ?? null,
                'error_type' => $row->error_type ?? null,
                'error_message' => $row->error_message ?? null,
                'started_at' => $this->datetime($row->started_at ?? null),
                'completed_at' => $row->completed_at ?? null,
            ],
        );
    }

    private function restoreEvents(object $row): void
    {
        foreach ($this->decode($row->event_entries ?? null) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $payload = [
                'request_id' => (string) $row->id,
                'category' => $this->string($entry['category'] ?? 'data', 'data'),
                'action' => $this->string($entry['action'] ?? 'changed', 'changed'),
                'subject_type' => $entry['subject_type'] ?? null,
                'subject_id' => $entry['subject_id'] ?? null,
                'subject_label' => $entry['subject_label'] ?? null,
                'description' => $entry['description'] ?? null,
                'before_values' => $this->jsonColumn($entry['before_values'] ?? null),
                'after_values' => $this->jsonColumn($entry['after_values'] ?? null),
                'changed_values' => $this->jsonColumn($entry['changed_values'] ?? null),
                'metadata' => $this->jsonColumn($entry['metadata'] ?? null),
                'occurred_at' => $this->datetime($entry['occurred_at'] ?? $row->started_at ?? null),
            ];
            $eventId = $entry['id'] ?? null;
            if (is_numeric($eventId) && (int) $eventId > 0) {
                DB::table('peristiwa_aktivitas')->updateOrInsert(['id' => (int) $eventId], $payload);
            } else {
                DB::table('peristiwa_aktivitas')->insert($payload);
            }
        }
    }

    private function restorePageView(object $row): void
    {
        if (! (bool) ($row->is_page_view ?? false)) {
            return;
        }

        DB::table('kunjungan_halaman')->updateOrInsert(
            ['request_id' => (string) $row->id],
            [
                'session_id' => $row->session_id ?? str_repeat('0', 26),
                'visitor_hash' => $row->visitor_hash ?? hash('sha256', (string) $row->id),
                'identity_source' => $row->identity_source ?? 'legacy_session',
                'user_id' => $row->user_id ?? null,
                'username' => $row->username ?? null,
                'actor_type' => $this->string($row->actor_type ?? 'anonymous', 'anonymous'),
                'segment' => $this->string($row->segment ?? 'publik', 'publik'),
                'route_name' => $row->route_name ?? null,
                'path' => $this->string($row->path ?? '/', '/'),
                'referer_host' => $row->referer_host ?? null,
                'language_code' => $row->language_code ?? null,
                'language_name' => $row->language_name ?? null,
                'device_type' => $this->string($row->device_type ?? 'unknown', 'unknown'),
                'browser_name' => $row->browser_name ?? null,
                'os_name' => $row->os_name ?? null,
                'is_bot' => (bool) ($row->is_bot ?? false),
                'is_prefetch' => (bool) ($row->is_prefetch ?? false),
                'http_status' => $row->http_status ?? 200,
                'response_ms' => $row->response_ms ?? $row->duration_ms ?? null,
                'occurred_at' => $this->datetime($row->occurred_at ?? $row->started_at ?? null),
            ],
        );
    }

    private function createLegacyRequestTable(): void
    {
        if (Schema::hasTable('permintaan_aktivitas')) {
            return;
        }

        Schema::create('permintaan_aktivitas', static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('actor_type', 20)->default('anonymous')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username', 120)->nullable();
            $table->string('role', 80)->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('branch_label', 160)->nullable();
            $table->unsignedBigInteger('impersonator_user_id')->nullable()->index();
            $table->string('impersonator_username', 120)->nullable();
            $table->string('impersonator_role', 80)->nullable();
            $table->char('visitor_hash', 64)->nullable()->index();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('method', 12)->index();
            $table->string('route_name', 180)->nullable();
            $table->text('path');
            $table->string('category', 60)->default('request');
            $table->string('action', 180)->default('request')->index();
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 191)->nullable()->index();
            $table->json('query_data')->nullable();
            $table->json('input_data')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->string('outcome', 30)->default('pending');
            $table->text('redirect_to')->nullable();
            $table->string('response_content_type', 180)->nullable();
            $table->unsignedBigInteger('response_size')->nullable();
            $table->decimal('duration_ms', 14, 3)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->string('error_type', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at', 6)->index();
            $table->dateTime('completed_at', 6)->nullable();
        });
    }

    private function createLegacyEventTable(): void
    {
        if (Schema::hasTable('peristiwa_aktivitas')) {
            return;
        }

        Schema::create('peristiwa_aktivitas', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('request_id');
            $table->string('category', 60);
            $table->string('action', 180)->index();
            $table->string('subject_type', 160)->nullable();
            $table->string('subject_id', 191)->nullable()->index();
            $table->string('subject_label', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->json('changed_values')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->index(['request_id', 'id']);
        });
    }

    private function createLegacyPageViewTable(): void
    {
        if (Schema::hasTable('kunjungan_halaman')) {
            return;
        }

        Schema::create('kunjungan_halaman', static function (Blueprint $table): void {
            $table->ulid('request_id')->primary();
            $table->ulid('session_id')->index();
            $table->char('visitor_hash', 64)->index();
            $table->string('identity_source', 30);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username', 120)->nullable();
            $table->string('actor_type', 20)->default('anonymous');
            $table->string('segment', 30)->index();
            $table->string('route_name', 180)->nullable();
            $table->text('path');
            $table->string('referer_host', 255)->nullable();
            $table->string('language_code', 20)->nullable()->index();
            $table->string('language_name', 100)->nullable();
            $table->string('device_type', 30)->default('unknown')->index();
            $table->string('browser_name', 120)->nullable();
            $table->string('os_name', 120)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->boolean('is_prefetch')->default(false)->index();
            $table->unsignedSmallInteger('http_status');
            $table->decimal('response_ms', 14, 3)->nullable();
            $table->dateTime('occurred_at', 6)->index();
        });
    }

    /** @param array<int, string> $tables */
    private function firstExistingTable(array $tables): ?string
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    /** @param array<int, mixed> $entries */
    private function eventSummary(array $entries): array
    {
        $categories = [];
        $actions = [];
        $subjectTypes = [];
        $subjectIds = [];
        $textParts = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $category = trim((string) ($entry['category'] ?? ''));
            if ($category !== '') {
                $categories[$category] = $category;
            }
            $action = trim((string) ($entry['action'] ?? ''));
            if ($action !== '') {
                $actions[$action] = $action;
            }
            $subjectType = trim((string) ($entry['subject_type'] ?? ''));
            if ($subjectType !== '') {
                $subjectTypes[$subjectType] = $subjectType;
            }
            $subjectId = trim((string) ($entry['subject_id'] ?? ''));
            if ($subjectId !== '') {
                $subjectIds[$subjectId] = $subjectId;
            }
            foreach (['action', 'subject_type', 'subject_id', 'subject_label', 'description'] as $key) {
                $value = trim((string) ($entry[$key] ?? ''));
                if ($value !== '') {
                    $textParts[] = $value;
                }
            }
        }

        return [
            'event_entries' => $this->encode(array_values($entries)),
            'events_count' => count($entries),
            'event_categories' => $this->encode(array_values($categories)),
            'event_actions' => $this->encode(array_values($actions)),
            'event_subject_types' => $this->encode(array_values($subjectTypes)),
            'event_subject_ids' => $this->encode(array_values($subjectIds)),
            'event_text' => trim(implode("\n", array_unique($textParts))) ?: null,
        ];
    }

    private function string(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }

    private function datetime(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : now('UTC')->format('Y-m-d H:i:s.u');
    }

    private function jsonColumn(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return $this->encode($value);
    }

    private function decodeNullable(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->decode($value);
    }

    /** @return array<int|string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }
};
