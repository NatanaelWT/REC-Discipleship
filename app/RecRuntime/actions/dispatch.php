<?php

if ($action !== '') {
    if ($action === 'submit_difficult_question') {
        $page = 'public_difficult_question_submit';
        $askerName = normalize_difficult_question_text((string) ($_POST['asker_name'] ?? ''), 120);
        $questionText = normalize_difficult_question_text((string) ($_POST['question_text'] ?? ''), 6000);
        $password = trim((string) ($_POST['question_password'] ?? ''));
        $passwordConfirm = trim((string) ($_POST['question_password_confirm'] ?? ''));

        $_SESSION['difficult_question_old'] = [
            'asker_name' => $askerName,
            'question_text' => $questionText,
        ];

        if ($questionText === '') {
            redirect_to('public_difficult_question_submit', ['error' => 'missing_question']);
        }
        if (strlen($password) < 4) {
            redirect_to('public_difficult_question_submit', ['error' => 'password_short']);
        }
        if ($password !== $passwordConfirm) {
            redirect_to('public_difficult_question_submit', ['error' => 'password_mismatch']);
        }

        $nowIso = now_iso();
        $difficultQuestions[] = [
            'id' => generate_id('dq'),
            'asker_name' => $askerName,
            'question' => $questionText,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'password_lookup' => difficult_question_password_lookup($password),
            'status' => 'pending',
            'answer' => '',
            'answered_by' => '',
            'answered_at' => '',
            'created_at' => $nowIso,
            'updated_at' => $nowIso,
        ];
        usort($difficultQuestions, static function ($a, $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        if (!write_json_unrestricted(data_path('difficult_questions'), array_values($difficultQuestions))) {
            redirect_to('public_difficult_question_submit', ['error' => 'save_failed']);
        }

        unset($_SESSION['difficult_question_old']);
        redirect_to('public_difficult_question_submit', ['submitted' => 1]);
    }

    if ($action === 'lookup_difficult_answer') {
        $page = 'public_difficult_answer_lookup';
        $password = trim((string) ($_POST['question_password'] ?? ''));
        if (strlen($password) < 4) {
            unset($_SESSION['difficult_answer_lookup_hash']);
            redirect_to('public_difficult_answer_lookup', ['error' => 'password_required']);
        }
        $_SESSION['difficult_answer_lookup_hash'] = difficult_question_password_lookup($password);
        redirect_to('public_difficult_answer_lookup', ['looked' => 1]);
    }

    if ($action === 'save_difficult_question_answer') {
        if (!can_manage_difficult_questions()) {
            redirect_to(branch_home_page(current_user_branch()), ['error' => 'access_denied']);
        }

        $questionId = trim((string) ($_POST['id'] ?? ''));
        $answerText = normalize_difficult_question_text((string) ($_POST['answer_text'] ?? ''), 8000);
        if ($questionId === '') {
            redirect_to('difficult_questions_admin', ['error' => 'missing_question']);
        }
        if ($answerText === '') {
            redirect_to('difficult_questions_admin', ['error' => 'missing_answer']);
        }

        $targetIndex = null;
        foreach ($difficultQuestions as $index => $questionRow) {
            if (!is_array($questionRow)) {
                continue;
            }
            if (trim((string) ($questionRow['id'] ?? '')) === $questionId) {
                $targetIndex = $index;
                break;
            }
        }
        if ($targetIndex === null) {
            redirect_to('difficult_questions_admin', ['error' => 'question_not_found']);
        }

        $nowIso = now_iso();
        $difficultQuestions[$targetIndex]['answer'] = $answerText;
        $difficultQuestions[$targetIndex]['status'] = 'answered';
        $difficultQuestions[$targetIndex]['answered_by'] = current_username();
        $difficultQuestions[$targetIndex]['answered_at'] = $nowIso;
        $difficultQuestions[$targetIndex]['updated_at'] = $nowIso;
        usort($difficultQuestions, static function ($a, $b): int {
            $aStatus = strtolower(trim((string) ($a['status'] ?? 'pending')));
            $bStatus = strtolower(trim((string) ($b['status'] ?? 'pending')));
            if ($aStatus !== $bStatus) {
                return $aStatus === 'pending' ? -1 : 1;
            }
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        if (!write_json_unrestricted(data_path('difficult_questions'), array_values($difficultQuestions))) {
            redirect_to('difficult_questions_admin', ['error' => 'save_failed']);
        }

        redirect_to('difficult_questions_admin', ['answered' => 1]);
    }

    if ($action === 'save_public_dg_report') {
        $page = 'public_dg_report';
        $publicDgReportOld = $_POST;
        $publicBranchRaw = trim((string) ($_POST['public_cabang'] ?? ''));
        if ($publicBranchRaw === '') {
            // Tidak ada cabang dari form — fallback ke cabang user jika login, redirect jika tidak
            if (is_logged_in()) {
                $publicBranch = current_user_branch();
            } else {
                redirect_to('public_dg_branch');
            }
        } elseif (!is_known_public_branch_code($publicBranchRaw)) {
            redirect_to('public_dg_branch', ['error' => 'invalid_branch']);
        } else {
            // Selalu gunakan cabang dari form (public_cabang), terlepas user login atau tidak
            $publicBranch = normalize_public_branch_code($publicBranchRaw);
        }
        $publicDgReportOld['public_cabang'] = $publicBranch;
        if (!isset($publicDgReportOld['absent_member_ids']) || !is_array($publicDgReportOld['absent_member_ids'])) {
            $publicDgReportOld['absent_member_ids'] = [];
        }
        if (!isset($publicDgReportOld['meditation_sharer_ids']) || !is_array($publicDgReportOld['meditation_sharer_ids'])) {
            $publicDgReportOld['meditation_sharer_ids'] = [];
        }

        $branchRuntime = load_branch_discipleship_runtime($publicBranch);
        $peopleById = is_array($branchRuntime['people_by_id'] ?? null) ? $branchRuntime['people_by_id'] : [];
        $branchGroups = is_array($branchRuntime['groups'] ?? null) ? $branchRuntime['groups'] : [];
        $dgMeetingReports = is_array($branchRuntime['dg_meeting_reports'] ?? null) ? $branchRuntime['dg_meeting_reports'] : [];
        $dgFormData = build_dg_public_form_data($branchGroups, $peopleById);
        $groupMap = $dgFormData['group_map'] ?? [];
        $leaderMap = [];
        foreach (($dgFormData['leaders'] ?? []) as $leaderRow) {
            $leaderIdKey = trim((string) ($leaderRow['id'] ?? ''));
            if ($leaderIdKey !== '') {
                $leaderMap[$leaderIdKey] = true;
            }
        }

        $leaderId = trim((string) ($_POST['leader_id'] ?? ''));
        $groupId = trim((string) ($_POST['group_id'] ?? ''));
        $meetingDate = normalize_ymd_date((string) ($_POST['meeting_date'] ?? ''));
        $materialTopic = trim((string) ($_POST['material_topic'] ?? ''));
        $materialTopicOther = trim((string) ($_POST['material_topic_other'] ?? ''));
        $absenceReason = trim((string) ($_POST['absence_reason'] ?? ''));
        $additionalNotes = trim((string) ($_POST['additional_notes'] ?? ''));

        $materialOptions = [];
        for ($i = 1; $i <= 12; $i++) {
            $materialOptions[] = 'Sesi ' . $i;
        }
        $materialOptions[] = 'Lainnya';

        $groupRow = null;
        if (count($groupMap) === 0) {
            $publicDgReportError = 'Belum ada Kelompok DG yang bisa dipilih.';
        } elseif ($leaderId === '' || !isset($leaderMap[$leaderId])) {
            $publicDgReportError = 'Pilih nama pemimpin DG terlebih dahulu.';
        } elseif ($groupId === '' || !isset($groupMap[$groupId])) {
            $publicDgReportError = 'Pilih kelompok DG terlebih dahulu.';
        } else {
            $groupRow = $groupMap[$groupId];
            $groupLeaderId = trim((string) ($groupRow['leader_id'] ?? ''));
            if ($groupLeaderId !== $leaderId) {
                $publicDgReportError = 'Kelompok yang dipilih tidak sesuai dengan pemimpin DG.';
            }
        }

        if ($publicDgReportError === '' && $meetingDate === '') {
            $publicDgReportError = 'Tanggal pelaksanaan tidak valid.';
        }

        if ($publicDgReportError === '' && !in_array($materialTopic, $materialOptions, true)) {
            $publicDgReportError = 'Pilih materi DG yang dibahas.';
        }
        if ($publicDgReportError === '' && $materialTopic === 'Lainnya' && $materialTopicOther === '') {
            $publicDgReportError = 'Isi materi DG pada kolom lainnya.';
        }
        $materialLabel = $materialTopic === 'Lainnya' ? $materialTopicOther : $materialTopic;

        $groupMemberMap = [];
        $requiredMeditationTimes = 2;
        $groupProgressAtMeeting = 'DG 1';
        if ($groupRow !== null) {
            $groupProgressAtMeeting = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
            if ($groupProgressAtMeeting === '') {
                $groupProgressAtMeeting = 'DG 1';
            }
            $requiredMeditationTimes = dg_progress_min_share_times($groupProgressAtMeeting);
            $groupMembers = $groupRow['members'] ?? [];
            if (!is_array($groupMembers)) {
                $groupMembers = [];
            }
            foreach ($groupMembers as $memberRow) {
                $memberId = trim((string) ($memberRow['id'] ?? ''));
                $memberName = trim((string) ($memberRow['name'] ?? ''));
                if ($memberId === '' || $memberName === '') {
                    continue;
                }
                $groupMemberMap[$memberId] = $memberName;
            }
        }

        $absentMemberIdsRaw = $_POST['absent_member_ids'] ?? [];
        if (!is_array($absentMemberIdsRaw)) {
            $absentMemberIdsRaw = [];
        }
        $absentMemberIds = [];
        foreach ($absentMemberIdsRaw as $memberId) {
            $memberId = trim((string) $memberId);
            if ($memberId === '' || !isset($groupMemberMap[$memberId]) || in_array($memberId, $absentMemberIds, true)) {
                continue;
            }
            $absentMemberIds[] = $memberId;
        }
        if ($publicDgReportError === '' && count($absentMemberIds) > 0 && $absenceReason === '') {
            $publicDgReportError = 'Isi alasan anggota DG yang tidak hadir.';
        }

        $qualityPrepare = parse_bool_value($_POST['quality_prepare'] ?? false);
        $qualityPray = parse_bool_value($_POST['quality_pray'] ?? false);
        $qualityShareMeditation = parse_bool_value($_POST['quality_share_meditation'] ?? false);
        $qualityRelational = parse_bool_value($_POST['quality_relational'] ?? false);
        $sharingOpennessRaw = trim((string) ($_POST['sharing_openness'] ?? ''));
        $sharingOpenness = null;
        if ($sharingOpennessRaw !== '' && is_numeric($sharingOpennessRaw)) {
            $sharingOpenness = (int) $sharingOpennessRaw;
        }
        if ($publicDgReportError === '' && ($sharingOpenness === null || $sharingOpenness < 1 || $sharingOpenness > 10)) {
            $publicDgReportError = 'Isi nilai sharing kelompok dari 1 sampai 10.';
        }

        $meditationSharerIdsRaw = $_POST['meditation_sharer_ids'] ?? [];
        if (!is_array($meditationSharerIdsRaw)) {
            $meditationSharerIdsRaw = [];
        }
        $meditationSharerIds = [];
        foreach ($meditationSharerIdsRaw as $memberId) {
            $memberId = trim((string) $memberId);
            if ($memberId === '' || !isset($groupMemberMap[$memberId]) || in_array($memberId, $meditationSharerIds, true)) {
                continue;
            }
            $meditationSharerIds[] = $memberId;
        }
        $meetingPhotos = [];
        if ($publicDgReportError === '' && isset($_FILES['meeting_photos']) && is_array($_FILES['meeting_photos'])) {
            $photoErrorCode = '';
            $meetingPhotos = upload_dg_meeting_photos($_FILES['meeting_photos'], $photoErrorCode);
            if ($photoErrorCode !== '') {
                if ($photoErrorCode === 'invalid_dg_photo_type') {
                    $publicDgReportError = 'Format foto pertemuan tidak didukung. Gunakan JPG/PNG/WEBP.';
                } elseif ($photoErrorCode === 'dg_photo_too_large') {
                    $publicDgReportError = 'Ukuran foto pertemuan terlalu besar. Maksimal 5 MB per file.';
                } else {
                    $publicDgReportError = 'Upload foto pertemuan gagal. Coba ulangi lagi.';
                }
            }
        }

        if ($publicDgReportError === '') {
            $absentMemberNames = [];
            foreach ($absentMemberIds as $memberId) {
                if (isset($groupMemberMap[$memberId])) {
                    $absentMemberNames[] = $groupMemberMap[$memberId];
                }
            }

            $meditationSharerNames = [];
            foreach ($meditationSharerIds as $memberId) {
                if (isset($groupMemberMap[$memberId])) {
                    $meditationSharerNames[] = $groupMemberMap[$memberId];
                }
            }

            $leaderName = trim((string) ($groupRow['leader_name'] ?? ''));
            $groupName = trim((string) ($groupRow['name'] ?? ''));
            if ($groupName === '') {
                $groupName = 'Kelompok';
            }

            $dgMeetingReports[] = [
                'id' => generate_id('dg_report'),
                'leader_id' => $leaderId,
                'leader_name' => $leaderName,
                'group_id' => $groupId,
                'group_name' => $groupName,
                'meeting_date' => $meetingDate,
                'material_topic' => $materialLabel,
                'absent_member_ids' => $absentMemberIds,
                'absent_member_names' => $absentMemberNames,
                'absence_reason' => $absenceReason,
                'quality_prepare' => $qualityPrepare,
                'quality_pray' => $qualityPray,
                'quality_share_meditation' => $qualityShareMeditation,
                'quality_relational' => $qualityRelational,
                'sharing_openness' => $sharingOpenness,
                'meditation_sharer_ids' => $meditationSharerIds,
                'meditation_sharer_names' => $meditationSharerNames,
                'meditation_min_times' => $requiredMeditationTimes,
                'group_progress' => $groupProgressAtMeeting,
                'additional_notes' => $additionalNotes,
                'meeting_photos' => $meetingPhotos,
                'source' => 'public_form',
                'created_at' => now_iso(),
                'updated_at' => now_iso(),
            ];
            persist_dg_meeting_reports_data($dgMeetingReports, $publicBranch);
            redirect_to('public_dg_report', ['submitted' => 1, 'cabang' => $publicBranch]);
        }
    }

    if ($action === 'save_public_member_feedback') {
        $page = 'public_member_feedback';
        $publicMemberFeedbackOld = $_POST;
        $publicBranchRaw = trim((string) ($_POST['public_cabang'] ?? ''));
        if ($publicBranchRaw === '') {
            if (is_logged_in()) {
                $publicBranch = current_user_branch();
            } else {
                redirect_to('public_member_feedback_branch');
            }
        } elseif (!is_known_public_branch_code($publicBranchRaw)) {
            redirect_to('public_member_feedback_branch', ['error' => 'invalid_branch']);
        } else {
            $publicBranch = normalize_public_branch_code($publicBranchRaw);
        }
        $publicMemberFeedbackOld['public_cabang'] = $publicBranch;

        $branchRuntime = load_branch_discipleship_runtime($publicBranch);
        $peopleById = is_array($branchRuntime['people_by_id'] ?? null) ? $branchRuntime['people_by_id'] : [];
        $branchGroups = is_array($branchRuntime['groups'] ?? null) ? $branchRuntime['groups'] : [];
        $dgFormData = build_dg_public_form_data($branchGroups, $peopleById);
        $groupMap = is_array($dgFormData['group_map'] ?? null) ? $dgFormData['group_map'] : [];

        $groupId = trim((string) ($_POST['group_id'] ?? ''));
        $respondentPersonId = trim((string) ($_POST['respondent_person_id'] ?? ''));
        $feedbackSession = normalize_public_member_feedback_session($_POST['feedback_session'] ?? '');
        $groupRow = null;
        $respondentName = '';
        $groupMemberMap = [];

        if (count($groupMap) === 0) {
            $publicMemberFeedbackError = 'Belum ada Kelompok DG yang bisa dipilih.';
        } elseif ($groupId === '' || !isset($groupMap[$groupId]) || !is_array($groupMap[$groupId])) {
            $publicMemberFeedbackError = 'Pilih kelompok DG terlebih dahulu.';
        } else {
            $groupRow = $groupMap[$groupId];
            $members = $groupRow['members'] ?? [];
            if (!is_array($members)) {
                $members = [];
            }
            foreach ($members as $memberRow) {
                if (!is_array($memberRow)) {
                    continue;
                }
                $memberId = trim((string) ($memberRow['id'] ?? ''));
                $memberName = trim((string) ($memberRow['name'] ?? ''));
                if ($memberId === '' || $memberName === '') {
                    continue;
                }
                $groupMemberMap[$memberId] = $memberName;
            }
            if ($respondentPersonId === '' || !isset($groupMemberMap[$respondentPersonId])) {
                $publicMemberFeedbackError = 'Pilih nama pengisi sesuai anggota kelompok DG.';
            } else {
                $respondentName = $groupMemberMap[$respondentPersonId];
            }
        }

        if ($publicMemberFeedbackError === '' && $feedbackSession === 0) {
            $publicMemberFeedbackError = 'Pilih pertemuan umpan balik: pertemuan 3 atau 12.';
        }

        $ratingsRaw = $_POST['ratings'] ?? [];
        if (!is_array($ratingsRaw)) {
            $ratingsRaw = [];
        }
        $ratingValues = [];
        foreach (public_member_feedback_questions() as $section) {
            $sectionRatings = $section['ratings'] ?? [];
            if (!is_array($sectionRatings)) {
                continue;
            }
            foreach ($sectionRatings as $question) {
                if (!is_array($question)) {
                    continue;
                }
                $key = trim((string) ($question['key'] ?? ''));
                $scale = (int) ($question['scale'] ?? 10);
                if ($key === '' || $scale < 1) {
                    continue;
                }
                $rawValue = trim((string) ($ratingsRaw[$key] ?? ''));
                $value = is_numeric($rawValue) ? (int) $rawValue : 0;
                if ($publicMemberFeedbackError === '' && ($value < 1 || $value > $scale)) {
                    $publicMemberFeedbackError = 'Isi semua pertanyaan skala yang wajib.';
                }
                if ($value >= 1 && $value <= $scale) {
                    $ratingValues[$key] = $value;
                }
            }
        }

        $notesRaw = $_POST['notes'] ?? [];
        if (!is_array($notesRaw)) {
            $notesRaw = [];
        }
        $noteValues = [];
        foreach (public_member_feedback_questions() as $section) {
            if (!is_array($section)) {
                continue;
            }
            $noteKey = trim((string) ($section['note_key'] ?? ''));
            if ($noteKey === '') {
                continue;
            }
            $noteValues[$noteKey] = normalize_public_member_feedback_text((string) ($notesRaw[$noteKey] ?? ''), 2500);
        }

        if ($publicMemberFeedbackError === '' && is_array($groupRow)) {
            $leaderId = trim((string) ($groupRow['leader_id'] ?? ''));
            $leaderName = trim((string) ($groupRow['leader_name'] ?? ''));
            $groupName = trim((string) ($groupRow['name'] ?? ''));
            if ($groupName === '') {
                $groupName = 'Kelompok';
            }
            $groupProgress = normalize_dg_progress_value((string) ($groupRow['progress'] ?? ''));
            if ($groupProgress === '') {
                $groupProgress = 'DG 1';
            }
            $feedbackRows = read_public_member_feedback_rows($publicBranch);
            $nowIso = now_iso();
            $feedbackRows[] = [
                'id' => generate_id('dg_member_feedback'),
                'branch_code' => $publicBranch,
                'feedback_session' => $feedbackSession,
                'leader_id' => $leaderId,
                'leader_name' => $leaderName,
                'group_id' => $groupId,
                'group_name' => $groupName,
                'group_label' => public_member_feedback_group_option_label($groupRow),
                'group_progress' => $groupProgress,
                'respondent_person_id' => $respondentPersonId,
                'respondent_name' => $respondentName,
                'ratings' => $ratingValues,
                'notes' => $noteValues,
                'source' => 'public_form',
                'created_at' => $nowIso,
                'updated_at' => $nowIso,
            ];
            usort($feedbackRows, static function ($a, $b): int {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            });
            if (!persist_public_member_feedback_rows($feedbackRows, $publicBranch)) {
                $publicMemberFeedbackError = 'Jurnal umpan balik gagal disimpan. Coba ulangi lagi.';
            } else {
                redirect_to('public_member_feedback', ['submitted' => 1, 'cabang' => $publicBranch, 'feedback_session' => $feedbackSession]);
            }
        }
    }

    if ($action === 'save_discipleship_targets') {
        $targetsInput = [
            'dg_total_people' => $_POST['target_dg_total_people'] ?? '',
            'msk_completed' => $_POST['target_msk_completed'] ?? '',
            'dg1_people' => $_POST['target_dg1_people'] ?? '',
            'dg2_people' => $_POST['target_dg2_people'] ?? '',
            'dg3_people' => $_POST['target_dg3_people'] ?? '',
        ];
        $discipleshipTargets = normalize_discipleship_targets($targetsInput);
        write_json(data_path('discipleship_targets'), $discipleshipTargets);
        redirect_to('discipleship_targets', ['saved' => 1]);
    }

    if ($action === 'import_pemuridan_excel') {
        $batchMonthInput = trim((string) ($_POST['batch_month'] ?? ''));
        $batchMonth = '';
        if ($batchMonthInput !== '') {
            if (strtolower($batchMonthInput) === 'all') {
                $batchMonth = 'all';
            } else {
                $batchMonth = import_normalize_month_strict($batchMonthInput);
            }
        }
        $importReturnPage = trim((string) ($_POST['return_page'] ?? ''));
        if (!in_array($importReturnPage, ['discipleship_dashboard', 'msk_classes'], true)) {
            $importReturnPage = 'discipleship_dashboard';
        }
        $redirectParams = [];
        if ($batchMonth !== '' && $importReturnPage === 'msk_classes') {
            $redirectParams['batch_month'] = $batchMonth;
        }

        $file = $_FILES['import_pemuridan_excel'] ?? null;
        if (!is_array($file)) {
            $redirectParams['error'] = 'import_missing_file';
            redirect_to($importReturnPage, $redirectParams);
        }
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            $redirectParams['error'] = 'import_missing_file';
            redirect_to($importReturnPage, $redirectParams);
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            $redirectParams['error'] = 'import_upload_failed';
            redirect_to($importReturnPage, $redirectParams);
        }
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $redirectParams['error'] = 'import_upload_failed';
            redirect_to($importReturnPage, $redirectParams);
        }
        $originalName = trim((string) ($file['name'] ?? ''));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $redirectParams['error'] = 'import_invalid_file_type';
            redirect_to($importReturnPage, $redirectParams);
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > (10 * 1024 * 1024)) {
            $redirectParams['error'] = 'import_file_too_large';
            redirect_to($importReturnPage, $redirectParams);
        }

        $xlsxError = '';
        $sheets = import_read_xlsx_sheets($tmpPath, $xlsxError);
        if ($xlsxError !== '') {
            $redirectParams['error'] = $xlsxError === 'zip_unavailable' ? 'import_zip_unavailable' : 'import_invalid_excel';
            redirect_to($importReturnPage, $redirectParams);
        }

        $sheetMap = [];
        foreach ($sheets as $sheetName => $rows) {
            $sheetMap[import_sheet_name_key((string) $sheetName)] = is_array($rows) ? $rows : [];
        }
        $mskRows = $sheetMap['kelas msk'] ?? null;
        if (!is_array($mskRows)) {
            $redirectParams['error'] = 'import_missing_sheet';
            redirect_to($importReturnPage, $redirectParams);
        }
        if (count($mskRows) === 0) {
            $redirectParams['error'] = 'import_empty_sheet';
            redirect_to($importReturnPage, $redirectParams);
        }

        $importErrors = [];
        $mskInserted = 0;
        $mskUpdated = 0;

        $mskHeaderMap = import_build_header_map($mskRows[0] ?? []);
        $requiredMskHeaders = ['full_name', 'msk_month', 'session_numbers'];
        foreach ($requiredMskHeaders as $requiredHeader) {
            if (!isset($mskHeaderMap[$requiredHeader])) {
                $importErrors[] = 'Sheet Kelas MSK: kolom wajib "' . $requiredHeader . '" tidak ditemukan.';
            }
        }

        if (count($importErrors) === 0) {
            $mskIndexById = [];
            $mskIndexByIdentity = [];
            foreach ($mskClasses as $idx => $participant) {
                if (!is_array($participant)) {
                    continue;
                }
                $participantId = trim((string) ($participant['id'] ?? ''));
                if ($participantId !== '') {
                    $mskIndexById[$participantId] = $idx;
                }
                $identityKey = discipleship_unified_identity_key(
                    (string) ($participant['full_name'] ?? ''),
                    (string) ($participant['whatsapp'] ?? '')
                );
                if ($identityKey === '') {
                    continue;
                }
                if (!isset($mskIndexByIdentity[$identityKey])) {
                    $mskIndexByIdentity[$identityKey] = [];
                }
                $mskIndexByIdentity[$identityKey][] = $idx;
            }

            $seenMskRowKeys = [];
            foreach ($mskRows as $rowIndex => $row) {
                if ($rowIndex === 0) {
                    continue;
                }
                if (!is_array($row) || import_is_blank_row($row)) {
                    continue;
                }
                $excelRowNumber = $rowIndex + 1;
                $participantIdInput = import_row_value($row, $mskHeaderMap, ['participant_id', 'id']);
                $fullName = trim(import_row_value($row, $mskHeaderMap, ['full_name', 'nama']));
                $whatsapp = trim(import_row_value($row, $mskHeaderMap, ['whatsapp', 'phone', 'nomor_wa', 'nomor_whatsapp']));
                $genderRaw = import_row_value($row, $mskHeaderMap, ['gender', 'jenis_kelamin']);
                $birthDateRaw = import_row_value($row, $mskHeaderMap, ['birth_date', 'tanggal_lahir']);
                $birthPlace = trim(import_row_value($row, $mskHeaderMap, ['birth_place', 'tempat_lahir']));
                $address = trim(import_row_value($row, $mskHeaderMap, ['address', 'alamat']));
                $emailRaw = import_row_value($row, $mskHeaderMap, ['email']);
                $mskMonthRaw = import_row_value($row, $mskHeaderMap, ['msk_month', 'bulan_msk']);
                $sessionRaw = import_row_value($row, $mskHeaderMap, ['session_numbers', 'sessions', 'sesi']);
                $notes = trim(import_row_value($row, $mskHeaderMap, ['notes', 'keterangan']));

                if ($fullName === '') {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': full_name wajib diisi.';
                    continue;
                }
                $mskMonth = import_normalize_month_strict($mskMonthRaw);
                if ($mskMonth === '') {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': msk_month wajib format YYYY-MM.';
                    continue;
                }
                $sessionTokens = import_split_csv_tokens($sessionRaw);
                $sessionNumbers = import_parse_msk_session_numbers($sessionRaw);
                $invalidSession = false;
                if (count($sessionTokens) === 0 || count($sessionNumbers) === 0) {
                    $invalidSession = true;
                } else {
                    foreach ($sessionTokens as $token) {
                        if (!preg_match('/^\d+$/', $token)) {
                            $invalidSession = true;
                            break;
                        }
                        $tokenInt = (int) $token;
                        if ($tokenInt < 1 || $tokenInt > 12) {
                            $invalidSession = true;
                            break;
                        }
                    }
                }
                if ($invalidSession) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': session_numbers harus angka 1-12 dipisah koma.';
                    continue;
                }
                $gender = import_normalize_gender_value($genderRaw);
                if ($genderRaw !== '' && $gender === '') {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': gender tidak valid.';
                    continue;
                }
                $birthDate = '';
                if ($birthDateRaw !== '') {
                    $birthDate = normalize_ymd_date($birthDateRaw);
                    if ($birthDate === '') {
                        $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': birth_date harus format YYYY-MM-DD.';
                        continue;
                    }
                }
                $email = strtolower(trim($emailRaw));
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': email tidak valid.';
                    continue;
                }

                $identityKey = discipleship_unified_identity_key($fullName, $whatsapp);
                $rowIdentityKey = $participantIdInput !== '' ? ('id:' . $participantIdInput) : ('identity:' . $identityKey);
                if ($rowIdentityKey !== '' && isset($seenMskRowKeys[$rowIdentityKey])) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': data peserta duplikat di file import.';
                    continue;
                }
                if ($rowIdentityKey !== '') {
                    $seenMskRowKeys[$rowIdentityKey] = true;
                }

                $existingIndex = null;
                if ($participantIdInput !== '' && isset($mskIndexById[$participantIdInput])) {
                    $existingIndex = (int) $mskIndexById[$participantIdInput];
                } elseif ($identityKey !== '' && isset($mskIndexByIdentity[$identityKey])) {
                    $candidateIndexes = array_values(array_unique(array_map('intval', $mskIndexByIdentity[$identityKey])));
                    if (count($candidateIndexes) > 1) {
                        $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': nama/whatsapp ambigu. Gunakan participant_id dari hasil export atau rapikan data peserta MSK yang duplikat terlebih dahulu.';
                        continue;
                    }
                    if (count($candidateIndexes) === 1) {
                        $existingIndex = $candidateIndexes[0];
                    }
                }

                $existing = $existingIndex !== null ? ($mskClasses[$existingIndex] ?? null) : null;
                $participantId = $participantIdInput !== '' ? $participantIdInput : trim((string) ($existing['id'] ?? ''));
                if ($participantId === '') {
                    $participantId = generate_id('msk');
                }
                if ($participantIdInput !== '' && $existingIndex === null && isset($mskIndexById[$participantId])) {
                    $importErrors[] = 'Kelas MSK baris ' . $excelRowNumber . ': participant_id sudah dipakai peserta lain.';
                    continue;
                }

                $participantData = [
                    'id' => $participantId,
                    'member_id' => trim((string) ($existing['member_id'] ?? '')),
                    'full_name' => $fullName,
                    'gender' => $gender,
                    'birth_date' => $birthDate,
                    'birth_day_month' => $birthDate !== '' ? date('d-m', strtotime($birthDate)) : normalize_member_birth_day_month_value((string) ($existing['birth_day_month'] ?? '')),
                    'birth_place' => $birthPlace,
                    'address' => $address,
                    'email' => $email,
                    'whatsapp' => $whatsapp,
                    'photos' => extract_msk_participant_photos(is_array($existing) ? $existing : []),
                    'msk_month' => $mskMonth,
                    'session_numbers' => $sessionNumbers,
                    'notes' => $notes,
                    'completed_at' => trim((string) ($existing['completed_at'] ?? '')),
                    'created_at' => (string) ($existing['created_at'] ?? now_iso()),
                    'updated_at' => now_iso(),
                ];
                if ($existingIndex === null) {
                    $mskClasses[] = $participantData;
                    $newIndex = count($mskClasses) - 1;
                    $mskIndexById[$participantId] = $newIndex;
                    if ($identityKey !== '') {
                        if (!isset($mskIndexByIdentity[$identityKey])) {
                            $mskIndexByIdentity[$identityKey] = [];
                        }
                        $mskIndexByIdentity[$identityKey][] = $newIndex;
                    }
                    $mskInserted++;
                } else {
                    $mskClasses[$existingIndex] = $participantData;
                    $mskUpdated++;
                }
            }
        }

        if (count($importErrors) === 0) {
            $membersChangedByImport = false;
            if ($mskInserted > 0 || $mskUpdated > 0) {
                foreach ($mskClasses as $idx => $participant) {
                    if (!is_array($participant)) {
                        continue;
                    }
                    $beforeMemberCount = count($members);
                    if (auto_register_msk_participant_as_member($mskClasses[$idx], $members)) {
                        $membersChangedByImport = true;
                    }
                    if (sync_member_data_from_msk($mskClasses[$idx], $members)) {
                        $membersChangedByImport = true;
                    }
                    if (count($members) !== $beforeMemberCount) {
                        $membersChangedByImport = true;
                    }
                }
                if ($membersChangedByImport) {
                    sync_member_family_links($members);
                }
                persist_people_registry_data($members, $mskClasses);
            }
        } else {
            $mskInserted = 0;
            $mskUpdated = 0;
        }

        $redirectParams['imported'] = 1;
        $redirectParams['import_msk_inserted'] = $mskInserted;
        $redirectParams['import_msk_updated'] = $mskUpdated;
        $redirectParams['import_error_count'] = count($importErrors);
        if (count($importErrors) > 0) {
            $redirectParams['import_error_preview'] = substr((string) $importErrors[0], 0, 220);
        }
        if ($importReturnPage === 'msk_classes' && $batchMonth === '' && ($mskInserted > 0 || $mskUpdated > 0)) {
            $latestImportedMonth = '';
            foreach ($mskClasses as $participant) {
                if (!is_array($participant)) {
                    continue;
                }
                $participantMonth = normalize_month_value((string) ($participant['msk_month'] ?? ''));
                if ($participantMonth !== '' && ($latestImportedMonth === '' || strcmp($participantMonth, $latestImportedMonth) > 0)) {
                    $latestImportedMonth = $participantMonth;
                }
            }
            if ($latestImportedMonth !== '') {
                $redirectParams['batch_month'] = $latestImportedMonth;
            }
        }
        redirect_to($importReturnPage, $redirectParams);
    }

    if ($action === 'export_pemuridan_excel') {
        $batchMonthInput = trim((string) ($_POST['batch_month'] ?? ''));
        $batchMonth = '';
        if ($batchMonthInput !== '') {
            if (strtolower($batchMonthInput) === 'all') {
                $batchMonth = 'all';
            } else {
                $batchMonth = import_normalize_month_strict($batchMonthInput);
            }
        }

        $redirectParams = [];
        if ($batchMonth !== '') {
            $redirectParams['batch_month'] = $batchMonth;
        }

        $participantsToExport = $mskClasses;
        usort($participantsToExport, function ($a, $b) {
            return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        });

        if ($batchMonth !== '' && $batchMonth !== 'all') {
            $participantsToExport = array_values(array_filter($participantsToExport, function ($participant) use ($batchMonth) {
                if (!is_array($participant)) {
                    return false;
                }
                return normalize_month_value((string) ($participant['msk_month'] ?? date('Y-m'))) === $batchMonth;
            }));
        }

        $exportError = '';
        $xlsxPath = create_msk_import_export_xlsx($participantsToExport, $exportError);
        if ($xlsxPath === null) {
            if ($exportError === 'zip_unavailable') {
                $redirectParams['error'] = 'export_zip_unavailable';
            } elseif ($exportError === 'template_missing') {
                $redirectParams['error'] = 'export_template_missing';
            } else {
                $redirectParams['error'] = 'export_failed';
            }
            redirect_to('msk_classes', $redirectParams);
        }

        $branchLabel = sanitize_file_name_component((string) current_user_branch(), 'cabang');
        $filterLabel = $batchMonth === 'all' ? 'semua-batch' : ($batchMonth !== '' ? $batchMonth : 'semua-data');
        $downloadName = 'kelas-msk-' . $branchLabel . '-' . $filterLabel . '.xlsx';
        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $downloadName) ?? 'kelas-msk.xlsx';
        if ($downloadName === '') {
            $downloadName = 'kelas-msk.xlsx';
        }
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'kelas-msk.xlsx';
        if ($asciiDownloadName === '') {
            $asciiDownloadName = 'kelas-msk.xlsx';
        }
        $contentLength = (int) @filesize($xlsxPath);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Disposition: attachment; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        if ($contentLength > 0) {
            header('Content-Length: ' . (string) $contentLength);
        }
        readfile($xlsxPath);
        @unlink($xlsxPath);
        legacy_exit();
    }

    if ($action === 'export_pohon_pemuridan_dot') {
        $targetBranch = normalize_public_branch_code(current_user_branch());
        $redirectParams = [];

        if (is_effective_central_discipleship_readonly()) {
            $selectedExportBranch = trim((string) ($_POST['export_cabang'] ?? ''));
            if ($selectedExportBranch === '') {
                $selectedExportBranch = central_recap_selected_branch();
            }
            $selectedExportBranch = normalize_central_recap_branch($selectedExportBranch);
            if ($selectedExportBranch === 'all') {
                redirect_to('people_tree', ['rekap_cabang' => 'all', 'error' => 'dot_export_branch_required']);
            }
            $targetBranch = normalize_public_branch_code($selectedExportBranch);
            $redirectParams['rekap_cabang'] = $targetBranch;
        }

        $dotModel = dgv2_normalize_model(dgv2_read_model($targetBranch));
        $dotContent = build_pohon_pemuridan_dot_content($targetBranch, $dotModel);
        if ($dotContent === '') {
            $redirectParams['error'] = 'dot_export_failed';
            redirect_to('people_tree', $redirectParams);
        }

        $branchLabel = sanitize_file_name_component($targetBranch, 'cabang');
        $downloadName = 'pohon_pemuridan_' . $branchLabel . '.dot';
        $downloadName = preg_replace('/[\x00-\x1F\x7F"\\\\]+/', '_', $downloadName) ?? 'pohon_pemuridan.dot';
        if ($downloadName === '') {
            $downloadName = 'pohon_pemuridan.dot';
        }
        $asciiDownloadName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?? 'pohon_pemuridan.dot';
        if ($asciiDownloadName === '') {
            $asciiDownloadName = 'pohon_pemuridan.dot';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/vnd.graphviz; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Disposition: attachment; filename="' . $asciiDownloadName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
        header('Content-Length: ' . (string) strlen($dotContent));
        echo $dotContent;
        legacy_exit();
    }

    if ($discipleshipV2Enabled && in_array($action, ['save_person', 'delete_person', 'save_group', 'delete_group', 'leave_person_group', 'complete_group', 'reactivate_group'], true)) {
    $returnPage = trim((string) ($_POST['return_page'] ?? 'people_tree'));
    if ($returnPage === 'people_tree_v2') {
        $returnPage = 'people_tree';
    }
    if (!in_array($returnPage, ['people_tree', 'discipleship_dashboard', 'groups_list', 'people_list'], true)) {
        $returnPage = 'people_tree';
    }

    $result = ['ok' => false, 'error' => 'invalid_action'];

    $modifier = function(array &$model) use ($action, $members, $mskClasses, &$result) {
        $projectionPeople = dgv2_people_projection($model, $members, $mskClasses);
        $projectionPeopleById = index_by_id($projectionPeople);

        if ($action === 'save_person') {
            $result = dgv2_save_person($model, $_POST, $members, $mskClasses);
        } elseif ($action === 'delete_person') {
            $result = dgv2_archive_person($model, trim((string) ($_POST['id'] ?? '')));
        } elseif ($action === 'save_group') {
            $result = dgv2_save_group($model, $_POST, $projectionPeopleById);
        } elseif ($action === 'delete_group') {
            $result = dgv2_archive_group($model, trim((string) ($_POST['id'] ?? '')));
        } elseif ($action === 'leave_person_group') {
            $result = dgv2_leave_group(
                $model,
                trim((string) ($_POST['id'] ?? '')),
                trim((string) ($_POST['group_id'] ?? ''))
            );
        } elseif ($action === 'complete_group') {
            $result = dgv2_complete_group($model, trim((string) ($_POST['id'] ?? '')));
        } elseif ($action === 'reactivate_group') {
            $result = dgv2_reactivate_group($model, trim((string) ($_POST['id'] ?? '')));
        }
    };

    dgv2_load_and_save_model($discipleshipV2Branch, $modifier);

    if (!empty($result['ok'])) {
        if ($action === 'delete_person') {
            redirect_to($returnPage, ['person_archived' => 1]);
        }
        if ($action === 'leave_person_group') {
            redirect_to($returnPage, ['left_group' => 1]);
        }
        if ($action === 'complete_group') {
            redirect_to($returnPage, ['group_completed' => 1]);
        }
        if ($action === 'reactivate_group') {
            redirect_to($returnPage, ['group_reactivated' => 1]);
        }
        redirect_to($returnPage, ['saved' => 1]);
    }

    $errorCode = trim((string) ($result['error'] ?? 'save_failed'));
    redirect_to($returnPage, ['error' => $errorCode]);
}

    if ($action === 'save_person') {
        $returnPage = trim((string) ($_POST['return_page'] ?? 'people_tree'));
        if ($returnPage === 'people_tree_v2') {
            $returnPage = 'people_tree';
        }
        if ($returnPage !== 'people_tree') {
            $returnPage = 'people_tree';
        }
        $id = $_POST['id'] ?? '';
        $memberId = trim((string) ($_POST['member_id'] ?? ''));
        $leaderProvided = array_key_exists('leader_id', $_POST);
        $leader1 = $leaderProvided ? (string) ($_POST['leader_id'] ?? '') : '';
        $groupId = trim((string) ($_POST['group_id'] ?? ''));
        $leaderIds = array_values(array_unique(array_filter([$leader1], function ($id) {
            return $id !== '';
        })));
        $peopleById = index_by_id($people);
        $membersById = discipleship_person_sources_by_id($members, $mskClasses);
        $isRootEdit = ($id !== '' && $id === $rootLeaderId);
        $allowEmptyParent = false;
        if ($id !== '' && isset($peopleById[$id])) {
            $currentParents = get_parent_ids($peopleById[$id]);
            if (count($currentParents) === 0) {
                $allowEmptyParent = true;
            }
        }
        if ($id !== '' && !isset($peopleById[$id])) {
            redirect_to($returnPage, ['error' => 'invalid_person']);
        }
        if ($id !== '' && !$leaderProvided && isset($peopleById[$id])) {
            $leaderIds = get_parent_ids($peopleById[$id]);
        }
        $targetGroupId = '';

        if ($id === '' && $groupId !== '') {
            if ($groupId === 'virtual_root_group') {
                $leader1 = '';
                $leaderIds = [];
                $targetGroupId = '';
            } else {
                $groupLeaderId = '';
                foreach ($groups as $grp) {
                    if (($grp['id'] ?? '') === $groupId) {
                        $groupLeaderId = (string) ($grp['leader_id'] ?? '');
                        break;
                    }
                }
                if ($groupLeaderId === '' || !isset($peopleById[$groupLeaderId])) {
                    redirect_to($returnPage, ['error' => 'invalid_group']);
                }
                $leader1 = $groupLeaderId;
                $leaderIds = array_values(array_unique(array_filter([$leader1], function ($id) {
                    return $id !== '';
                })));
                $targetGroupId = $groupId;
            }
        } elseif ($id === '') {
            redirect_to($returnPage, ['error' => 'missing_group']);
        }

        $memberName = '';
        $memberPhone = '';
        if ($memberId === '' && $id !== '' && isset($peopleById[$id])) {
            $memberId = trim((string) ($peopleById[$id]['member_id'] ?? ''));
        }
        if ($memberId === '') {
            redirect_to($returnPage, ['error' => 'missing_member']);
        }
        if (!isset($membersById[$memberId])) {
            redirect_to($returnPage, ['error' => 'invalid_member']);
        }
        foreach ($people as $existingPerson) {
            $existingId = (string) ($existingPerson['id'] ?? '');
            $existingMemberId = trim((string) ($existingPerson['member_id'] ?? ''));
            if ($existingMemberId !== '' && $existingMemberId === $memberId && $existingId !== $id) {
                redirect_to($returnPage, ['error' => 'member_exists']);
            }
        }

        $memberName = trim((string) ($membersById[$memberId]['full_name'] ?? ''));
        if ($memberName === '') {
            redirect_to($returnPage, ['error' => 'invalid_member']);
        }
        $memberPhone = trim((string) ($membersById[$memberId]['whatsapp'] ?? ''));

        if (strcasecmp($memberName, $rootLeaderName) === 0 && $id !== $rootLeaderId) {
            redirect_to($returnPage, ['error' => 'reserved_name']);
        }
        if ($id === '' && count($leaderIds) === 0 && $groupId !== 'virtual_root_group') {
            redirect_to($returnPage, ['error' => 'missing_parent']);
        }
        if ($id !== '' && !$isRootEdit && count($leaderIds) === 0 && !$allowEmptyParent) {
            redirect_to($returnPage, ['error' => 'missing_parent']);
        }
        if ($isRootEdit) {
            $leaderIds = [];
            $memberName = $rootLeaderName;
        }
        foreach ($leaderIds as $leaderId) {
            if (!isset($peopleById[$leaderId]) || $leaderId === $id) {
                redirect_to($returnPage, ['error' => 'invalid_parent']);
            }
        }
        if ($memberName !== '') {
            $existingPerson = $id !== '' && isset($peopleById[$id]) ? $peopleById[$id] : null;
            $person = [
                'id' => $id !== '' ? $id : generate_id('person'),
                'member_id' => $memberId,
                'name' => $memberName,
                'phone' => $memberPhone,
                'role' => $id === '' ? 'Anggota' : ($peopleById[$id]['role'] ?? 'Anggota'),
                'parent_ids' => $leaderIds,
                'notes' => trim($_POST['notes'] ?? ''),
                'kampus' => $existingPerson !== null ? (string) ($existingPerson['kampus'] ?? '') : '',
                'jurusan' => $existingPerson !== null ? (string) ($existingPerson['jurusan'] ?? '') : '',
                'pekerjaan' => $existingPerson !== null ? (string) ($existingPerson['pekerjaan'] ?? '') : '',
                'updated_at' => now_iso(),
            ];
            if ($existingPerson !== null && array_key_exists('angkatan', $existingPerson)) {
                // Keep existing legacy value without exposing/editing it in UI.
                $person['angkatan'] = (string) $existingPerson['angkatan'];
            }
            if ($id === '') {
                $person['created_at'] = now_iso();
                $people[] = $person;
            } else {
                foreach ($people as &$p) {
                    if ($p['id'] === $id) {
                        $person['created_at'] = $p['created_at'] ?? now_iso();
                        $p = $person;
                        break;
                    }
                }
                unset($p);
            }
            if (update_roles_based_on_children($people, $rootLeaderId)) {
                $peopleById = index_by_id($people);
            }
            $peopleById = index_by_id($people);
            persist_people_data($people);
            if ($targetGroupId !== '') {
                foreach ($groups as &$grp) {
                    if (($grp['id'] ?? '') === $targetGroupId) {
                        $memberIds = $grp['member_ids'] ?? [];
                        if (!is_array($memberIds)) {
                            $memberIds = [];
                        }
                        if (!in_array($person['id'], $memberIds, true)) {
                            $memberIds[] = $person['id'];
                        }
                        $grp['member_ids'] = array_values(array_unique($memberIds));
                        $grp['member_names'] = build_group_member_names($grp['member_ids'], $peopleById, $grp['member_names'] ?? []);
                        $grp['updated_at'] = now_iso();
                        break;
                    }
                }
                unset($grp);
                persist_groups_data($groups);
            }
            $groupsChanged = normalize_groups($groups, $peopleById);
            if ($groupsChanged) {
                persist_groups_data($groups);
            }
        }
        redirect_to($returnPage);
    }

    if ($action === 'delete_person') {
        $returnPage = trim((string) ($_POST['return_page'] ?? 'people_tree'));
        if ($returnPage === 'people_tree_v2') {
            $returnPage = 'people_tree';
        }
        if ($returnPage !== 'people_tree') {
            $returnPage = 'people_tree';
        }
        $id = $_POST['id'] ?? '';
        $removedMemberRef = '';
        foreach ($people as $personRow) {
            if ((string) ($personRow['id'] ?? '') !== $id) {
                continue;
            }
            $removedMemberRef = trim((string) ($personRow['member_id'] ?? ''));
            break;
        }
        if ($id === $rootLeaderId) {
            redirect_to($returnPage, ['error' => 'root_locked']);
        }
        $inUse = false;
        foreach ($people as $p) {
            $parentIds = get_parent_ids($p);
            if (in_array($id, $parentIds, true)) {
                $inUse = true;
                break;
            }
        }
        if ($inUse) {
            redirect_to($returnPage, ['error' => 'in_use']);
        }
        $people = array_values(array_filter($people, function ($p) use ($id) {
            return $p['id'] !== $id;
        }));
        update_roles_based_on_children($people, $rootLeaderId);
        persist_people_data($people);
        $peopleById = index_by_id($people);
        $groupsChanged = false;
        foreach ($groups as &$grp) {
            $memberIds = $grp['member_ids'] ?? [];
            if (!is_array($memberIds)) {
                $memberIds = [];
            }
            $nextMemberIds = array_values(array_filter(array_map('strval', $memberIds), function ($memberId) use ($id) {
                return $memberId !== '' && $memberId !== $id;
            }));
            if ($nextMemberIds !== array_values(array_map('strval', $memberIds))) {
                $grp['member_ids'] = $nextMemberIds;
                $grp['member_names'] = build_group_member_names($nextMemberIds, $peopleById, $grp['member_names'] ?? []);
                $grp['updated_at'] = now_iso();
                $groupsChanged = true;
            }
        }
        unset($grp);
        if (normalize_groups($groups, $peopleById)) {
            $groupsChanged = true;
        }
        if ($groupsChanged) {
            persist_groups_data($groups);
        }
        redirect_to($returnPage);
    }

    if ($action === 'save_group') {
        $returnPage = trim((string) ($_POST['return_page'] ?? 'people_tree'));
        if ($returnPage === 'people_tree_v2') {
            $returnPage = 'people_tree';
        }
        if ($returnPage !== 'people_tree') {
            $returnPage = 'people_tree';
        }
        $id = $_POST['id'] ?? '';
        $leaderId = $_POST['leader_id'] ?? '';
        $assistantId = trim((string) ($_POST['assistant_id'] ?? ''));
        $progress = trim((string) ($_POST['progress'] ?? ''));
        $groupNotes = trim((string) ($_POST['notes'] ?? ''));
        $hasMemberIdsInput = array_key_exists('member_ids', $_POST);
        $groupMembers = [];
        if ($id === '') {
            $singleMember = trim((string) ($_POST['member_id'] ?? ''));
            if ($singleMember !== '') {
                $groupMembers = [$singleMember];
            }
        } else {
            if ($hasMemberIdsInput) {
                $groupMembers = $_POST['member_ids'] ?? [];
                if (!is_array($groupMembers)) {
                    $groupMembers = [];
                }
            } else {
                foreach ($groups as $groupRow) {
                    if ((string) ($groupRow['id'] ?? '') !== (string) $id) {
                        continue;
                    }
                    $existingMemberIds = $groupRow['member_ids'] ?? [];
                    if (is_array($existingMemberIds)) {
                        $groupMembers = $existingMemberIds;
                    }
                    break;
                }
            }
        }
        $groupMembers = array_values(array_unique(array_filter($groupMembers, function ($m) {
            return $m !== '';
        })));

        $peopleById = index_by_id($people);
        if ($leaderId === '' || !isset($peopleById[$leaderId])) {
            redirect_to($returnPage, ['error' => 'invalid_group']);
        }
        if (!in_array($progress, $progressOptions, true)) {
            $progress = 'DG 1';
        }
        if ($assistantId === $leaderId) {
            $assistantId = '';
        }
        if ($assistantId !== '' && !isset($peopleById[$assistantId])) {
            $assistantId = '';
        }
        $filtered = [];
        foreach ($groupMembers as $mid) {
            if (!isset($peopleById[$mid])) {
                continue;
            }
            $leaderIds = get_parent_ids($peopleById[$mid]);
            $primaryLeader = $leaderIds[0] ?? '';
            if ($primaryLeader !== $leaderId) {
                continue;
            }
            $filtered[] = $mid;
        }
        $filtered = array_values(array_unique($filtered));
        $currentId = $id !== '' ? $id : '';
        $leaderNameSnapshot = trim((string) ($peopleById[$leaderId]['name'] ?? ''));
        if ($leaderNameSnapshot === '') {
            $leaderNameSnapshot = 'Leader #' . $leaderId;
        }
        $memberNamesSnapshot = build_group_member_names($filtered, $peopleById);

        $memberGroupMap = [];
        foreach ($groups as $grp) {
            $gid = $grp['id'] ?? '';
            if ($gid === $currentId) {
                continue;
            }
            $memberIds = $grp['member_ids'] ?? [];
            if (!is_array($memberIds)) {
                $memberIds = [];
            }
            foreach ($memberIds as $mid) {
                if ($mid === '') {
                    continue;
                }
                $memberGroupMap[$mid] = 'Kelompok';
            }
        }
        $conflicts = [];
        foreach ($filtered as $mid) {
            if (isset($memberGroupMap[$mid])) {
                $conflicts[] = person_label($peopleById, (string) $mid, $mid) . ' (' . $memberGroupMap[$mid] . ')';
            }
        }
        if (count($conflicts) > 0) {
            $conflictText = implode(', ', $conflicts);
            redirect_to($returnPage, ['error' => 'member_in_group', 'conflict' => $conflictText]);
        }

        if ($id === '') {
            $currentId = generate_id('grp');
            $groups[] = [
                'id' => $currentId,
                'leader_id' => $leaderId,
                'assistant_id' => $assistantId,
                'member_ids' => $filtered,
                'leader_name' => $leaderNameSnapshot,
                'member_names' => $memberNamesSnapshot,
                'progress' => $progress,
                'notes' => $groupNotes,
                'created_at' => now_iso(),
                'updated_at' => now_iso(),
            ];
        } else {
            $currentId = $id;
            foreach ($groups as &$grp) {
                if (($grp['id'] ?? '') === $id) {
                    $grp['leader_id'] = $leaderId;
                    $grp['assistant_id'] = $assistantId;
                    unset($grp['name']);
                    $grp['member_ids'] = $filtered;
                    $grp['leader_name'] = $leaderNameSnapshot;
                    $grp['member_names'] = $memberNamesSnapshot;
                    $grp['progress'] = $progress;
                    $grp['notes'] = $groupNotes;
                    $grp['updated_at'] = now_iso();
                    break;
                }
            }
            unset($grp);
        }
        persist_groups_data($groups);
        redirect_to($returnPage);
    }

    if ($action === 'delete_group') {
        $returnPage = trim((string) ($_POST['return_page'] ?? 'people_tree'));
        if ($returnPage === 'people_tree_v2') {
            $returnPage = 'people_tree';
        }
        if ($returnPage !== 'people_tree') {
            $returnPage = 'people_tree';
        }
        $id = $_POST['id'] ?? '';
        $groups = array_values(array_filter($groups, function ($g) use ($id) {
            return ($g['id'] ?? '') !== $id;
        }));

        // Remove all DG meeting reports that belong to deleted group.
        $filteredReports = [];
        $reportPhotoPathsToDelete = [];
        foreach ($dgMeetingReports as $report) {
            if (!is_array($report)) {
                continue;
            }
            $reportGroupId = trim((string) ($report['group_id'] ?? ''));
            if ($reportGroupId !== '' && (string) $reportGroupId === (string) $id) {
                $rawMeetingPhotos = $report['meeting_photos'] ?? [];
                if (is_array($rawMeetingPhotos)) {
                    foreach ($rawMeetingPhotos as $photoItem) {
                        $photoPath = '';
                        if (is_array($photoItem)) {
                            $photoPath = (string) ($photoItem['path'] ?? '');
                        } elseif (is_string($photoItem)) {
                            $photoPath = $photoItem;
                        }
                        $safePhotoPath = sanitize_relative_upload_path($photoPath);
                        if ($safePhotoPath !== '') {
                            $reportPhotoPathsToDelete[$safePhotoPath] = true;
                        }
                    }
                }
                continue;
            }
            $filteredReports[] = $report;
        }
        $dgMeetingReports = array_values($filteredReports);

        $peopleById = index_by_id($people);
        normalize_groups($groups, $peopleById);
        persist_groups_data($groups);
        persist_dg_meeting_reports_data($dgMeetingReports);
        foreach (array_keys($reportPhotoPathsToDelete) as $photoPathToDelete) {
            delete_relative_upload_file($photoPathToDelete);
        }
        redirect_to($returnPage);
    }

    if ($action === 'save_worship_penatalayan') {
        $monthInput = normalize_month_value((string) ($_POST['month'] ?? date('Y-m')));
        $title = trim((string) ($_POST['title'] ?? ''));
        $updateNote = trim((string) ($_POST['update_note'] ?? ''));
        $rowLabelsInput = $_POST['row_labels'] ?? [];
        $assignmentsInput = $_POST['assignments'] ?? [];
        if (!is_array($rowLabelsInput)) {
            $rowLabelsInput = [];
        }
        if (!is_array($assignmentsInput)) {
            $assignmentsInput = [];
        }

        $submittedRows = [];
        foreach ($rowLabelsInput as $rowIndex => $roleLabelRaw) {
            $roleLabel = trim((string) $roleLabelRaw);
            if ($roleLabel === '') {
                continue;
            }
            $roleKey = strtolower($roleLabel);
            $rowAssignments = $assignmentsInput[$rowIndex] ?? [];
            if (!is_array($rowAssignments)) {
                $rowAssignments = [];
            }
            $normalizedRowAssignments = [];
            foreach ($rowAssignments as $weekIndex => $weekValue) {
                if (is_array($weekValue)) {
                    $weekParts = [];
                    foreach ($weekValue as $weekPart) {
                        $weekPartValue = trim((string) $weekPart);
                        if ($weekPartValue !== '') {
                            $weekParts[] = $weekPartValue;
                        }
                    }
                    $normalizedRowAssignments[(int) $weekIndex] = implode("\n", $weekParts);
                } else {
                    $scalarValue = trim((string) $weekValue);
                    if ($roleKey === 'jadwal latihan') {
                        $scalarValue = worship_penatalayan_training_date($scalarValue, $monthInput);
                    }
                    $normalizedRowAssignments[(int) $weekIndex] = $scalarValue;
                }
            }
            ksort($normalizedRowAssignments);
            $submittedRows[] = [
                'role' => $roleLabel,
                'assignments' => array_values($normalizedRowAssignments),
            ];
        }

        $weekCount = count(worship_penatalayan_week_dates($monthInput));
        $rows = normalize_worship_penatalayan_rows($submittedRows, $weekCount);
        $existingIndex = null;
        foreach ($worshipPenatalayanSchedules as $idx => $schedule) {
            if ((string) ($schedule['month'] ?? '') === $monthInput) {
                $existingIndex = $idx;
                break;
            }
        }

        $now = now_iso();
        $payload = [
            'month' => $monthInput,
            'title' => $title !== '' ? $title : default_worship_penatalayan_title($monthInput),
            'update_note' => $updateNote,
            'rows' => $rows,
            'updated_at' => $now,
        ];
        if ($existingIndex === null) {
            $payload['created_at'] = $now;
            $worshipPenatalayanSchedules[] = $payload;
        } else {
            $payload['created_at'] = (string) ($worshipPenatalayanSchedules[$existingIndex]['created_at'] ?? $now);
            $worshipPenatalayanSchedules[$existingIndex] = array_merge($worshipPenatalayanSchedules[$existingIndex], $payload);
        }

        usort($worshipPenatalayanSchedules, function ($a, $b) {
            return strcmp((string) ($b['month'] ?? ''), (string) ($a['month'] ?? ''));
        });
        write_json(data_path('worship_penatalayan'), $worshipPenatalayanSchedules);
        redirect_to('worship_penatalayan', ['month' => $monthInput, 'saved' => 1]);
    }

    if ($action === 'delete_worship_penatalayan') {
        $monthInput = normalize_month_value((string) ($_POST['month'] ?? date('Y-m')));
        $beforeCount = count($worshipPenatalayanSchedules);
        $worshipPenatalayanSchedules = array_values(array_filter($worshipPenatalayanSchedules, function ($schedule) use ($monthInput) {
            return (string) ($schedule['month'] ?? '') !== $monthInput;
        }));
        if (count($worshipPenatalayanSchedules) === $beforeCount) {
            redirect_to('worship_penatalayan', ['error' => 'invalid_schedule', 'month' => $monthInput]);
        }
        write_json(data_path('worship_penatalayan'), $worshipPenatalayanSchedules);
        redirect_to('worship_penatalayan', ['deleted' => 1]);
    }

    if ($action === 'save_member') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $memberId = $id !== '' ? $id : generate_id('member');
        $returnPage = trim((string) ($_POST['return_page'] ?? 'members'));
        if (!in_array($returnPage, ['members', 'member_completeness'], true)) {
            $returnPage = 'members';
        }
        $returnMissing = '';
        if ($returnPage === 'member_completeness') {
            $returnMissing = trim((string) ($_POST['return_missing'] ?? 'all'));
            $allowedReturnMissing = member_completeness_filter_options();
            if (!isset($allowedReturnMissing[$returnMissing])) {
                $returnMissing = 'all';
            }
        }
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $gender = normalize_member_gender_value((string) ($_POST['gender'] ?? ''));
        $birthDateInput = trim((string) ($_POST['birth_date'] ?? ''));
        $birthDate = $birthDateInput !== '' ? normalize_ymd_date($birthDateInput) : '';
        $birthDayMonthInput = trim((string) ($_POST['birth_day_month'] ?? ''));
        $birthDayMonth = $birthDayMonthInput !== '' ? normalize_member_birth_day_month_value($birthDayMonthInput) : '';
        $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));
        $birthPlace = trim((string) ($_POST['birth_place'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $emailInput = trim((string) ($_POST['email'] ?? ''));
        $email = strtolower($emailInput);
        $socialMedia = normalize_social_link_value((string) ($_POST['social_media'] ?? ''));
        $hasMembershipStatusInput = array_key_exists('membership_status', $_POST);
        $membershipStatus = $hasMembershipStatusInput
            ? normalize_member_status_value((string) ($_POST['membership_status'] ?? 'active'))
            : 'active';
        $leftReason = $hasMembershipStatusInput ? trim((string) ($_POST['left_reason'] ?? '')) : '';
        $familyIdsInput = $_POST['family_ids'] ?? [];
        if (!is_array($familyIdsInput)) {
            $familyIdsInput = [];
        }
        $removePhotoPathsInput = $_POST['remove_photo_paths'] ?? [];
        if (!is_array($removePhotoPathsInput)) {
            $removePhotoPathsInput = [];
        }
        $removePhotoPaths = [];
        foreach ($removePhotoPathsInput as $path) {
            $safePath = sanitize_relative_upload_path((string) $path);
            if ($safePath !== '') {
                $removePhotoPaths[$safePath] = true;
            }
        }

        $params = [];
        if ($id !== '') {
            $params['edit'] = $id;
        }
        if ($returnPage === 'member_completeness') {
            $params['missing'] = $returnMissing;
        }
        if ($fullName === '' || $gender === '') {
            $params['error'] = 'missing_member_fields';
            redirect_to($returnPage, $params);
        }
        if ($birthDateInput !== '' && $birthDate === '') {
            $params['error'] = 'invalid_member_birth_date';
            redirect_to($returnPage, $params);
        }
        if ($birthDayMonthInput !== '' && $birthDayMonth === '') {
            $params['error'] = 'invalid_member_birth_date';
            redirect_to($returnPage, $params);
        }
        if ($birthDate !== '') {
            $timestamp = strtotime($birthDate);
            if ($timestamp !== false) {
                $birthDayMonth = date('d-m', $timestamp);
            }
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $params['error'] = 'invalid_member_email';
            redirect_to($returnPage, $params);
        }
        if ($socialMedia !== '' && filter_var($socialMedia, FILTER_VALIDATE_URL) === false) {
            $params['error'] = 'invalid_member_social_link';
            redirect_to($returnPage, $params);
        }
        if ($hasMembershipStatusInput && $membershipStatus === 'left' && $leftReason === '') {
            $params['error'] = 'missing_member_left_reason';
            redirect_to($returnPage, $params);
        }

        $existingIndex = null;
        foreach ($members as $idx => $member) {
            if ((string) ($member['id'] ?? '') === $id) {
                $existingIndex = $idx;
                break;
            }
        }
        if ($id !== '' && $existingIndex === null) {
            $params['error'] = 'invalid_member';
            redirect_to($returnPage, $params);
        }

        $membersById = index_by_id($members);
        $familyIds = [];
        foreach ($familyIdsInput as $familyId) {
            $familyId = trim((string) $familyId);
            if ($familyId === '' || $familyId === $memberId || !isset($membersById[$familyId])) {
                continue;
            }
            $familyIds[] = $familyId;
        }
        $familyIds = array_values(array_unique($familyIds));

        $existing = $existingIndex !== null ? $members[$existingIndex] : null;
        if (!$hasMembershipStatusInput && $existing !== null) {
            $membershipStatus = normalize_member_status_value((string) ($existing['membership_status'] ?? 'active'));
            $leftReason = trim((string) ($existing['left_reason'] ?? ''));
        }
        $existingPhotos = $existing !== null ? extract_member_photos($existing) : [];
        $existingPhotosByPath = [];
        foreach ($existingPhotos as $photo) {
            $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($photoPath === '') {
                continue;
            }
            $photoName = trim((string) ($photo['name'] ?? ''));
            if ($photoName === '') {
                $photoName = 'Foto';
            }
            $existingPhotosByPath[$photoPath] = [
                'path' => $photoPath,
                'name' => $photoName,
            ];
        }

        $filesToDelete = [];
        foreach (array_keys($removePhotoPaths) as $pathToRemove) {
            if (isset($existingPhotosByPath[$pathToRemove])) {
                $filesToDelete[$pathToRemove] = true;
                unset($existingPhotosByPath[$pathToRemove]);
            }
        }

        $uploadError = '';
        $uploadedPhotos = [];
        if (isset($_FILES['member_photos']) && is_array($_FILES['member_photos'])) {
            $uploadedPhotos = upload_member_photos($_FILES['member_photos'], $uploadError);
        }
        if ($uploadError !== '') {
            $params['error'] = $uploadError;
            redirect_to($returnPage, $params);
        }

        foreach ($uploadedPhotos as $uploadedPhoto) {
            $uploadedPath = sanitize_relative_upload_path((string) ($uploadedPhoto['path'] ?? ''));
            if ($uploadedPath === '') {
                continue;
            }
            $uploadedName = trim((string) ($uploadedPhoto['name'] ?? ''));
            if ($uploadedName === '') {
                $uploadedName = 'Foto';
            }
            $existingPhotosByPath[$uploadedPath] = [
                'path' => $uploadedPath,
                'name' => $uploadedName,
            ];
        }
        $finalPhotos = array_values($existingPhotosByPath);

        $now = now_iso();
        $leftAt = '';
        if ($membershipStatus === 'left') {
            $leftAt = trim((string) ($existing['left_at'] ?? ''));
            if ($leftAt === '') {
                $leftAt = $now;
            }
        } else {
            $leftReason = '';
        }
        $memberData = [
            'id' => $memberId,
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
            'photos' => $finalPhotos,
            'family_ids' => $familyIds,
            'created_at' => $existing['created_at'] ?? $now,
            'updated_at' => $now,
        ];

        if ($existingIndex === null) {
            $members[] = $memberData;
        } else {
            $members[$existingIndex] = $memberData;
        }

        $selectedFamilyMap = [];
        foreach ($familyIds as $familyId) {
            $selectedFamilyMap[$familyId] = true;
        }
        foreach ($members as &$candidate) {
            $candidateId = (string) ($candidate['id'] ?? '');
            if ($candidateId === '' || $candidateId === $memberId) {
                continue;
            }
            $candidateFamilyIds = $candidate['family_ids'] ?? [];
            if (!is_array($candidateFamilyIds)) {
                $candidateFamilyIds = [];
            }
            if (isset($selectedFamilyMap[$candidateId])) {
                if (!in_array($memberId, $candidateFamilyIds, true)) {
                    $candidateFamilyIds[] = $memberId;
                }
            } else {
                $candidateFamilyIds = array_values(array_filter($candidateFamilyIds, function ($familyId) use ($memberId) {
                    return (string) $familyId !== $memberId;
                }));
            }
            $candidate['family_ids'] = array_values(array_unique($candidateFamilyIds));
        }
        unset($candidate);

        sync_member_family_links($members);
        $mskChangedByMember = sync_msk_data_from_member($memberData, $mskClasses);
        if ($mskChangedByMember) {
            // no-op, both views will be persisted together below
        }
        persist_people_registry_data($members, $mskClasses);
        foreach (array_keys($filesToDelete) as $pathToDelete) {
            delete_photo_file_if_unused($members, $mskClasses, $pathToDelete);
        }

        $successParams = ['saved' => 1];
        if ($returnPage === 'member_completeness') {
            $successParams['missing'] = $returnMissing;
        }
        redirect_to($returnPage, $successParams);
    }

    if ($action === 'mark_member_left') {
        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '') {
            redirect_to('members', ['error' => 'invalid_member']);
        }
        $exitReason = trim((string) ($_POST['exit_reason'] ?? ''));
        if ($exitReason === '') {
            redirect_to('members', ['error' => 'missing_member_left_reason', 'edit' => $id]);
        }

        $updated = false;
        foreach ($members as &$member) {
            if ((string) ($member['id'] ?? '') === $id) {
                $member['membership_status'] = 'left';
                $member['left_reason'] = $exitReason;
                $leftAt = trim((string) ($member['left_at'] ?? ''));
                if ($leftAt === '') {
                    $leftAt = now_iso();
                }
                $member['left_at'] = $leftAt;
                $member['updated_at'] = now_iso();
                $updated = true;
                break;
            }
        }
        unset($member);
        if (!$updated) {
            redirect_to('members', ['error' => 'invalid_member']);
        }

        sync_member_left_duplicates_by_identity($members);
        persist_people_registry_data($members, $mskClasses);
        redirect_to('members', ['lefted' => 1]);
    }

    if ($action === 'delete_member') {
        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '') {
            redirect_to('members', ['error' => 'invalid_member']);
        }

        $deletedMember = null;
        $filteredMembers = [];
        foreach ($members as $member) {
            if ((string) ($member['id'] ?? '') === $id) {
                $deletedMember = $member;
                continue;
            }
            $filteredMembers[] = $member;
        }
        if ($deletedMember === null) {
            redirect_to('members', ['error' => 'invalid_member']);
        }

        $members = array_values($filteredMembers);
        sync_member_family_links($members);
        persist_people_registry_data($members, $mskClasses);

        foreach (extract_member_photos($deletedMember) as $photo) {
            $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($photoPath !== '') {
                delete_photo_file_if_unused($members, $mskClasses, $photoPath);
            }
        }

        redirect_to('members', ['deleted' => 1]);
    }

    if ($action === 'mark_member_active') {
        $id = trim((string) ($_POST['id'] ?? ''));
        if ($id === '') {
            redirect_to('members', ['error' => 'invalid_member']);
        }

        $updated = false;
        foreach ($members as &$member) {
            if ((string) ($member['id'] ?? '') === $id) {
                $member['membership_status'] = 'active';
                $member['left_reason'] = '';
                $member['left_at'] = '';
                $member['updated_at'] = now_iso();
                $updated = true;
                break;
            }
        }
        unset($member);
        if (!$updated) {
            redirect_to('members', ['error' => 'invalid_member']);
        }

        persist_people_registry_data($members, $mskClasses);
        redirect_to('members', ['reactivated' => 1]);
    }

    if ($action === 'save_msk_participant') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $participantId = $id !== '' ? $id : generate_id('msk');
        $memberId = '';
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $gender = normalize_member_gender_value((string) ($_POST['gender'] ?? ''));
        $birthDateInput = trim((string) ($_POST['birth_date'] ?? ''));
        $birthDate = $birthDateInput !== '' ? normalize_ymd_date($birthDateInput) : '';
        $birthDayMonth = '';
        $birthPlace = trim((string) ($_POST['birth_place'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));
        $emailInput = trim((string) ($_POST['email'] ?? ''));
        $email = strtolower($emailInput);
        $whatsapp = trim((string) ($_POST['whatsapp'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $mskMonthInput = trim((string) ($_POST['msk_month'] ?? date('Y-m')));
        $mskMonth = normalize_month_value($mskMonthInput);
        $batchMonthInput = trim((string) ($_POST['batch_month'] ?? ''));
        $batchMonthIsAll = strtolower($batchMonthInput) === 'all';
        $batchMonth = (!$batchMonthIsAll && $batchMonthInput !== '') ? normalize_month_value($batchMonthInput) : '';
        $sessionNumbers = normalize_msk_session_numbers($_POST['session_numbers'] ?? []);
        $removePhotoPathsInput = $_POST['remove_photo_paths'] ?? [];
        if (!is_array($removePhotoPathsInput)) {
            $removePhotoPathsInput = [];
        }
        $removePhotoPaths = [];
        foreach ($removePhotoPathsInput as $path) {
            $safePath = sanitize_relative_upload_path((string) $path);
            if ($safePath !== '') {
                $removePhotoPaths[$safePath] = true;
            }
        }

        $params = [];
        if ($id !== '') {
            $params['edit'] = $id;
        }
        if ($batchMonthIsAll) {
            $params['batch_month'] = 'all';
        } elseif ($batchMonth !== '') {
            $params['batch_month'] = $batchMonth;
        }

        if ($birthDateInput !== '' && $birthDate === '') {
            $params['error'] = 'invalid_msk_birth_date';
            redirect_to('msk_classes', $params);
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $params['error'] = 'invalid_msk_email';
            redirect_to('msk_classes', $params);
        }
        if ($birthDate !== '') {
            $timestamp = strtotime($birthDate);
            if ($timestamp !== false) {
                $birthDayMonth = date('d-m', $timestamp);
            }
        }

        $existingIndex = null;
        foreach ($mskClasses as $idx => $participant) {
            if ((string) ($participant['id'] ?? '') === $id) {
                $existingIndex = $idx;
                break;
            }
        }
        if ($id !== '' && $existingIndex === null) {
            $params['error'] = 'invalid_msk_participant';
            redirect_to('msk_classes', $params);
        }
        $existing = $existingIndex !== null ? $mskClasses[$existingIndex] : null;
        $existingLinkedMemberId = trim((string) ($existing['member_id'] ?? ''));
        $existingPhotos = $existing !== null ? extract_msk_participant_photos($existing) : [];
        $existingPhotosByPath = [];
        foreach ($existingPhotos as $photo) {
            $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($photoPath === '') {
                continue;
            }
            $photoName = trim((string) ($photo['name'] ?? ''));
            if ($photoName === '') {
                $photoName = 'Foto';
            }
            $existingPhotosByPath[$photoPath] = [
                'path' => $photoPath,
                'name' => $photoName,
            ];
        }
        $filesToDelete = [];
        foreach (array_keys($removePhotoPaths) as $pathToRemove) {
            if (isset($existingPhotosByPath[$pathToRemove])) {
                $filesToDelete[$pathToRemove] = true;
                unset($existingPhotosByPath[$pathToRemove]);
            }
        }
        $finalPhotosByPath = $existingPhotosByPath;

        $membersById = index_active_members_by_id($members);
        if ($fullName === '') {
            $params['error'] = 'missing_msk_name';
            redirect_to('msk_classes', $params);
        }
        if ($existing !== null) {
            $existingMemberId = trim((string) ($existing['member_id'] ?? ''));
            if ($existingMemberId !== '' && isset($membersById[$existingMemberId])) {
                $memberId = $existingMemberId;
            }
        }

        $uploadError = '';
        $uploadedPhotos = [];
        if (isset($_FILES['participant_photos']) && is_array($_FILES['participant_photos'])) {
            $uploadedPhotos = upload_member_photos($_FILES['participant_photos'], $uploadError);
        }
        if ($uploadError !== '') {
            if ($uploadError === 'invalid_member_photo_type') {
                $params['error'] = 'invalid_msk_photo_type';
            } elseif ($uploadError === 'member_photo_too_large') {
                $params['error'] = 'msk_photo_too_large';
            } else {
                $params['error'] = 'msk_photo_upload_failed';
            }
            redirect_to('msk_classes', $params);
        }
        foreach ($uploadedPhotos as $uploadedPhoto) {
            $uploadedPath = sanitize_relative_upload_path((string) ($uploadedPhoto['path'] ?? ''));
            if ($uploadedPath === '') {
                continue;
            }
            $uploadedName = trim((string) ($uploadedPhoto['name'] ?? ''));
            if ($uploadedName === '') {
                $uploadedName = 'Foto';
            }
            $finalPhotosByPath[$uploadedPath] = [
                'path' => $uploadedPath,
                'name' => $uploadedName,
            ];
        }
        $finalPhotos = array_values($finalPhotosByPath);

        $now = now_iso();
        $participantData = [
            'id' => $participantId,
            'member_id' => $memberId,
            'full_name' => $fullName,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'birth_day_month' => $birthDayMonth,
            'birth_place' => $birthPlace,
            'address' => $address,
            'email' => $email,
            'whatsapp' => $whatsapp,
            'photos' => $finalPhotos,
            'msk_month' => $mskMonth,
            'session_numbers' => $sessionNumbers,
            'notes' => $notes,
            'completed_at' => trim((string) ($existing['completed_at'] ?? '')),
            'journey_bridge_status' => normalize_journey_bridge_status((string) ($existing['journey_bridge_status'] ?? 'belum')),
            'status' => normalize_msk_participant_status((string) ($existing['status'] ?? 'active')),
            'created_at' => (string) ($existing['created_at'] ?? $now),
            'updated_at' => $now,
        ];

        $wasLinkedMember = trim((string) ($existing['member_id'] ?? '')) !== '';
        $membersBeforeSave = $members;
        $membersChangedBySync = false;
        auto_register_msk_participant_as_member($participantData, $members);
        $membersChangedBySync = sync_member_data_from_msk($participantData, $members);
        $finalLinkedMemberId = trim((string) ($participantData['member_id'] ?? ''));
        if ($finalLinkedMemberId !== '' && $finalLinkedMemberId !== $existingLinkedMemberId && is_member_registered_in_msk($mskClasses, $finalLinkedMemberId, $participantId)) {
            foreach ($uploadedPhotos as $uploadedPhoto) {
                $uploadedPath = sanitize_relative_upload_path((string) ($uploadedPhoto['path'] ?? ''));
                if ($uploadedPath !== '') {
                    delete_relative_upload_file($uploadedPath);
                }
            }
            $params['error'] = 'duplicate_msk_member';
            redirect_to('msk_classes', $params);
        }
        $isNowLinkedMember = trim((string) ($participantData['member_id'] ?? '')) !== '';
        $autoConverted = !$wasLinkedMember
            && $isNowLinkedMember
            && msk_is_complete($participantData);

        if ($existingIndex === null) {
            $mskClasses[] = $participantData;
        } else {
            $mskClasses[$existingIndex] = $participantData;
        }
        persist_people_registry_data($members, $mskClasses);

        if ($membersChangedBySync || $membersBeforeSave !== $members) {
            sync_member_family_links($members);
            persist_people_registry_data($members, $mskClasses);
        }
        foreach (array_keys($filesToDelete) as $pathToDelete) {
            delete_photo_file_if_unused($members, $mskClasses, $pathToDelete);
        }

        $redirectParams = ['saved' => 1];
        if ($autoConverted) {
            $redirectParams['converted'] = 1;
        }
        $redirectParams['batch_month'] = $batchMonthIsAll ? 'all' : $mskMonth;
        redirect_to('msk_classes', $redirectParams);
    }

    if ($action === 'save_msk_sessions') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $returnPage = trim((string) ($_POST['return_page'] ?? 'discipleship_dashboard'));
        if (!in_array($returnPage, ['discipleship_dashboard'], true)) {
            $returnPage = 'discipleship_dashboard';
        }

        $redirectParams = [];
        if ($id !== '') {
            $redirectParams['edit_msk_sessions'] = $id;
        }

        if ($id === '') {
            $redirectParams['error'] = 'invalid_msk_participant';
            redirect_to($returnPage, $redirectParams);
        }

        $existingIndex = null;
        foreach ($mskClasses as $idx => $participant) {
            if ((string) ($participant['id'] ?? '') === $id) {
                $existingIndex = $idx;
                break;
            }
        }
        if ($existingIndex === null) {
            $redirectParams['error'] = 'invalid_msk_participant';
            redirect_to($returnPage, $redirectParams);
        }

        $sessionNumbers = normalize_msk_session_numbers($_POST['session_numbers'] ?? []);
        $participantData = $mskClasses[$existingIndex];
        $wasLinkedMember = trim((string) ($participantData['member_id'] ?? '')) !== '';
        $membersBeforeSave = $members;
        $participantData['session_numbers'] = $sessionNumbers;
        $participantData['updated_at'] = now_iso();

        $membersChangedBySync = false;
        auto_register_msk_participant_as_member($participantData, $members);
        $membersChangedBySync = sync_member_data_from_msk($participantData, $members);

        $isNowLinkedMember = trim((string) ($participantData['member_id'] ?? '')) !== '';
        $autoConverted = !$wasLinkedMember
            && $isNowLinkedMember
            && msk_is_complete($participantData);

        $mskClasses[$existingIndex] = $participantData;
        persist_people_registry_data($members, $mskClasses);

        if ($membersChangedBySync || $membersBeforeSave !== $members) {
            sync_member_family_links($members);
            persist_people_registry_data($members, $mskClasses);
        }

        $redirectParams = ['msk_session_saved' => 1];
        if ($autoConverted) {
            $redirectParams['converted'] = 1;
        }
        redirect_to($returnPage, $redirectParams);
    }

    if ($action === 'save_journey_bridge_status') {
        $participantId = trim((string) ($_POST['id'] ?? ''));
        if ($participantId === '') {
            redirect_to('spiritual_journey');
        }

        $existingIndex = null;
        foreach ($mskClasses as $idx => $participant) {
            if ((string) ($participant['id'] ?? '') === $participantId) {
                $existingIndex = $idx;
                break;
            }
        }
        if ($existingIndex === null) {
            redirect_to('spiritual_journey');
        }

        $participantData = $mskClasses[$existingIndex];
        $participantData['journey_bridge_status'] = normalize_journey_bridge_status((string) ($_POST['journey_bridge_status'] ?? 'belum'));
        $participantData['updated_at'] = now_iso();
        $mskClasses[$existingIndex] = $participantData;
        persist_people_registry_data($members, $mskClasses);
        redirect_to('spiritual_journey', ['saved' => 1]);
    }

    if ($action === 'delete_msk_participant') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $batchMonthInput = trim((string) ($_POST['batch_month'] ?? ''));
        $batchMonthIsAll = strtolower($batchMonthInput) === 'all';
        $batchMonth = (!$batchMonthIsAll && $batchMonthInput !== '') ? normalize_month_value($batchMonthInput) : '';
        if ($id === '') {
            $params = ['error' => 'invalid_msk_participant'];
            if ($batchMonthIsAll) {
                $params['batch_month'] = 'all';
            } elseif ($batchMonth !== '') {
                $params['batch_month'] = $batchMonth;
            }
            redirect_to('msk_classes', $params);
        }

        $existingIndex = null;
        foreach ($mskClasses as $idx => $participant) {
            if ((string) ($participant['id'] ?? '') === $id) {
                $existingIndex = $idx;
                break;
            }
        }
        if ($existingIndex === null) {
            $params = ['error' => 'invalid_msk_participant'];
            if ($batchMonthIsAll) {
                $params['batch_month'] = 'all';
            } elseif ($batchMonth !== '') {
                $params['batch_month'] = $batchMonth;
            }
            redirect_to('msk_classes', $params);
        }

        $participantData = $mskClasses[$existingIndex];
        $participantData['status'] = 'inactive';
        $participantData['updated_at'] = now_iso();
        $mskClasses[$existingIndex] = $participantData;
        persist_people_registry_data($members, $mskClasses);

        $params = ['deleted' => 1];
        if ($batchMonthIsAll) {
            $params['batch_month'] = 'all';
        } elseif ($batchMonth !== '') {
            $params['batch_month'] = $batchMonth;
        }
        redirect_to('msk_classes', $params);
    }

    if ($action === 'reactivate_msk_participant') {
        $id = trim((string) ($_POST['id'] ?? ''));
        $batchMonthInput = trim((string) ($_POST['batch_month'] ?? ''));
        $batchMonthIsAll = strtolower($batchMonthInput) === 'all';
        $batchMonth = (!$batchMonthIsAll && $batchMonthInput !== '') ? normalize_month_value($batchMonthInput) : '';
        if ($id === '') {
            $params = ['error' => 'invalid_msk_participant'];
            if ($batchMonthIsAll) {
                $params['batch_month'] = 'all';
            } elseif ($batchMonth !== '') {
                $params['batch_month'] = $batchMonth;
            }
            redirect_to('msk_classes', $params);
        }

        $existingIndex = null;
        foreach ($mskClasses as $idx => $participant) {
            if ((string) ($participant['id'] ?? '') === $id) {
                $existingIndex = $idx;
                break;
            }
        }
        if ($existingIndex === null) {
            $params = ['error' => 'invalid_msk_participant'];
            if ($batchMonthIsAll) {
                $params['batch_month'] = 'all';
            } elseif ($batchMonth !== '') {
                $params['batch_month'] = $batchMonth;
            }
            redirect_to('msk_classes', $params);
        }

        $participantData = $mskClasses[$existingIndex];
        $participantData['status'] = 'active';
        $participantData['updated_at'] = now_iso();
        $mskClasses[$existingIndex] = $participantData;
        persist_people_registry_data($members, $mskClasses);

        $params = ['reactivated' => 1];
        if ($batchMonthIsAll) {
            $params['batch_month'] = 'all';
        } elseif ($batchMonth !== '') {
            $params['batch_month'] = $batchMonth;
        }
        redirect_to('msk_classes', $params);
    }


}
