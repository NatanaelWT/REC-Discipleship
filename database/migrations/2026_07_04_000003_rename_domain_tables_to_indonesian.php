<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $tables = [
        'app_configs' => 'konfigurasi',
        'branches' => 'cabang',
        'login_attempts' => 'percobaan_login',
        'activity_requests' => 'permintaan_aktivitas',
        'activity_events' => 'peristiwa_aktivitas',
        'difficult_questions' => 'pertanyaan_sulit',
        'people' => 'orang',
        'discipleship_groups' => 'kelompok_dg',
        'discipleship_group_people' => 'keanggotaan_kelompok_dg',
        'discipleship_relationships' => 'relasi_dg',
        'discipleship_meeting_reports' => 'jurnal_temu_dg',
        'discipleship_feedbacks' => 'jurnal_umpan_balik',
        'discipleship_manual_journey_records' => 'dg_manual',
        'public_material_files' => 'materi_publik',
        'website_sessions' => 'sesi',
        'website_page_views' => 'kunjungan_halaman',
        'worship_service_schedules' => 'jadwal_pelayanan_ibadah',
    ];

    public function up(): void
    {
        $this->renameTables($this->tables);
    }

    public function down(): void
    {
        $this->renameTables(array_reverse(array_flip($this->tables), true));
    }

    /**
     * @param  array<string, string>  $tables
     */
    private function renameTables(array $tables): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $from => $to) {
                if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                    Schema::rename($from, $to);
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
