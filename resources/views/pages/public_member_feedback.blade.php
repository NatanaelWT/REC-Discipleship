<?php

if ($page === 'public_member_feedback') {
    page_header_plain('Jurnal Umpan Balik Anggota', $settings, 'page-dg-public page-public-member-feedback');
    $old = is_array($publicMemberFeedbackOld) ? $publicMemberFeedbackOld : [];
    $publicBranchRaw = trim((string) ($_GET['cabang'] ?? ''));
    if ($publicBranchRaw === '') {
        $publicBranchRaw = trim((string) ($old['public_cabang'] ?? ''));
    }
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
    $publicBranchLabel = public_branch_label($publicBranch);
    $old['public_cabang'] = $publicBranch;

    $branchRuntime = load_branch_discipleship_runtime($publicBranch);
    $branchPeopleById = is_array($branchRuntime['people_by_id'] ?? null) ? $branchRuntime['people_by_id'] : [];
    $branchGroups = is_array($branchRuntime['groups'] ?? null) ? $branchRuntime['groups'] : [];
    $dgFormData = build_dg_public_form_data($branchGroups, $branchPeopleById);
    $groupOptions = is_array($dgFormData['groups'] ?? null) ? $dgFormData['groups'] : [];
    $groupMap = is_array($dgFormData['group_map'] ?? null) ? $dgFormData['group_map'] : [];
    $questions = public_member_feedback_questions();

    $oldGroupId = trim((string) ($old['group_id'] ?? ''));
    $oldRespondentPersonId = trim((string) ($old['respondent_person_id'] ?? ''));
    $oldFeedbackSession = normalize_public_member_feedback_session($old['feedback_session'] ?? '');
    if ($oldFeedbackSession === 0) {
        $oldFeedbackSession = normalize_public_member_feedback_session($_GET['feedback_session'] ?? ($_GET['session'] ?? ''));
    }
    $oldRatings = is_array($old['ratings'] ?? null) ? $old['ratings'] : [];
    $oldNotes = is_array($old['notes'] ?? null) ? $old['notes'] : [];

    $formDisabled = count($groupOptions) === 0;
    $hasValidSelectedGroup = $oldGroupId !== '' && isset($groupMap[$oldGroupId]) && is_array($groupMap[$oldGroupId]);
    $selectedGroupRow = $hasValidSelectedGroup ? $groupMap[$oldGroupId] : null;
    $selectedGroupMembers = [];
    if (is_array($selectedGroupRow)) {
        $members = $selectedGroupRow['members'] ?? [];
        if (is_array($members)) {
            $selectedGroupMembers = $members;
        }
    }
    $hasValidRespondent = false;
    foreach ($selectedGroupMembers as $memberRow) {
        if (!is_array($memberRow)) {
            continue;
        }
        if (trim((string) ($memberRow['id'] ?? '')) === $oldRespondentPersonId) {
            $hasValidRespondent = true;
            break;
        }
    }
    $feedbackUnlocked = !$formDisabled && $hasValidSelectedGroup && $hasValidRespondent && $oldFeedbackSession !== 0;
    $feedbackLockAttr = $feedbackUnlocked ? '' : ' disabled';
    $selectedLeaderLabel = 'DG Saudara';
    if (is_array($selectedGroupRow)) {
        $selectedLeaderLabel = public_member_feedback_group_title($selectedGroupRow);
    }
    $initialGroupMeta = 'Pilih kelompok DG, lalu pilih nama Saudara sebagai pengisi.';
    if (is_array($selectedGroupRow)) {
        $memberCount = count($selectedGroupMembers);
        $initialGroupMeta = public_member_feedback_group_title($selectedGroupRow) . ' - ' . (string) $memberCount . ' anggota';
    }

    if (isset($_GET['submitted'])) {
        render_alert('success', 'Jurnal umpan balik anggota berhasil dikirim. Terima kasih.');
    }
    if ($publicMemberFeedbackError !== '') {
        render_alert('danger', $publicMemberFeedbackError);
    }
    if ($formDisabled) {
        render_alert('danger', 'Belum ada data Kelompok DG. Hubungi admin terlebih dahulu.');
    }

    echo "<section class=\"card public-feedback-card\">\n";
    echo "  <div class=\"card-row public-feedback-head\">\n";
    echo "    <div>\n";
    echo "      <h2>Jurnal Umpan Balik Anggota - " . h($publicBranchLabel) . "</h2>\n";
    echo "      <p class=\"public-feedback-subtitle\">Jurnal ini diisi oleh setiap anggota DG pada pertemuan 3 dan 12.</p>\n";
    echo "    </div>\n";
    echo "    <span class=\"badge warning\">Form Publik</span>\n";
    echo "  </div>\n";
    echo "  <form method=\"post\" class=\"form-grid public-member-feedback-form\" data-public-member-feedback-form data-feedback-hard-disabled=\"" . ($formDisabled ? '1' : '0') . "\">\n";
    echo "    <input type=\"hidden\" name=\"action\" value=\"save_public_member_feedback\">\n";
    echo "    <input type=\"hidden\" name=\"public_cabang\" value=\"" . h($publicBranch) . "\">\n";

    echo "    <label class=\"dg-question dg-panel\"><span class=\"question-label\">Kelompok DG <span class=\"required-mark\">*</span></span><select name=\"group_id\" data-feedback-group required" . ($formDisabled ? ' disabled' : '') . ">\n";
    echo "      <option value=\"\">- Pilih Kelompok DG -</option>\n";
    foreach ($groupOptions as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $selected = $oldGroupId === $groupId ? ' selected' : '';
        echo "      <option value=\"" . h($groupId) . "\"" . $selected . ">" . h(public_member_feedback_group_option_label($groupRow)) . "</option>\n";
    }
    echo "    </select></label>\n";

    $memberSelectDisabled = $formDisabled || !$hasValidSelectedGroup;
    $memberPlaceholder = $hasValidSelectedGroup ? '- Pilih Nama Pengisi -' : '- Pilih Kelompok Dulu -';
    echo "    <label class=\"dg-question dg-panel\"><span class=\"question-label\">Nama pengisi form ini <span class=\"required-mark\">*</span></span><select name=\"respondent_person_id\" data-feedback-respondent data-initial-member=\"" . h($oldRespondentPersonId) . "\" required" . ($memberSelectDisabled ? ' disabled' : '') . ">\n";
    echo "      <option value=\"\">" . h($memberPlaceholder) . "</option>\n";
    if ($hasValidSelectedGroup) {
        foreach ($selectedGroupMembers as $memberRow) {
            if (!is_array($memberRow)) {
                continue;
            }
            $memberId = trim((string) ($memberRow['id'] ?? ''));
            $memberName = trim((string) ($memberRow['name'] ?? ''));
            if ($memberId === '' || $memberName === '') {
                continue;
            }
            $selected = $oldRespondentPersonId === $memberId ? ' selected' : '';
            echo "      <option value=\"" . h($memberId) . "\"" . $selected . ">" . h($memberName) . "</option>\n";
        }
    }
    echo "    </select></label>\n";

    echo "    <label class=\"dg-question dg-panel\"><span class=\"question-label\">Pertemuan umpan balik <span class=\"required-mark\">*</span></span><select name=\"feedback_session\" data-feedback-session required" . ($formDisabled ? ' disabled' : '') . ">\n";
    echo "      <option value=\"\">- Pilih Pertemuan -</option>\n";
    foreach ([3, 12] as $sessionNumber) {
        $selected = $oldFeedbackSession === $sessionNumber ? ' selected' : '';
        echo "      <option value=\"" . h((string) $sessionNumber) . "\"" . $selected . ">Pertemuan " . h((string) $sessionNumber) . "</option>\n";
    }
    echo "    </select></label>\n";
    echo "    <div class=\"dg-panel public-feedback-group-meta\" data-feedback-group-meta>" . h($initialGroupMeta) . "</div>\n";

    foreach ($questions as $sectionKey => $section) {
        if (!is_array($section)) {
            continue;
        }
        $sectionTitle = trim((string) ($section['title'] ?? 'Bagian'));
        $sectionIntro = trim((string) ($section['intro'] ?? ''));
        echo "    <section class=\"public-feedback-section\" data-feedback-section=\"" . h((string) $sectionKey) . "\">\n";
        echo "      <div class=\"public-feedback-section-head\">\n";
        echo "        <h3>" . h($sectionTitle) . " - <span data-feedback-leader-name>" . h($selectedLeaderLabel) . "</span></h3>\n";
        if ($sectionIntro !== '') {
            echo "        <p>" . h($sectionIntro) . "</p>\n";
        }
        echo "      </div>\n";
        echo "      <div class=\"public-feedback-section-grid\">\n";
        $sectionRatings = $section['ratings'] ?? [];
        if (is_array($sectionRatings)) {
            foreach ($sectionRatings as $question) {
                if (!is_array($question)) {
                    continue;
                }
                $questionKey = trim((string) ($question['key'] ?? ''));
                $questionLabel = trim((string) ($question['label'] ?? ''));
                $scale = (int) ($question['scale'] ?? 10);
                if ($questionKey === '' || $questionLabel === '' || $scale < 1) {
                    continue;
                }
                $leftHint = trim((string) ($question['left'] ?? '1'));
                $middleHint = trim((string) ($question['middle'] ?? ''));
                $rightHint = trim((string) ($question['right'] ?? (string) $scale));
                $oldRatingValue = trim((string) ($oldRatings[$questionKey] ?? ''));
                $ratingClass = 'dg-rating public-feedback-rating';
                if ($scale === 5) {
                    $ratingClass .= ' is-scale-5';
                }
                echo "        <fieldset class=\"" . h($ratingClass) . "\">\n";
                echo "          <legend>" . h($questionLabel) . " <span class=\"required-mark\">*</span></legend>\n";
                echo "          <div class=\"dg-rating-body\">\n";
                echo "            <div class=\"dg-rating-hint public-feedback-hint" . ($middleHint !== '' ? ' has-middle' : '') . "\"><span>" . h($leftHint) . "</span>";
                if ($middleHint !== '') {
                    echo "<span>" . h($middleHint) . "</span>";
                }
                echo "<span>" . h($rightHint) . "</span></div>\n";
                echo "            <div class=\"dg-rating-scale\">\n";
                for ($score = 1; $score <= $scale; $score++) {
                    $checked = $oldRatingValue === (string) $score ? ' checked' : '';
                    echo "              <label class=\"dg-rating-option\"><input type=\"radio\" name=\"ratings[" . h($questionKey) . "]\" value=\"" . h((string) $score) . "\" data-feedback-requires-member" . $checked . $feedbackLockAttr . " required><span>" . h((string) $score) . "</span></label>\n";
                }
                echo "            </div>\n";
                echo "          </div>\n";
                echo "        </fieldset>\n";
            }
        }
        $noteKey = trim((string) ($section['note_key'] ?? ''));
        $noteLabel = trim((string) ($section['note_label'] ?? ''));
        if ($noteKey !== '' && $noteLabel !== '') {
            $oldNoteValue = trim((string) ($oldNotes[$noteKey] ?? ''));
            echo "        <label class=\"dg-question dg-panel public-feedback-note\"><span class=\"question-label\">" . h($noteLabel) . " <span class=\"public-feedback-optional\">Opsional</span></span><textarea name=\"notes[" . h($noteKey) . "]\" rows=\"3\" maxlength=\"2500\" data-feedback-requires-member" . $feedbackLockAttr . ">" . h($oldNoteValue) . "</textarea></label>\n";
        }
        echo "      </div>\n";
        echo "    </section>\n";
    }

    echo "    <div class=\"form-actions public-feedback-actions\">\n";
    echo "      <button class=\"btn\" type=\"submit\" data-feedback-submit" . ($feedbackUnlocked ? '' : ' disabled') . ">Kirim</button>\n";
    echo "      <a class=\"btn ghost\" href=\"?page=public_member_feedback_branch\">Kembali</a>\n";
    echo "    </div>\n";
    echo "  </form>\n";
    echo "</section>\n";

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $groupsJson = json_encode($groupOptions, $jsonFlags);
    if (!is_string($groupsJson)) {
        $groupsJson = '[]';
    }

    echo "<script>\n";
    echo "(function () {\n";
    echo "  var form = document.querySelector('[data-public-member-feedback-form]');\n";
    echo "  if (!form) { return; }\n";
    echo "  var groups = " . $groupsJson . ";\n";
    echo "  var groupSelect = form.querySelector('[data-feedback-group]');\n";
    echo "  var respondentSelect = form.querySelector('[data-feedback-respondent]');\n";
    echo "  var sessionSelect = form.querySelector('[data-feedback-session]');\n";
    echo "  var groupMeta = form.querySelector('[data-feedback-group-meta]');\n";
    echo "  var submitBtn = form.querySelector('[data-feedback-submit]');\n";
    echo "  var hardDisabled = form.getAttribute('data-feedback-hard-disabled') === '1';\n";
    echo "  var initialMemberId = respondentSelect ? (respondentSelect.getAttribute('data-initial-member') || '') : '';\n";
    echo "  var findGroup = function (groupId) {\n";
    echo "    return groups.find(function (groupRow) { return groupRow && groupRow.id === groupId; }) || null;\n";
    echo "  };\n";
    echo "  var formatGroupLabel = function (groupRow) {\n";
    echo "    if (!groupRow) { return 'DG Saudara'; }\n";
    echo "    var progress = String(groupRow.progress || 'DG').trim() || 'DG';\n";
    echo "    var leader = String(groupRow.leader_name || '').trim();\n";
    echo "    return leader ? progress + ' (' + leader + ')' : progress;\n";
    echo "  };\n";
    echo "  var renderRespondents = function () {\n";
    echo "    if (!respondentSelect || !groupSelect) { return; }\n";
    echo "    var keepMemberId = respondentSelect.value || initialMemberId;\n";
    echo "    var groupRow = findGroup(groupSelect.value || '');\n";
    echo "    while (respondentSelect.firstChild) { respondentSelect.removeChild(respondentSelect.firstChild); }\n";
    echo "    var placeholder = document.createElement('option');\n";
    echo "    placeholder.value = '';\n";
    echo "    placeholder.textContent = groupRow ? '- Pilih Nama Pengisi -' : '- Pilih Kelompok Dulu -';\n";
    echo "    respondentSelect.appendChild(placeholder);\n";
    echo "    respondentSelect.disabled = hardDisabled || !groupRow;\n";
    echo "    var members = groupRow && Array.isArray(groupRow.members) ? groupRow.members : [];\n";
    echo "    members.forEach(function (memberRow) {\n";
    echo "      if (!memberRow || !memberRow.id || !memberRow.name) { return; }\n";
    echo "      var option = document.createElement('option');\n";
    echo "      option.value = String(memberRow.id);\n";
    echo "      option.textContent = String(memberRow.name);\n";
    echo "      respondentSelect.appendChild(option);\n";
    echo "    });\n";
    echo "    if (keepMemberId && members.some(function (memberRow) { return memberRow && memberRow.id === keepMemberId; })) {\n";
    echo "      respondentSelect.value = keepMemberId;\n";
    echo "    } else {\n";
    echo "      respondentSelect.value = '';\n";
    echo "    }\n";
    echo "    initialMemberId = '';\n";
    echo "  };\n";
    echo "  var updateLabels = function () {\n";
    echo "    var groupRow = groupSelect ? findGroup(groupSelect.value || '') : null;\n";
    echo "    var label = formatGroupLabel(groupRow);\n";
    echo "    form.querySelectorAll('[data-feedback-leader-name]').forEach(function (node) { node.textContent = label; });\n";
    echo "    if (groupMeta) {\n";
    echo "      var members = groupRow && Array.isArray(groupRow.members) ? groupRow.members : [];\n";
    echo "      groupMeta.textContent = groupRow ? label + ' - ' + members.length + ' anggota' : 'Pilih kelompok DG, lalu pilih nama Saudara sebagai pengisi.';\n";
    echo "    }\n";
    echo "  };\n";
    echo "  var updateLockState = function () {\n";
    echo "    var groupReady = groupSelect ? (groupSelect.value || '') !== '' : false;\n";
    echo "    var respondentReady = respondentSelect ? (!respondentSelect.disabled && (respondentSelect.value || '') !== '') : false;\n";
    echo "    var sessionReady = sessionSelect ? (sessionSelect.value === '3' || sessionSelect.value === '12') : false;\n";
    echo "    var unlocked = !hardDisabled && groupReady && respondentReady && sessionReady;\n";
    echo "    form.querySelectorAll('[data-feedback-requires-member]').forEach(function (input) {\n";
    echo "      input.disabled = !unlocked;\n";
    echo "    });\n";
    echo "    if (submitBtn) { submitBtn.disabled = !unlocked; }\n";
    echo "  };\n";
    echo "  if (groupSelect) {\n";
    echo "    groupSelect.addEventListener('change', function () {\n";
    echo "      renderRespondents();\n";
    echo "      updateLabels();\n";
    echo "      updateLockState();\n";
    echo "    });\n";
    echo "  }\n";
    echo "  if (respondentSelect) { respondentSelect.addEventListener('change', updateLockState); }\n";
    echo "  if (sessionSelect) { sessionSelect.addEventListener('change', updateLockState); }\n";
    echo "  renderRespondents();\n";
    echo "  updateLabels();\n";
    echo "  updateLockState();\n";
    echo "}());\n";
    echo "</script>\n";

    page_footer_plain();
    legacy_exit();
}
