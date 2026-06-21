<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('website_page_views') || ! Schema::hasTable('website_sessions')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('website_page_views')
                ->where(static function (Builder $query): void {
                    $query->whereNotNull('user_id')
                        ->orWhereNull('segment')
                        ->orWhereNotIn('segment', ['publik', 'login']);
                })
                ->delete();

            DB::table('website_page_views')->update([
                'user_id' => null,
                'username' => null,
                'actor_type' => 'anonymous',
            ]);

            DB::table('website_sessions')
                ->whereNotExists(static function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('website_page_views')
                        ->whereColumn('website_page_views.session_id', 'website_sessions.id');
                })
                ->delete();

            DB::table('website_sessions')
                ->select('id')
                ->orderBy('id')
                ->chunkById(500, function ($sessions): void {
                    foreach ($sessions as $session) {
                        $views = DB::table('website_page_views')
                            ->where('session_id', $session->id);
                        $bounds = (clone $views)
                            ->selectRaw('COUNT(*) AS page_views, MIN(occurred_at) AS started_at, MAX(occurred_at) AS last_seen_at')
                            ->first();

                        if ((int) ($bounds->page_views ?? 0) === 0) {
                            DB::table('website_sessions')->where('id', $session->id)->delete();

                            continue;
                        }

                        $first = (clone $views)
                            ->orderBy('occurred_at')
                            ->orderBy('request_id')
                            ->first(['path']);
                        $last = (clone $views)
                            ->orderByDesc('occurred_at')
                            ->orderByDesc('request_id')
                            ->first(['path']);

                        DB::table('website_sessions')->where('id', $session->id)->update([
                            'user_id' => null,
                            'username' => null,
                            'started_at' => $bounds->started_at,
                            'last_seen_at' => $bounds->last_seen_at,
                            'landing_path' => (string) ($first->path ?? '/'),
                            'exit_path' => (string) ($last->path ?? '/'),
                            'page_views' => (int) $bounds->page_views,
                        ]);
                    }
                }, 'id');
        });
    }

    public function down(): void
    {
        // Irreversible: page view internal yang telah dihapus tidak dapat direkonstruksi.
    }
};
