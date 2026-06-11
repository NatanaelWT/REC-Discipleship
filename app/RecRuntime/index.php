<?php
// REC administration system - PHP Native + File JSON

const APP_TIMEZONE = 'Asia/Jakarta';
const CHURCH_NAME = 'Reformed Exodus Community';
const PEOPLE_REGISTRY_DATA_NAME = 'people_registry';
const DISCIPLESHIP_GROUPS_DATA_NAME = 'discipleship_groups';
const DISCIPLESHIP_RELATIONSHIPS_DATA_NAME = 'discipleship_relationships';

date_default_timezone_set(APP_TIMEZONE);

require_once __DIR__ . '/support/legacy_runtime_path.php';


require_once __DIR__ . '/support/legacy_public_path.php';


require_once __DIR__ . '/support/legacy_exit.php';


$httpsEnabled = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if ($httpsEnabled) {
        ini_set('session.cookie_secure', '1');
    }
    session_name('rec_admin_session');
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if ($httpsEnabled) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

require_once __DIR__ . '/support/normalize_public_branch_code.php';


require_once __DIR__ . '/support/is_known_public_branch_code.php';


require_once __DIR__ . '/support/public_dg_branch_options.php';


require_once __DIR__ . '/support/public_branch_label.php';


require_once __DIR__ . '/support/public_material_menu_options.php';


require_once __DIR__ . '/support/normalize_public_material_menu.php';


require_once __DIR__ . '/support/public_material_option.php';


require_once __DIR__ . '/support/church_files_for_public_material.php';


require_once __DIR__ . '/support/public_material_previewable_extensions.php';


require_once __DIR__ . '/support/is_public_material_previewable_path.php';


require_once __DIR__ . '/support/is_public_material_dg_session_menu.php';


require_once __DIR__ . '/support/public_material_session_number.php';


require_once __DIR__ . '/support/is_public_material_feedback_session.php';


require_once __DIR__ . '/support/normalize_difficult_question_text.php';


require_once __DIR__ . '/support/difficult_question_password_lookup.php';


require_once __DIR__ . '/support/difficult_question_status_label.php';


require_once __DIR__ . '/support/default_discipleship_targets.php';


require_once __DIR__ . '/support/normalize_discipleship_targets.php';


require_once __DIR__ . '/support/read_branch_discipleship_targets.php';


require_once __DIR__ . '/support/is_public_dg_flow_request.php';


require_once __DIR__ . '/support/requested_public_dg_branch.php';






require_once __DIR__ . '/support/discipleship_page_map.php';


require_once __DIR__ . '/support/worship_page_map.php';


require_once __DIR__ . '/support/restricted_branch_page_map.php';


require_once __DIR__ . '/support/worship_only_page_map.php';


require_once __DIR__ . '/support/central_readonly_page_map.php';




require_once __DIR__ . '/support/discipleship_action_map.php';


require_once __DIR__ . '/support/worship_action_map.php';


require_once __DIR__ . '/support/restricted_branch_action_map.php';


require_once __DIR__ . '/support/worship_only_action_map.php';


require_once __DIR__ . '/support/central_readonly_action_map.php';


require_once __DIR__ . '/support/is_discipleship_action.php';


require_once __DIR__ . '/support/normalize_auth_access_scope.php';


require_once __DIR__ . '/support/current_auth_access_scope.php';


require_once __DIR__ . '/support/auth_access_scope_label.php';


require_once __DIR__ . '/support/is_worship_only_scope.php';


require_once __DIR__ . '/support/is_worship_page.php';


require_once __DIR__ . '/support/is_worship_action.php';


require_once __DIR__ . '/support/username_can_access_worship.php';


require_once __DIR__ . '/support/current_user_can_access_worship.php';


require_once __DIR__ . '/support/is_discipleship_branch_scope.php';


require_once __DIR__ . '/support/is_central_discipleship_readonly_session.php';


require_once __DIR__ . '/support/is_effective_central_discipleship_readonly.php';


require_once __DIR__ . '/support/read_user_accounts.php';


require_once __DIR__ . '/support/update_user_password.php';


require_once __DIR__ . '/support/update_user_last_login.php';


require_once __DIR__ . '/support/find_auth_account_by_username.php';


require_once __DIR__ . '/support/clear_removed_session_state.php';


require_once __DIR__ . '/support/scoped_virtual_id.php';


require_once __DIR__ . '/support/append_branch_suffix.php';


require_once __DIR__ . '/support/central_recap_branch_options.php';


require_once __DIR__ . '/support/normalize_central_recap_branch.php';


require_once __DIR__ . '/support/central_recap_branch_label.php';


require_once __DIR__ . '/support/central_recap_selected_branch.php';


require_once __DIR__ . '/support/filter_central_snapshot_by_branch.php';


require_once __DIR__ . '/support/branch_scoped_data_names.php';


require_once __DIR__ . '/support/canonical_data_name.php';


require_once __DIR__ . '/support/is_people_registry_data_name.php';


require_once __DIR__ . '/support/is_discipleship_groups_data_name.php';


require_once __DIR__ . '/support/branch_scoped_virtual_data_path.php';


require_once __DIR__ . '/support/data_path.php';


require_once __DIR__ . '/support/discipleship_table_object_data_names.php';


require_once __DIR__ . '/support/discipleship_table_path.php';


require_once __DIR__ . '/support/discipleship_table_default.php';


require_once __DIR__ . '/support/discipleship_table_now_iso.php';


require_once __DIR__ . '/support/discipleship_branch_path_info.php';


require_once __DIR__ . '/support/discipleship_table_read_raw.php';


require_once __DIR__ . '/support/discipleship_table_branch_from_record.php';


require_once __DIR__ . '/support/discipleship_table_strip_branch.php';


require_once __DIR__ . '/support/discipleship_table_encode_branch_record.php';


require_once __DIR__ . '/support/discipleship_persons_are_unified.php';


require_once __DIR__ . '/support/discipleship_embedded_relation_table_names.php';


require_once __DIR__ . '/support/discipleship_relationship_kind_map.php';


require_once __DIR__ . '/support/discipleship_relationship_kind_for_table.php';


require_once __DIR__ . '/support/discipleship_relationship_database_exists.php';


require_once __DIR__ . '/support/discipleship_relationships_read_raw.php';


require_once __DIR__ . '/support/discipleship_relationships_branch_known.php';


require_once __DIR__ . '/support/discipleship_relationship_rows_from_branch.php';


require_once __DIR__ . '/support/discipleship_relationship_set_branch_value.php';


require_once __DIR__ . '/support/discipleship_embedded_relation_owner_field.php';


require_once __DIR__ . '/support/discipleship_unified_branch_exists.php';


require_once __DIR__ . '/support/discipleship_normalize_embedded_relation_container.php';


require_once __DIR__ . '/support/discipleship_embedded_relation_rows_from_unified_branch.php';


require_once __DIR__ . '/support/discipleship_person_row_from_unified_record.php';


require_once __DIR__ . '/support/discipleship_person_rows_from_unified_branch.php';


require_once __DIR__ . '/support/discipleship_unified_record_aliases.php';


require_once __DIR__ . '/support/discipleship_unified_record_has_payload.php';


require_once __DIR__ . '/support/unified_discipleship_person_payload.php';


require_once __DIR__ . '/support/unified_move_profile_field_from_payload.php';


require_once __DIR__ . '/support/unified_compact_profile_owned_payloads.php';


require_once __DIR__ . '/support/people_registry_record_has_nested_payload.php';


