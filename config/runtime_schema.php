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
        'msk_import_jobs' => ['id', 'user_id', 'branch_id', 'active_branch_id', 'status', 'staged_byte_cursor', 'processed_rows'],
        'msk_import_source_keys' => ['id', 'job_id', 'row_number', 'match_type', 'match_key'],
        'msk_import_existing_people' => ['id', 'job_id', 'person_id', 'identity_key', 'touched_at'],
        'msk_import_batches' => ['id', 'job_id', 'batch_token', 'byte_cursor_before', 'byte_cursor_after', 'result'],
    ],
];
