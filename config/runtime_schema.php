<?php

return [
    /*
    | This is the database contract used by normal HTTP requests. Schema
    | introspection belongs in the deployment health check, never in the hot
    | path. Keep this list in step with migrations that change these tables.
    */
    'tables' => [
        'users' => ['id', 'username', 'access_scope', 'branch_id', 'is_active'],
        'cabang' => ['id', 'label', 'is_active'],
        'orang' => ['id', 'branch_id', 'full_name', 'status', 'session_numbers', 'photos'],
        'kelompok_dg' => ['id', 'branch_id', 'status', 'stage'],
        'keanggotaan_kelompok_dg' => ['id', 'branch_id', 'discipleship_group_id', 'person_id', 'role', 'status'],
        'jurnal_temu_dg' => ['id', 'branch_id', 'discipleship_group_id', 'meeting_date', 'absences', 'meditation_sharers', 'photos'],
        'dg_manual' => ['id', 'branch_id', 'person_id'],
        'jurnal_umpan_balik' => ['id', 'branch_id', 'feedback_session', 'ratings', 'notes'],
        'materi_publik' => ['id', 'menu', 'relative_path', 'sha256', 'text_extracted_at', 'text_extraction_error'],
        'konfigurasi' => ['id', 'key', 'value'],
        'percobaan_login' => ['id', 'attempt_key', 'failed_attempt_count', 'last_attempted_at'],
    ],
];