require_once __DIR__ . '/support/people_registry_value_is_present.php';


require_once __DIR__ . '/support/people_registry_has_any_present_key.php';


require_once __DIR__ . '/support/people_registry_copy_present_value.php';


require_once __DIR__ . '/support/hydrate_people_registry_records_for_runtime.php';


require_once __DIR__ . '/support/hydrate_people_registry_record_for_runtime.php';


require_once __DIR__ . '/support/flatten_people_registry_record_for_storage.php';


require_once __DIR__ . '/support/flatten_people_registry_table_for_storage.php';


require_once __DIR__ . '/support/discipleship_persons_set_unified_branch_value.php';


require_once __DIR__ . '/support/discipleship_embedded_relation_set_unified_branch_value.php';


require_once __DIR__ . '/support/discipleship_table_has_logical_source.php';


require_once __DIR__ . '/support/discipleship_table_write_raw.php';


require_once __DIR__ . '/support/discipleship_table_branch_value.php';


require_once __DIR__ . '/support/discipleship_table_set_branch_value.php';


require_once __DIR__ . '/support/discipleship_branch_json_exists.php';


require_once __DIR__ . '/support/read_json.php';


require_once __DIR__ . '/support/detect_preferred_json_eol.php';


require_once __DIR__ . '/support/encode_json_for_storage.php';


require_once __DIR__ . '/support/write_json.php';


require_once __DIR__ . '/support/write_json_unrestricted.php';


require_once __DIR__ . '/support/user_branch_label.php';


require_once __DIR__ . '/support/app_timezone.php';


require_once __DIR__ . '/support/normalize_iso_datetime_to_jakarta.php';


require_once __DIR__ . '/support/first_iso_datetime.php';


require_once __DIR__ . '/support/latest_iso_datetime.php';


require_once __DIR__ . '/support/sync_unified_record_timestamps.php';


require_once __DIR__ . '/support/scoped_data_path.php';


// Merged from modules/discipleship_v2.php
require_once __DIR__ . '/support/dgv2_model_names.php';


require_once __DIR__ . '/support/dgv2_branch_file_path.php';


require_once __DIR__ . '/support/dgv2_empty_model.php';


require_once __DIR__ . '/support/dgv2_read_model.php';


require_once __DIR__ . '/support/dgv2_write_model.php';


require_once __DIR__ . '/support/dgv2_load_and_save_model.php';


require_once __DIR__ . '/support/dgv2_group_active_member_ids.php';


require_once __DIR__ . '/support/dgv2_person_active_group_ids.php';


require_once __DIR__ . '/support/dgv2_group_historical_member_ids.php';


require_once __DIR__ . '/support/dgv2_close_active_relation_for_disciple.php';


require_once __DIR__ . '/support/dgv2_open_relation.php';


require_once __DIR__ . '/support/dgv2_sync_group_memberships.php';


require_once __DIR__ . '/support/dgv2_sync_group_leaderships.php';


require_once __DIR__ . '/support/dgv2_save_person_external.php';


require_once __DIR__ . '/support/dgv2_save_person_single.php';


require_once __DIR__ . '/support/dgv2_save_person.php';


require_once __DIR__ . '/support/dgv2_save_group.php';


require_once __DIR__ . '/support/dgv2_archive_person.php';


require_once __DIR__ . '/support/dgv2_leave_group.php';


require_once __DIR__ . '/support/dgv2_complete_group.php';


require_once __DIR__ . '/support/dgv2_reactivate_group.php';


require_once __DIR__ . '/support/dgv2_archive_group.php';


require_once __DIR__ . '/support/dgv2_validation_errors.php';


require_once __DIR__ . '/support/dgv2_canonical_identity_source_id.php';


require_once __DIR__ . '/support/dgv2_identity_sources.php';


require_once __DIR__ . '/support/dgv2_find_identity.php';


require_once __DIR__ . '/support/dgv2_payload_member_ids.php';


require_once __DIR__ . '/support/dgv2_is_active_row.php';


require_once __DIR__ . '/support/dgv2_effective_end_date.php';


require_once __DIR__ . '/support/dgv2_is_current_period.php';


require_once __DIR__ . '/support/dgv2_model_exists.php';


require_once __DIR__ . '/support/dgv2_normalize_model.php';


require_once __DIR__ . '/support/dgv2_migrate_from_legacy.php';


require_once __DIR__ . '/support/dgv2_load_or_migrate.php';


require_once __DIR__ . '/support/dgv2_people_projection.php';


require_once __DIR__ . '/support/dgv2_groups_projection.php';



require_once __DIR__ . '/support/build_central_discipleship_snapshot.php';


require_once __DIR__ . '/support/persist_groups_data.php';


require_once __DIR__ . '/support/h.php';


require_once __DIR__ . '/support/now_iso.php';


require_once __DIR__ . '/support/current_jakarta_time_label.php';


require_once __DIR__ . '/support/today_date.php';


require_once __DIR__ . '/support/normalize_ymd_date.php';


require_once __DIR__ . '/support/is_sunday_date.php';


require_once __DIR__ . '/support/next_sunday_date.php';


require_once __DIR__ . '/support/is_saturday_date.php';


require_once __DIR__ . '/support/next_saturday_date.php';


require_once __DIR__ . '/support/format_indo_date.php';




require_once __DIR__ . '/support/format_file_size.php';


require_once __DIR__ . '/support/format_datetime_id.php';


require_once __DIR__ . '/support/normalize_sheet_cell_value.php';


require_once __DIR__ . '/support/normalize_sheet_rows_payload.php';


require_once __DIR__ . '/support/normalize_sheet_axis_format_payload.php';


require_once __DIR__ . '/support/normalize_sheet_format_payload.php';


require_once __DIR__ . '/support/write_sheet_csv_file.php';


require_once __DIR__ . '/support/sheet_format_sidecar_path.php';


require_once __DIR__ . '/support/write_sheet_format_sidecar.php';


require_once __DIR__ . '/support/read_sheet_format_sidecar.php';


require_once __DIR__ . '/support/delete_sheet_format_sidecar.php';


require_once __DIR__ . '/support/read_sheet_csv_file.php';


require_once __DIR__ . '/support/import_sheet_name_key.php';






require_once __DIR__ . '/support/import_split_csv_tokens.php';


require_once __DIR__ . '/support/import_normalize_gender_value.php';


require_once __DIR__ . '/support/import_normalize_month_strict.php';


require_once __DIR__ . '/support/import_parse_msk_session_numbers.php';


require_once __DIR__ . '/support/import_xlsx_column_index_from_ref.php';


require_once __DIR__ . '/support/import_xlsx_shared_string_text.php';


require_once __DIR__ . '/support/import_read_xlsx_sheets.php';


require_once __DIR__ . '/support/export_xlsx_column_ref.php';


require_once __DIR__ . '/support/export_xlsx_inline_text.php';


require_once __DIR__ . '/support/build_msk_import_export_rows.php';


require_once __DIR__ . '/support/build_msk_import_export_sheet_xml.php';


require_once __DIR__ . '/support/create_msk_import_export_xlsx.php';


require_once __DIR__ . '/support/import_build_header_map.php';


require_once __DIR__ . '/support/import_row_value.php';


require_once __DIR__ . '/support/import_is_blank_row.php';






require_once resource_path('views/partials/render_pemuridan_import_feedback.blade.php');


require_once __DIR__ . '/support/normalize_month_value.php';


require_once __DIR__ . '/support/format_indo_month.php';


require_once __DIR__ . '/support/format_short_indo_date.php';


