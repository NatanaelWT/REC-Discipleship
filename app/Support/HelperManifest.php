<?php

namespace App\Support;

class HelperManifest
{
    private const CORE = [
        'app_church_name', 'app_config_value', 'app_timezone', 'asset_version', 'auth_access_scope_label',
        'branch_can_access_page', 'branch_can_access_secure_upload_path', 'branch_can_use_action', 'branch_home_page',
        'branch_id_from_slug', 'branch_ids_from_slugs', 'branch_slug_from_id', 'can_manage_app_config',
        'can_manage_difficult_questions', 'can_manage_public_materials', 'can_manage_users', 'central_readonly_action_map',
        'central_readonly_page_map', 'central_recap_branch_label', 'central_recap_branch_options',
        'central_recap_selected_branch', 'clear_removed_session_state', 'client_ip_address', 'current_auth_access_scope',
        'current_jakarta_time_label', 'current_username', 'current_user_branch', 'current_user_branch_id',
        'current_user_can_access_worship', 'developer_debug_banner_enabled', 'discipleship_action_map',
        'discipleship_page_map', 'destroy_current_session', 'format_datetime_id',
        'format_indo_date', 'format_indo_month', 'format_lock_wait_label', 'format_short_indo_date', 'format_short_indo_weekday_date',
        'h', 'is_central_discipleship_readonly_session', 'is_developer_access_mode', 'is_developer_session', 'is_discipleship_action',
        'is_discipleship_branch_scope', 'is_effective_central_discipleship_readonly', 'is_logged_in',
        'is_same_origin_url', 'is_superuser_session', 'is_valid_post_origin', 'is_worship_action',
        'is_worship_only_scope', 'is_worship_page', 'normalize_auth_access_scope', 'normalize_central_recap_branch',
        'normalize_public_branch_code', 'normalize_user_branch', 'normalize_whatsapp_digits', 'normalize_ymd_date',
        'now_iso', 'parse_bool_value', 'public_branch_label', 'public_dg_branch_options', 'request_host_name',
        'restricted_branch_action_map', 'restricted_branch_page_map', 'restricted_secure_upload_prefixes',
        'secure_upload_prefixes_for_current_scope', 'secure_upload_url', 'today_date', 'user_branch_label',
        'view_rendering', 'worship_action_map', 'worship_only_action_map', 'worship_only_page_map', 'worship_page_map',
        'developer_access_original_username', 'developer_access_target_username',
    ];

    private const FILES = [
        'cleanup_uploaded_entries', 'delete_photo_file_if_unused', 'delete_relative_upload_file', 'detect_file_mime_type',
        'format_file_size', 'is_upload_path', 'normalize_uploaded_file_items', 'rec_public_path', 'rec_runtime_path',
        'resolve_relative_upload_path', 'sanitize_file_name_component', 'sanitize_relative_upload_path',
        'secure_file_allowed_extensions', 'secure_file_extension', 'secure_file_inline_extensions',
        'secure_file_mime_by_extension', 'upload_managed_file', 'upload_managed_files',
    ];