require_once __DIR__ . '/support/format_short_indo_weekday_date.php';


require_once __DIR__ . '/support/worship_penatalayan_default_roles.php';


require_once __DIR__ . '/support/default_worship_penatalayan_title.php';


require_once __DIR__ . '/support/worship_penatalayan_week_dates.php';


require_once __DIR__ . '/support/normalize_worship_penatalayan_rows.php';


require_once __DIR__ . '/support/normalize_worship_penatalayan_records.php';


require_once __DIR__ . '/support/build_worship_penatalayan_schedule.php';


require_once __DIR__ . '/support/worship_penatalayan_service_names.php';


require_once __DIR__ . '/support/worship_penatalayan_service_counts.php';


require_once __DIR__ . '/support/worship_penatalayan_historical_service_names.php';


require_once __DIR__ . '/support/worship_penatalayan_training_date.php';


require_once __DIR__ . '/support/worship_penatalayan_training_field_value.php';


require_once __DIR__ . '/support/worship_penatalayan_training_label.php';


require_once __DIR__ . '/support/worship_penatalayan_svg_escape.php';


require_once __DIR__ . '/support/worship_penatalayan_svg_wrap_lines.php';


require_once __DIR__ . '/support/worship_penatalayan_svg_text.php';




require_once __DIR__ . '/support/worship_penatalayan_font_path.php';


require_once __DIR__ . '/support/worship_penatalayan_png_text_box.php';


require_once __DIR__ . '/support/worship_penatalayan_png_wrap_lines.php';


require_once __DIR__ . '/support/worship_penatalayan_png_text_layout.php';


require_once __DIR__ . '/support/worship_penatalayan_png_draw_text.php';


require_once resource_path('views/partials/render_worship_penatalayan_schedule_png.blade.php');


require_once __DIR__ . '/support/sanitize_relative_upload_path.php';


require_once __DIR__ . '/support/is_upload_path.php';


require_once __DIR__ . '/support/secure_file_extension.php';


require_once __DIR__ . '/support/secure_file_allowed_extensions.php';


require_once __DIR__ . '/support/secure_file_mime_by_extension.php';


require_once __DIR__ . '/support/secure_file_inline_extensions.php';


require_once __DIR__ . '/support/detect_file_mime_type.php';


require_once __DIR__ . '/support/secure_upload_url.php';


require_once __DIR__ . '/support/delete_relative_upload_file.php';


require_once __DIR__ . '/support/sanitize_file_name_component.php';


require_once __DIR__ . '/support/upload_managed_file.php';


require_once __DIR__ . '/support/cleanup_uploaded_entries.php';


require_once __DIR__ . '/support/upload_managed_files.php';


require_once __DIR__ . '/support/extract_member_photos.php';


require_once __DIR__ . '/support/extract_msk_participant_photos.php';


require_once __DIR__ . '/support/is_photo_path_used_in_members.php';


require_once __DIR__ . '/support/is_photo_path_used_in_msk_classes.php';


require_once __DIR__ . '/support/delete_photo_file_if_unused.php';


require_once __DIR__ . '/support/normalize_member_gender_value.php';


require_once __DIR__ . '/support/normalize_member_status_value.php';


require_once __DIR__ . '/support/is_member_active.php';


require_once __DIR__ . '/support/normalize_whatsapp_digits.php';


require_once __DIR__ . '/support/member_identity_key.php';


require_once __DIR__ . '/support/filter_active_members.php';


require_once __DIR__ . '/support/index_active_members_by_id.php';


require_once __DIR__ . '/support/member_completeness_fields.php';


require_once __DIR__ . '/support/member_completeness_filter_options.php';


require_once resource_path('views/partials/render_member_form_html.blade.php');


require_once resource_path('views/partials/render_member_view_html.blade.php');


require_once __DIR__ . '/support/discipleship_person_sources.php';


require_once __DIR__ . '/support/discipleship_person_sources_by_id.php';


require_once __DIR__ . '/support/completed_msk_person_sources.php';


require_once __DIR__ . '/support/normalize_member_birth_day_month_value.php';


require_once __DIR__ . '/support/format_member_birth_day_month.php';


require_once __DIR__ . '/support/member_birth_day_month.php';


require_once __DIR__ . '/support/normalize_journey_bridge_status.php';


require_once __DIR__ . '/support/normalize_msk_participant_status.php';


require_once __DIR__ . '/support/normalize_msk_session_numbers.php';


require_once __DIR__ . '/support/normalize_dg_progress_value.php';


require_once __DIR__ . '/support/discipleship_completed_dg3_count.php';


require_once __DIR__ . '/support/discipleship_stage_color.php';


require_once __DIR__ . '/support/find_unique_member_id_by_full_name.php';


require_once __DIR__ . '/support/has_member_by_full_name.php';


require_once __DIR__ . '/support/sync_member_left_duplicates_by_identity.php';


require_once __DIR__ . '/support/is_member_registered_in_msk.php';


require_once __DIR__ . '/support/msk_is_complete.php';


require_once __DIR__ . '/support/auto_register_msk_participant_as_member.php';


require_once __DIR__ . '/support/sync_member_data_from_msk.php';


require_once __DIR__ . '/support/sync_msk_data_from_member.php';


require_once __DIR__ . '/support/unified_pick_string.php';


require_once __DIR__ . '/support/unified_person_profile.php';


require_once __DIR__ . '/support/unified_member_payload.php';


require_once __DIR__ . '/support/unified_msk_payload.php';


require_once __DIR__ . '/support/unified_discipleship_payload.php';


require_once __DIR__ . '/support/discipleship_unified_identity_key.php';


require_once __DIR__ . '/support/normalize_people_registry_records.php';


require_once __DIR__ . '/support/people_registry_views.php';


require_once __DIR__ . '/support/merge_people_registry_records.php';


require_once __DIR__ . '/support/persist_people_registry_data.php';


require_once __DIR__ . '/support/persist_people_data.php';


require_once __DIR__ . '/support/compact_people_registry_records_for_storage.php';






require_once __DIR__ . '/support/compact_dg_meeting_reports_for_storage.php';


require_once __DIR__ . '/support/persist_dg_meeting_reports_data.php';


require_once __DIR__ . '/support/hydrate_dg_meeting_reports_for_runtime.php';


require_once __DIR__ . '/support/normalize_social_link_value.php';


require_once __DIR__ . '/support/upload_member_photo.php';


require_once __DIR__ . '/support/upload_member_photos.php';


require_once __DIR__ . '/support/upload_dg_meeting_photo.php';


require_once __DIR__ . '/support/upload_dg_meeting_photos.php';


require_once __DIR__ . '/support/church_file_categories.php';


require_once __DIR__ . '/support/normalize_church_file_category.php';


require_once __DIR__ . '/support/normalize_church_folder_segment.php';


require_once __DIR__ . '/support/normalize_church_folder_path.php';


require_once __DIR__ . '/support/church_files_base_relative_path.php';


require_once __DIR__ . '/support/church_file_folder_from_path.php';


require_once __DIR__ . '/support/normalize_uploaded_file_items.php';


require_once __DIR__ . '/support/upload_church_file.php';


require_once __DIR__ . '/support/sync_member_family_links.php';


require_once __DIR__ . '/support/member_family_groups.php';


require_once __DIR__ . '/support/parse_bool_value.php';


require_once __DIR__ . '/support/dg_progress_min_share_times.php';


require_once __DIR__ . '/support/asset_version.php';


require_once __DIR__ . '/support/client_ip_address.php';


require_once __DIR__ . '/support/login_attempt_key.php';


require_once __DIR__ . '/support/read_login_attempts.php';


require_once __DIR__ . '/support/prune_login_attempts.php';


require_once __DIR__ . '/support/login_wait_seconds.php';


require_once __DIR__ . '/support/register_login_failure.php';


require_once __DIR__ . '/support/clear_login_failures.php';


require_once __DIR__ . '/support/format_lock_wait_label.php';


require_once __DIR__ . '/support/request_host_name.php';


require_once __DIR__ . '/support/is_same_origin_url.php';


require_once __DIR__ . '/support/is_valid_post_origin.php';


require_once __DIR__ . '/support/is_top_level_navigation_request.php';


require_once __DIR__ . '/support/is_logged_in.php';


require_once __DIR__ . '/support/destroy_current_session.php';


require_once __DIR__ . '/support/normalize_user_branch.php';


require_once __DIR__ . '/support/current_user_branch.php';


require_once __DIR__ . '/support/current_username.php';


require_once __DIR__ . '/support/can_manage_difficult_questions.php';


require_once __DIR__ . '/support/branch_home_page.php';


require_once __DIR__ . '/support/branch_can_access_page.php';


require_once __DIR__ . '/support/branch_can_use_action.php';


require_once __DIR__ . '/support/restricted_secure_upload_prefixes.php';


require_once __DIR__ . '/support/secure_upload_prefixes_for_current_scope.php';


require_once __DIR__ . '/support/branch_can_access_secure_upload_path.php';


require_once __DIR__ . '/support/auth_accounts_config.php';


require_once __DIR__ . '/support/find_auth_account.php';


require_once __DIR__ . '/support/redirect_to.php';


require_once __DIR__ . '/support/redirect_to_files.php';



require_once __DIR__ . '/support/index_by_id.php';


require_once __DIR__ . '/support/normalize_group_member_names.php';


require_once __DIR__ . '/support/build_group_member_names.php';




require_once __DIR__ . '/support/build_dg_public_form_data.php';


require_once __DIR__ . '/support/public_member_feedback_questions.php';


require_once __DIR__ . '/support/normalize_public_member_feedback_session.php';


require_once __DIR__ . '/support/normalize_public_member_feedback_text.php';


require_once __DIR__ . '/support/public_member_feedback_group_title.php';


require_once __DIR__ . '/support/public_member_feedback_group_option_label.php';


require_once __DIR__ . '/support/read_public_member_feedback_rows.php';


require_once __DIR__ . '/support/persist_public_member_feedback_rows.php';


require_once __DIR__ . '/support/generate_id.php';


$defaultWorshipPenatalayan = [];
$defaultDgMeetingReports = [];
$defaultMemberMskUnified = [];
$defaultChurchFiles = [];

$settings = ['church_name' => CHURCH_NAME];
$discipleshipTargets = normalize_discipleship_targets(read_json(data_path('discipleship_targets'), default_discipleship_targets()));
$people = [];
$groups = [];
$worshipPenatalayanSchedules = normalize_worship_penatalayan_records(read_json(data_path('worship_penatalayan'), $defaultWorshipPenatalayan));
$memberMskUnifiedPath = data_path(PEOPLE_REGISTRY_DATA_NAME);
$memberMskUnifiedExists = discipleship_branch_json_exists($memberMskUnifiedPath);
$memberMskUnifiedRecords = read_json($memberMskUnifiedPath, $defaultMemberMskUnified);
$memberMskViews = [
    'members' => [],
    'msk_classes' => [],
    'people' => [],
];
$members = [];
$dgMeetingReports = read_json(data_path('dg_meeting_reports'), $defaultDgMeetingReports);
$mskClasses = [];
$churchFiles = read_json(data_path('church_files'), $defaultChurchFiles);
$difficultQuestions = read_json(data_path('difficult_questions'), []);
if (!is_array($difficultQuestions)) {
    $difficultQuestions = [];
}

if ($memberMskUnifiedExists) {
    $normalizedUnifiedRecords = normalize_people_registry_records($memberMskUnifiedRecords);
    $compactUnifiedRecords = compact_people_registry_records_for_storage($normalizedUnifiedRecords);
    $memberMskUnifiedRecords = $compactUnifiedRecords;
    $memberMskViews = people_registry_views($memberMskUnifiedRecords);
    $members = $memberMskViews['members'] ?? [];
    $mskClasses = $memberMskViews['msk_classes'] ?? [];
    if (!is_array($members)) {
        $members = [];
    }
    if (!is_array($mskClasses)) {
        $mskClasses = [];
    }
} else {
    $memberMskUnifiedRecords = [];
    $memberMskViews = people_registry_views($memberMskUnifiedRecords);
}

// Ensure completed MSK participants are converted into jemaat before normalizing people tree data.
$membersChangedByMskMigration = false;
$mskChangedByMigration = false;
foreach ($mskClasses as $idx => $participant) {
    if (!is_array($participant)) {
        continue;
    }
    $beforeMemberCount = count($members);
    if (auto_register_msk_participant_as_member($mskClasses[$idx], $members)) {
        $mskChangedByMigration = true;
    }
    if (sync_member_data_from_msk($mskClasses[$idx], $members)) {
        $mskChangedByMigration = true;
        $membersChangedByMskMigration = true;
    }
    if (count($members) !== $beforeMemberCount) {
        $membersChangedByMskMigration = true;
    }
}
if ($membersChangedByMskMigration && sync_member_family_links($members)) {
    $membersChangedByMskMigration = true;
}
if ($membersChangedByMskMigration || $mskChangedByMigration) {
    persist_people_registry_data($members, $mskClasses);
}

$discipleshipV2Enabled = true;
$discipleshipV2Branch = normalize_public_branch_code(current_user_branch());
$discipleshipV2Model = dgv2_read_model($discipleshipV2Branch);

$progressOptions = ['DG 1', 'DG 2', 'DG 3'];

require_once __DIR__ . '/support/load_branch_discipleship_runtime.php';


$rootLeaderName = 'Injil';
$rootLeaderId = 'virtual_injil';
$discipleshipOnlyPeopleData = false;
$people = dgv2_people_projection($discipleshipV2Model, $members, $mskClasses);
$peopleById = index_by_id($people);
$groups = dgv2_groups_projection($discipleshipV2Model, $peopleById);
$groupsById = index_by_id($groups);
persist_people_data($people);
persist_groups_data($groups);
$dgMeetingReports = hydrate_dg_meeting_reports_for_runtime($dgMeetingReports, $groupsById, $peopleById);