    private const DISCIPLESHIP = [
        'append_branch_suffix', 'attach_people_tree_group_children', 'build_group_member_names',
        'build_msk_import_export_rows', 'build_msk_import_export_sheet_xml', 'build_people_tree_group_history_views',
        'build_people_tree_group_rows', 'build_pohon_pemuridan_dot_content', 'completed_msk_person_sources',
        'create_msk_import_export_xlsx', 'default_discipleship_targets', 'dgv2_archive_group', 'dgv2_archive_person',
        'dgv2_canonical_identity_source_id', 'dgv2_close_active_relation_for_disciple', 'dgv2_complete_group',
        'dgv2_effective_end_date', 'dgv2_empty_model', 'dgv2_find_identity', 'dgv2_groups_projection',
        'dgv2_group_active_member_ids', 'dgv2_group_historical_member_ids', 'dgv2_identity_sources',
        'dgv2_is_active_row', 'dgv2_is_current_period', 'dgv2_leave_group', 'dgv2_normalize_model',
        'dgv2_open_relation', 'dgv2_payload_member_ids', 'dgv2_people_projection', 'dgv2_person_active_group_ids',
        'dgv2_reactivate_group', 'dgv2_save_group', 'dgv2_save_person', 'dgv2_save_person_external',
        'dgv2_save_person_single', 'dgv2_sync_group_leaderships', 'dgv2_sync_group_memberships',
        'dg_progress_min_share_times', 'discipleship_group_display_label', 'discipleship_group_stage_value',
        'discipleship_action_map', 'discipleship_page_map',
        'discipleship_person_sources', 'discipleship_stage_color', 'discipleship_unified_identity_key',
        'export_xlsx_column_ref', 'export_xlsx_inline_text', 'extract_member_photos', 'extract_msk_participant_photos',
        'filter_active_members', 'format_parent_names', 'get_parent_ids',
        'import_build_header_map', 'import_is_blank_row', 'import_normalize_gender_value', 'import_normalize_month_strict',
        'import_parse_msk_session_numbers', 'import_read_xlsx_sheets', 'import_row_value', 'import_sheet_name_key',
        'import_split_csv_tokens', 'import_xlsx_column_index_from_ref', 'import_xlsx_shared_string_text',
        'index_by_id', 'is_member_active', 'is_photo_path_used_in_msk_classes', 'msk_is_complete',
        'normalize_dg_progress_value', 'normalize_discipleship_targets', 'normalize_group_member_names',
        'normalize_journey_bridge_status', 'normalize_member_gender_value',
        'normalize_member_status_value', 'normalize_month_value', 'normalize_msk_participant_status',
        'normalize_msk_session_numbers', 'normalize_sheet_cell_value', 'person_label', 'pohon_dot_attrs',
        'pohon_dot_group_label', 'pohon_dot_group_node_id', 'pohon_dot_group_stage', 'pohon_dot_id',
        'pohon_dot_person_label', 'pohon_dot_person_name', 'pohon_dot_primary_group_leader_name', 'pohon_dot_quote',
        'read_branch_discipleship_targets', 'temporary_model_id', 'upload_dg_meeting_photo',
        'upload_dg_meeting_photos', 'upload_member_photo', 'upload_member_photos',
    ];

    private const PUBLIC_PORTAL = [
        'normalize_church_folder_path', 'normalize_church_folder_segment', 'normalize_public_material_menu',
        'normalize_public_member_feedback_session', 'is_known_public_branch_code', 'is_public_material_dg_session_menu',
        'is_public_material_feedback_session', 'is_public_material_previewable_path', 'normalize_msk_session_numbers',
        'public_material_paths', 'public_material_previewable_extensions', 'public_material_session_number',
        'discipleship_group_display_label', 'discipleship_group_stage_value',
        'public_member_feedback_group_option_label', 'public_member_feedback_group_title',
        'public_member_feedback_questions', 'normalize_public_member_feedback_session',
    ];

    private const WORSHIP = [
        'build_worship_penatalayan_schedule', 'default_worship_penatalayan_title', 'is_sunday_date',
        'normalize_month_value', 'normalize_worship_penatalayan_rows', 'worship_penatalayan_default_roles', 'worship_penatalayan_font_path',
        'worship_penatalayan_historical_service_names', 'worship_penatalayan_png_draw_text',
        'worship_penatalayan_png_text_box', 'worship_penatalayan_png_text_layout', 'worship_penatalayan_png_wrap_lines',
        'worship_penatalayan_service_counts', 'worship_penatalayan_service_names', 'worship_penatalayan_svg_wrap_lines',
        'worship_penatalayan_training_date', 'worship_penatalayan_training_field_value',
        'worship_penatalayan_training_label', 'worship_penatalayan_week_dates',
    ];

    /** @return array<int, string> */
    public static function forPath(string $path): array
    {
        $groups = [self::CORE];
        if (str_starts_with($path, 'pemuridan')) {
            $groups[] = self::DISCIPLESHIP;
            $groups[] = self::FILES;
        } elseif (str_starts_with($path, 'ibadah')) {
            $groups[] = self::WORSHIP;
            $groups[] = self::FILES;
        } elseif ($path === '' || str_starts_with($path, 'publik') || str_starts_with($path, 'materi') || str_starts_with($path, 'file-aman')) {
            $groups[] = self::PUBLIC_PORTAL;
            $groups[] = self::DISCIPLESHIP;
            $groups[] = self::FILES;
        } else {
            $groups[] = self::FILES;
        }

        return array_values(array_unique(array_merge(...$groups)));
    }

    /** @return array<int, string> */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(self::CORE, self::FILES, self::DISCIPLESHIP, self::PUBLIC_PORTAL, self::WORSHIP)));
    }
}