$membersNormalized = false;
$cleanMembers = [];
$seenMemberIds = [];
foreach ($members as $member) {
    if (!is_array($member)) {
        $membersNormalized = true;
        continue;
    }

    $id = trim((string) ($member['id'] ?? ''));
    if ($id === '' || isset($seenMemberIds[$id])) {
        $id = generate_id('member');
        $membersNormalized = true;
    }
    $seenMemberIds[$id] = true;

    $fullName = trim((string) ($member['full_name'] ?? $member['name'] ?? ''));
    if ($fullName === '') {
        $fullName = 'Tanpa Nama';
        $membersNormalized = true;
    }
    if (!array_key_exists('full_name', $member)) {
        $membersNormalized = true;
    }

    $genderRaw = (string) ($member['gender'] ?? '');
    $gender = normalize_member_gender_value($genderRaw);
    if ($genderRaw !== $gender || !array_key_exists('gender', $member)) {
        $membersNormalized = true;
    }

    $birthDateRaw = trim((string) ($member['birth_date'] ?? $member['tanggal_lahir'] ?? ''));
    $birthDate = normalize_ymd_date($birthDateRaw);
    $legacyBirthDayMonth = '';
    if ($birthDateRaw !== '' && $birthDate === '') {
        $legacyBirthDayMonth = normalize_member_birth_day_month_value($birthDateRaw);
    }
    if ((string) ($member['birth_date'] ?? '') !== $birthDate || array_key_exists('tanggal_lahir', $member)) {
        $membersNormalized = true;
    }

    $birthDayMonthRaw = trim((string) ($member['birth_day_month'] ?? $member['tanggal_bulan_lahir'] ?? ''));
    $birthDayMonth = normalize_member_birth_day_month_value($birthDayMonthRaw);
    if ($birthDayMonth === '' && $legacyBirthDayMonth !== '') {
        $birthDayMonth = $legacyBirthDayMonth;
        $membersNormalized = true;
    }
    if ($birthDate !== '') {
        $timestamp = strtotime($birthDate);
        if ($timestamp !== false) {
            $derivedBirthDayMonth = date('d-m', $timestamp);
            if ($birthDayMonth !== $derivedBirthDayMonth) {
                $birthDayMonth = $derivedBirthDayMonth;
                $membersNormalized = true;
            }
        }
    }
    if ((string) ($member['birth_day_month'] ?? '') !== $birthDayMonth || array_key_exists('tanggal_bulan_lahir', $member)) {
        $membersNormalized = true;
    }

    $whatsapp = trim((string) ($member['whatsapp'] ?? $member['phone'] ?? ''));
    if (!array_key_exists('whatsapp', $member)) {
        $membersNormalized = true;
    }

    $birthPlace = trim((string) ($member['birth_place'] ?? ($member['tempat_lahir'] ?? '')));
    if ((string) ($member['birth_place'] ?? '') !== $birthPlace || array_key_exists('tempat_lahir', $member)) {
        $membersNormalized = true;
    }

    $address = trim((string) ($member['address'] ?? ($member['alamat'] ?? '')));
    if ((string) ($member['address'] ?? '') !== $address || array_key_exists('alamat', $member)) {
        $membersNormalized = true;
    }

    $emailRaw = trim((string) ($member['email'] ?? ''));
    $email = strtolower($emailRaw);
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
        $membersNormalized = true;
    }
    if ((string) ($member['email'] ?? '') !== $email) {
        $membersNormalized = true;
    }

    if (
        array_key_exists('origin_church', $member)
        || array_key_exists('gereja_asal', $member)
        || array_key_exists('origin_church_address', $member)
        || array_key_exists('alamat_gereja', $member)
    ) {
        $membersNormalized = true;
    }

    $socialMedia = normalize_social_link_value((string) ($member['social_media'] ?? $member['social_media_link'] ?? $member['sosmed'] ?? ''));
    if ((string) ($member['social_media'] ?? '') !== $socialMedia || array_key_exists('social_media_link', $member) || array_key_exists('sosmed', $member)) {
        $membersNormalized = true;
    }

    $membershipStatusRaw = (string) ($member['membership_status'] ?? ($member['status'] ?? 'active'));
    if (array_key_exists('is_active', $member)) {
        $membershipStatusRaw = parse_bool_value($member['is_active']) ? 'active' : 'left';
        $membersNormalized = true;
    }
    $membershipStatus = normalize_member_status_value($membershipStatusRaw);
    if ((string) ($member['membership_status'] ?? '') !== $membershipStatus || array_key_exists('status', $member)) {
        $membersNormalized = true;
    }

    $leftReason = trim((string) ($member['left_reason'] ?? $member['exit_reason'] ?? $member['alasan_keluar'] ?? ''));
    if ((string) ($member['left_reason'] ?? '') !== $leftReason || array_key_exists('exit_reason', $member) || array_key_exists('alasan_keluar', $member)) {
        $membersNormalized = true;
    }

    $photos = extract_member_photos($member);
    if (!array_key_exists('photos', $member)) {
        $membersNormalized = true;
    }
    if (array_key_exists('photo_path', $member) || array_key_exists('photo_name', $member)) {
        $membersNormalized = true;
    }
    $rawPhotos = $member['photos'] ?? [];
    if (!is_array($rawPhotos)) {
        $membersNormalized = true;
    } else {
        $rawNormalized = extract_member_photos(['photos' => $rawPhotos]);
        if ($rawNormalized !== $photos) {
            $membersNormalized = true;
        }
    }

    $familyIds = $member['family_ids'] ?? [];
    if (!is_array($familyIds)) {
        $familyIds = [];
        $membersNormalized = true;
    }
    $cleanFamilyIds = [];
    foreach ($familyIds as $familyId) {
        $familyId = trim((string) $familyId);
        if ($familyId === '' || $familyId === $id) {
            if ($familyId !== '') {
                $membersNormalized = true;
            }
            continue;
        }
        $cleanFamilyIds[] = $familyId;
    }
    $cleanFamilyIds = array_values(array_unique($cleanFamilyIds));
    if (($member['family_ids'] ?? null) !== $cleanFamilyIds) {
        $membersNormalized = true;
    }

    $createdAt = (string) ($member['created_at'] ?? now_iso());
    $updatedAt = (string) ($member['updated_at'] ?? $createdAt);
    if (!isset($member['created_at']) || !isset($member['updated_at'])) {
        $membersNormalized = true;
    }

    $leftAt = trim((string) ($member['left_at'] ?? $member['exit_at'] ?? ''));
    if ($membershipStatus === 'left' && $leftAt === '') {
        $leftAt = $updatedAt;
        $membersNormalized = true;
    }
    if ($membershipStatus !== 'left') {
        if ($leftReason !== '' || $leftAt !== '') {
            $membersNormalized = true;
        }
        $leftReason = '';
        $leftAt = '';
    }
    if ((string) ($member['left_at'] ?? '') !== $leftAt || array_key_exists('exit_at', $member)) {
        $membersNormalized = true;
    }

    $cleanMembers[] = [
        'id' => $id,
        'full_name' => $fullName,
        'gender' => $gender,
        'birth_date' => $birthDate,
        'birth_day_month' => $birthDayMonth,
        'whatsapp' => $whatsapp,
        'birth_place' => $birthPlace,
        'address' => $address,
        'email' => $email,
        'social_media' => $socialMedia,
        'membership_status' => $membershipStatus,
        'left_reason' => $leftReason,
        'left_at' => $leftAt,
        'photos' => $photos,
        'family_ids' => $cleanFamilyIds,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ];
}
if (count($cleanMembers) !== count($members)) {
    $membersNormalized = true;
}
$members = array_values($cleanMembers);
if (sync_member_family_links($members)) {
    $membersNormalized = true;
}
if ($membersNormalized) {
    persist_people_registry_data($members, $mskClasses);
}

$mskClassesNormalized = false;
$cleanMskClasses = [];
$seenMskClassIds = [];
$membersByIdForMsk = index_by_id($members);
$memberIdByUniqueFullName = [];
$memberDuplicateFullNameMap = [];
foreach ($members as $memberForMskLink) {
    if (!is_array($memberForMskLink)) {
        continue;
    }

    $memberForMskId = trim((string) ($memberForMskLink['id'] ?? ''));
    if ($memberForMskId === '') {
        continue;
    }

    $memberForMskName = trim((string) ($memberForMskLink['full_name'] ?? ''));
    $memberForMskName = preg_replace('/\s+/', ' ', $memberForMskName) ?? $memberForMskName;
    $memberForMskNameKey = strtolower($memberForMskName);
    if ($memberForMskNameKey === '') {
        continue;
    }

    if (isset($memberIdByUniqueFullName[$memberForMskNameKey])) {
        $memberDuplicateFullNameMap[$memberForMskNameKey] = true;
        continue;
    }

    $memberIdByUniqueFullName[$memberForMskNameKey] = $memberForMskId;
}
foreach (array_keys($memberDuplicateFullNameMap) as $duplicateFullNameKey) {
    unset($memberIdByUniqueFullName[$duplicateFullNameKey]);
}
foreach ($mskClasses as $participant) {
    if (!is_array($participant)) {
        $mskClassesNormalized = true;
        continue;
    }

    $id = trim((string) ($participant['id'] ?? ''));
    if ($id === '' || isset($seenMskClassIds[$id])) {
        $id = generate_id('msk');
        $mskClassesNormalized = true;
    }
    $seenMskClassIds[$id] = true;

    $memberId = trim((string) ($participant['member_id'] ?? ''));
    if ($memberId !== '' && !isset($membersByIdForMsk[$memberId])) {
        $memberId = '';
        $mskClassesNormalized = true;
    }
    if (array_key_exists('source_type', $participant) || array_key_exists('converted_member_id', $participant)) {
        $mskClassesNormalized = true;
    }

    $fullName = trim((string) ($participant['full_name'] ?? $participant['name'] ?? ''));
    $fullName = preg_replace('/\s+/', ' ', $fullName) ?? $fullName;
    $fullName = trim($fullName);
    if ($memberId === '' && $fullName !== '') {
        $fullNameKey = strtolower($fullName);
        if (isset($memberIdByUniqueFullName[$fullNameKey])) {
            $memberId = (string) $memberIdByUniqueFullName[$fullNameKey];
            $mskClassesNormalized = true;
        }
    }
    if ($fullName === '' && $memberId !== '') {
        if (isset($membersByIdForMsk[$memberId])) {
            $fullName = trim((string) ($membersByIdForMsk[$memberId]['full_name'] ?? ''));
            if ($fullName !== '') {
                $mskClassesNormalized = true;
            }
        }
    }
    if ($fullName === '') {
        $fullName = 'Tanpa Nama';
        $mskClassesNormalized = true;
    }
    if (!array_key_exists('full_name', $participant)) {
        $mskClassesNormalized = true;
    }

    $genderRaw = (string) ($participant['gender'] ?? '');
    $gender = normalize_member_gender_value($genderRaw);
    if ($genderRaw !== $gender || !array_key_exists('gender', $participant)) {
        $mskClassesNormalized = true;
    }

    $birthDateRaw = trim((string) ($participant['birth_date'] ?? ''));
    $birthDate = normalize_ymd_date($birthDateRaw);
    if ($birthDateRaw !== $birthDate || !array_key_exists('birth_date', $participant)) {
        $mskClassesNormalized = true;
    }

    $birthDayMonthRaw = trim((string) ($participant['birth_day_month'] ?? ''));
    $birthDayMonth = normalize_member_birth_day_month_value($birthDayMonthRaw);
    if ($birthDate !== '') {
        $timestamp = strtotime($birthDate);
        if ($timestamp !== false) {
            $derivedBirthDayMonth = date('d-m', $timestamp);
            if ($birthDayMonth !== $derivedBirthDayMonth) {
                $birthDayMonth = $derivedBirthDayMonth;
                $mskClassesNormalized = true;
            }
        }
    }
    if ($birthDayMonthRaw !== $birthDayMonth || !array_key_exists('birth_day_month', $participant)) {
        $mskClassesNormalized = true;
    }

    $whatsapp = trim((string) ($participant['whatsapp'] ?? ''));
    if ((string) ($participant['whatsapp'] ?? '') !== $whatsapp) {
        $mskClassesNormalized = true;
    }

    $birthPlace = trim((string) ($participant['birth_place'] ?? ($participant['tempat_lahir'] ?? '')));
    if ((string) ($participant['birth_place'] ?? '') !== $birthPlace || array_key_exists('tempat_lahir', $participant)) {
        $mskClassesNormalized = true;
    }

    $address = trim((string) ($participant['address'] ?? ($participant['alamat'] ?? '')));
    if ((string) ($participant['address'] ?? '') !== $address || array_key_exists('alamat', $participant)) {
        $mskClassesNormalized = true;
    }

    $emailRaw = trim((string) ($participant['email'] ?? ''));
    $email = strtolower($emailRaw);
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $email = '';
        $mskClassesNormalized = true;
    }
    if ((string) ($participant['email'] ?? '') !== $email) {
        $mskClassesNormalized = true;
    }

    if (
        array_key_exists('origin_church', $participant)
        || array_key_exists('gereja_asal', $participant)
        || array_key_exists('origin_church_address', $participant)
        || array_key_exists('alamat_gereja', $participant)
    ) {
        $mskClassesNormalized = true;
    }

    $photos = extract_msk_participant_photos($participant);
    if (!array_key_exists('photos', $participant) || array_key_exists('photo_path', $participant) || array_key_exists('photo_name', $participant)) {
        $mskClassesNormalized = true;
    }
    $rawPhotos = $participant['photos'] ?? [];
    if (!is_array($rawPhotos)) {
        $mskClassesNormalized = true;
    } else {
        $rawNormalized = extract_msk_participant_photos(['photos' => $rawPhotos]);
        if ($rawNormalized !== $photos) {
            $mskClassesNormalized = true;
        }
    }

    $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? ($participant['sessions'] ?? []));
    if (($participant['session_numbers'] ?? null) !== $sessionNumbers || array_key_exists('sessions', $participant)) {
        $mskClassesNormalized = true;
    }

    $notes = trim((string) ($participant['notes'] ?? ''));
    if ((string) ($participant['notes'] ?? '') !== $notes) {
        $mskClassesNormalized = true;
    }

    $mskMonthRaw = trim((string) ($participant['msk_month'] ?? ($participant['msk_period'] ?? '')));
    if ($mskMonthRaw === '') {
        $createdAtMonthFallback = '';
        $createdAtDate = normalize_ymd_date((string) ($participant['created_at'] ?? ''));
        if ($createdAtDate !== '') {
            $createdAtMonthFallback = substr($createdAtDate, 0, 7);
        }
        $mskMonth = normalize_month_value($createdAtMonthFallback !== '' ? $createdAtMonthFallback : date('Y-m'));
        $mskClassesNormalized = true;
    } else {
        $mskMonth = normalize_month_value($mskMonthRaw);
        if ($mskMonthRaw !== $mskMonth || !array_key_exists('msk_month', $participant) || array_key_exists('msk_period', $participant)) {
            $mskClassesNormalized = true;
        }
    }

    $completedAt = trim((string) ($participant['completed_at'] ?? ''));
    if (count($sessionNumbers) === 12 && $completedAt === '') {
        $completedAt = (string) ($participant['updated_at'] ?? $participant['created_at'] ?? now_iso());
        $mskClassesNormalized = true;
    }
    if ((string) ($participant['completed_at'] ?? '') !== $completedAt) {
        $mskClassesNormalized = true;
    }

    $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participant['journey_bridge_status'] ?? 'belum'));
    if (!isset($participant['journey_bridge_status']) || (string) ($participant['journey_bridge_status'] ?? '') !== $journeyBridgeStatus) {
        $mskClassesNormalized = true;
    }
    $participantStatus = normalize_msk_participant_status((string) ($participant['status'] ?? 'active'));
    if (!isset($participant['status']) || (string) ($participant['status'] ?? '') !== $participantStatus) {
        $mskClassesNormalized = true;
    }

    $createdAt = (string) ($participant['created_at'] ?? now_iso());
    $updatedAt = (string) ($participant['updated_at'] ?? $createdAt);
    if (!isset($participant['created_at']) || !isset($participant['updated_at'])) {
        $mskClassesNormalized = true;
    }

    $cleanMskClasses[] = [
        'id' => $id,
        'member_id' => $memberId,
        'full_name' => $fullName,
        'gender' => $gender,
        'birth_date' => $birthDate,
        'birth_day_month' => $birthDayMonth,
        'whatsapp' => $whatsapp,
        'birth_place' => $birthPlace,
        'address' => $address,
        'email' => $email,
        'photos' => $photos,
        'msk_month' => $mskMonth,
        'session_numbers' => $sessionNumbers,
        'notes' => $notes,
        'completed_at' => $completedAt,
        'journey_bridge_status' => $journeyBridgeStatus,
        'status' => $participantStatus,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ];
}
if (count($cleanMskClasses) !== count($mskClasses)) {
    $mskClassesNormalized = true;
}
$mskClasses = array_values($cleanMskClasses);

$membersChangedByMsk = false;
foreach ($mskClasses as $idx => $participant) {
    $beforeMemberCount = count($members);
    if (auto_register_msk_participant_as_member($mskClasses[$idx], $members)) {
        $mskClassesNormalized = true;
    }
    if (sync_member_data_from_msk($mskClasses[$idx], $members)) {
        $mskClassesNormalized = true;
        $membersChangedByMsk = true;
    }
    if (count($members) !== $beforeMemberCount) {
        $membersChangedByMsk = true;
    }
}
if ($membersChangedByMsk) {
    if (sync_member_family_links($members)) {
        $membersNormalized = true;
    }
    persist_people_registry_data($members, $mskClasses);
}
if ($mskClassesNormalized) {
    persist_people_registry_data($members, $mskClasses);
}

$churchFilesNormalized = false;
$cleanChurchFiles = [];
$seenChurchFileIds = [];
foreach ($churchFiles as $entry) {
    if (!is_array($entry)) {
        $churchFilesNormalized = true;
        continue;
    }

    $id = trim((string) ($entry['id'] ?? ''));
    if ($id === '' || isset($seenChurchFileIds[$id])) {
        $id = generate_id('church_file');
        $churchFilesNormalized = true;
    }
    $seenChurchFileIds[$id] = true;

    $title = trim((string) ($entry['title'] ?? ''));
    if ($title === '') {
        $title = trim((string) ($entry['file_name'] ?? $entry['name'] ?? 'Dokumen'));
        if ($title === '') {
            $title = 'Dokumen';
        }
        $churchFilesNormalized = true;
    }

    $category = normalize_church_file_category((string) ($entry['category'] ?? 'Lainnya'));
    if ((string) ($entry['category'] ?? 'Lainnya') !== $category) {
        $churchFilesNormalized = true;
    }

    $description = trim((string) ($entry['description'] ?? ''));
    if ((string) ($entry['description'] ?? '') !== $description) {
        $churchFilesNormalized = true;
    }

    $path = sanitize_relative_upload_path((string) ($entry['path'] ?? $entry['file_path'] ?? ''));
    if ($path === '') {
        $churchFilesNormalized = true;
        continue;
    }
    if ((string) ($entry['path'] ?? '') !== $path || array_key_exists('file_path', $entry)) {
        $churchFilesNormalized = true;
    }
    $fullPath = legacy_runtime_path($path);
    if (!is_file($fullPath)) {
        $churchFilesNormalized = true;
        continue;
    }

    $fileName = trim((string) ($entry['file_name'] ?? $entry['name'] ?? basename($path)));
    if ($fileName === '') {
        $fileName = basename($path);
        $churchFilesNormalized = true;
    }
    if ((string) ($entry['file_name'] ?? '') !== $fileName || array_key_exists('name', $entry)) {
        $churchFilesNormalized = true;
    }

    $size = (int) ($entry['size'] ?? 0);
    $actualSize = (int) @filesize($fullPath);
    if ($size <= 0 && $actualSize > 0) {
        $size = $actualSize;
        $churchFilesNormalized = true;
    }
    if ($size < 0) {
        $size = 0;
        $churchFilesNormalized = true;
    }

    $mime = trim((string) ($entry['mime'] ?? ''));
    if (!array_key_exists('mime', $entry)) {
        $churchFilesNormalized = true;
    }

    $uploadedAt = (string) ($entry['uploaded_at'] ?? $entry['created_at'] ?? now_iso());
    $updatedAt = (string) ($entry['updated_at'] ?? $uploadedAt);
    if (!isset($entry['uploaded_at']) || !isset($entry['updated_at'])) {
        $churchFilesNormalized = true;
    }

    $cleanChurchFiles[] = [
        'id' => $id,
        'title' => $title,
        'category' => $category,
        'description' => $description,
        'path' => $path,
        'file_name' => $fileName,
        'size' => $size,
        'mime' => $mime,
        'uploaded_at' => $uploadedAt,
        'updated_at' => $updatedAt,
    ];
}
if (count($cleanChurchFiles) !== count($churchFiles)) {
    $churchFilesNormalized = true;
}
$churchFiles = array_values($cleanChurchFiles);
if ($churchFilesNormalized) {
    write_json(data_path('church_files'), $churchFiles);
}


// Do not persist runtime projections during page boot. Login and read-only page loads
// must not rewrite branch-scoped data such as DG reports or member/MSK records.
if (!is_file(data_path('church_files'))) {
    write_json(data_path('church_files'), $churchFiles);
}

if (is_effective_central_discipleship_readonly()) {
    $centralSnapshot = build_central_discipleship_snapshot();
    $centralSelectedBranch = central_recap_selected_branch();
    $centralSnapshot = filter_central_snapshot_by_branch($centralSnapshot, $centralSelectedBranch);
    $people = is_array($centralSnapshot['people'] ?? null) ? array_values($centralSnapshot['people']) : [];
    $groups = is_array($centralSnapshot['groups'] ?? null) ? array_values($centralSnapshot['groups']) : [];
    $dgMeetingReports = is_array($centralSnapshot['dg_meeting_reports'] ?? null) ? array_values($centralSnapshot['dg_meeting_reports']) : [];
    $members = is_array($centralSnapshot['members'] ?? null) ? array_values($centralSnapshot['members']) : [];
    $mskClasses = is_array($centralSnapshot['msk_classes'] ?? null) ? array_values($centralSnapshot['msk_classes']) : [];
    $memberMskUnifiedRecords = is_array($centralSnapshot[PEOPLE_REGISTRY_DATA_NAME] ?? null) ? array_values($centralSnapshot[PEOPLE_REGISTRY_DATA_NAME]) : [];
    $discipleshipV2Model = dgv2_normalize_model(is_array($centralSnapshot['discipleship_v2_model'] ?? null) ? $centralSnapshot['discipleship_v2_model'] : []);
    $memberMskViews = [
        'members' => $members,
        'msk_classes' => $mskClasses,
        'people' => $people,
    ];
    $peopleById = index_by_id($people);
}

$page = $_GET['page'] ?? 'kutisari';
$page = trim((string) $page);
$action = $_POST['action'] ?? '';
$publicPages = ['kutisari', 'public_links', 'login', 'public_dg_branch', 'public_dg_report', 'public_member_feedback_branch', 'public_member_feedback', 'public_menu_empty', 'public_materials', 'public_material_download', 'public_material_preview', 'public_difficult_question_submit', 'public_difficult_answer_lookup'];
$publicActions = ['save_public_dg_report', 'save_public_member_feedback', 'submit_difficult_question', 'lookup_difficult_answer'];
$publicDgReportError = '';
$publicDgReportOld = [];
$publicMemberFeedbackError = '';
$publicMemberFeedbackOld = [];

if ($action !== '' && !in_array($action, $publicActions, true) && !is_valid_post_origin()) {
    http_response_code(403);
    legacy_exit('Permintaan ditolak demi keamanan.');
}

require __DIR__ . '/actions/login.php';


require __DIR__ . '/actions/change_password.php';


require __DIR__ . '/actions/logout.php';


if (!is_logged_in()) {
    if ($action !== '') {
        if (!in_array($action, $publicActions, true)) {
            redirect_to('login');
        }
    } elseif (!in_array($page, $publicPages, true)) {
        redirect_to('login');
    }
}

if (is_logged_in()) {
    $lastActiveAt = (int) ($_SESSION['last_active_at'] ?? 0);
    $now = time();
    $maxIdleSeconds = 60 * 60 * 8;
    if ($lastActiveAt > 0 && ($now - $lastActiveAt) > $maxIdleSeconds) {
        destroy_current_session();
        redirect_to('login', ['expired' => 1]);
    }
    if (find_auth_account_by_username(current_username()) === null) {
        destroy_current_session();
        redirect_to('login', ['account_removed' => 1]);
    }
    $_SESSION['last_active_at'] = $now;
    $_SESSION['cabang'] = current_user_branch();
    $_SESSION['access_scope'] = normalize_auth_access_scope((string) ($_SESSION['access_scope'] ?? 'branch'));
    if (trim((string) ($_SESSION['login_at'] ?? '')) === '') {
        $_SESSION['login_at'] = now_iso();
        update_user_last_login(current_username(), (string) $_SESSION['login_at']);
    }
    unset($_SESSION['role']);
}

if (is_logged_in()) {
    $branch = current_user_branch();
    if (!branch_can_access_page($branch, $page)) {
        redirect_to(branch_home_page($branch), ['error' => 'access_denied']);
    }
    if ($action !== '' && !branch_can_use_action($branch, $action)) {
        redirect_to(branch_home_page($branch), ['error' => 'access_denied']);
    }
}

require resource_path('views/pages/login.blade.php');


require resource_path('views/pages/secure_file.blade.php');


// Handle actions
require __DIR__ . '/actions/dispatch.php';


require_once __DIR__ . '/support/person_label.php';


require_once resource_path('views/partials/icon_svg.blade.php');


require_once __DIR__ . '/support/get_parent_ids.php';


require_once __DIR__ . '/support/format_parent_names.php';


require_once __DIR__ . '/support/update_roles_based_on_children.php';


require_once __DIR__ . '/support/normalize_groups.php';


require_once resource_path('views/partials/render_people_tree.blade.php');


require_once resource_path('views/partials/render_people_tree_v2.blade.php');


require_once __DIR__ . '/support/build_people_tree_group_rows.php';


require_once resource_path('views/partials/render_people_tree_v2_group_branch.blade.php');


require_once resource_path('views/partials/render_people_tree_v3_group_branch.blade.php');


require_once __DIR__ . '/support/build_people_tree_group_history_views.php';


require_once resource_path('views/partials/render_people_tree_v3.blade.php');


require_once __DIR__ . '/support/attach_people_tree_group_children.php';


require_once __DIR__ . '/support/build_pohon_pemuridan_dot_content.php';


require_once __DIR__ . '/support/pohon_dot_person_name.php';


require_once __DIR__ . '/support/pohon_dot_primary_group_leader_name.php';


require_once __DIR__ . '/support/pohon_dot_group_stage.php';


require_once __DIR__ . '/support/pohon_dot_person_label.php';


require_once __DIR__ . '/support/pohon_dot_group_label.php';


require_once __DIR__ . '/support/pohon_dot_group_node_id.php';


require_once __DIR__ . '/support/pohon_dot_id.php';


require_once __DIR__ . '/support/pohon_dot_quote.php';


require_once __DIR__ . '/support/pohon_dot_attrs.php';



require_once resource_path('views/partials/render_central_rekap_toolbar.blade.php');


require_once resource_path('views/partials/append_body_classes.blade.php');


require_once resource_path('views/partials/body_class_attr.blade.php');


require_once resource_path('views/partials/render_app_document_head.blade.php');


require_once resource_path('views/partials/render_app_script_tag.blade.php');


require_once resource_path('views/partials/render_alert.blade.php');


require_once resource_path('views/partials/render_condition_alerts.blade.php');


require_once resource_path('views/partials/render_mapped_error_alert.blade.php');


require_once __DIR__ . '/support/member_form_error_messages.php';


require_once resource_path('views/partials/page_header_active_group.blade.php');


require_once resource_path('views/partials/render_sidebar_nav_link.blade.php');


require_once resource_path('views/partials/render_sidebar_nav_group.blade.php');




require_once resource_path('views/partials/render_sidebar_navigation.blade.php');


require_once resource_path('views/partials/render_table_search_input.blade.php');


require_once resource_path('views/partials/page_header.blade.php');


require_once resource_path('views/partials/page_header_plain.blade.php');


require_once resource_path('views/partials/page_footer_plain.blade.php');


require_once resource_path('views/partials/page_footer.blade.php');


require resource_path('views/pages/worship_penatalayan_image.blade.php');


require resource_path('views/pages/kutisari.blade.php');


require resource_path('views/pages/public_difficult_question_submit.blade.php');


require resource_path('views/pages/public_difficult_answer_lookup.blade.php');


require resource_path('views/pages/public_menu_empty.blade.php');


require resource_path('views/pages/public_material_download.blade.php');


require resource_path('views/pages/public_material_preview.blade.php');


require resource_path('views/pages/public_materials.blade.php');


require resource_path('views/pages/public_member_feedback_branch.blade.php');


require resource_path('views/pages/public_member_feedback.blade.php');


require resource_path('views/pages/public_dg_branch.blade.php');


require resource_path('views/pages/public_dg_report.blade.php');


require resource_path('views/pages/login_17258.blade.php');


// Pages
require resource_path('views/pages/dashboard.blade.php');


require resource_path('views/pages/worship_penatalayan.blade.php');


require resource_path('views/pages/people.blade.php');


require resource_path('views/pages/discipleship_dashboard_group_people.blade.php');


require resource_path('views/pages/people_tree.blade.php');


require resource_path('views/pages/people_tree_v2.blade.php');


require resource_path('views/pages/spiritual_journey.blade.php');

require resource_path('views/pages/dg_reports_recap.blade.php');


require resource_path('views/pages/member_dashboard.blade.php');


require resource_path('views/pages/member_completeness.blade.php');


require resource_path('views/pages/members.blade.php');


require resource_path('views/pages/member_families.blade.php');


require resource_path('views/pages/msk_classes.blade.php');


require resource_path('views/pages/member_birthdays.blade.php');


require resource_path('views/pages/difficult_questions_admin.blade.php');


require resource_path('views/pages/settings.blade.php');


require resource_path('views/pages/discipleship_targets.blade.php');


require resource_path('views/pages/fallback.blade.php');

?>
