<?php

if ($page === 'public_dg_report') {
    page_header_plain('Form Laporan Pertemuan DG', $settings, 'page-dg-public page-public-dg-report');
    $old = is_array($publicDgReportOld) ? $publicDgReportOld : [];
    $publicBranchRaw = trim((string) ($_GET['cabang'] ?? ''));
    if ($publicBranchRaw === '') {
        $publicBranchRaw = trim((string) ($old['public_cabang'] ?? ''));
    }
    if ($publicBranchRaw === '') {
        // Tidak ada ?cabang= di URL — fallback ke cabang user jika login, redirect jika tidak
        if (is_logged_in()) {
            $publicBranch = current_user_branch();
        } else {
            redirect_to('public_dg_branch');
        }
    } elseif (!is_known_public_branch_code($publicBranchRaw)) {
        redirect_to('public_dg_branch', ['error' => 'invalid_branch']);
    } else {
        // Selalu gunakan cabang dari URL (?cabang=xxx), terlepas user login atau tidak
        $publicBranch = normalize_public_branch_code($publicBranchRaw);
    }
    $publicBranchLabel = public_branch_label($publicBranch);
    $old['public_cabang'] = $publicBranch;

    $branchRuntime = load_branch_discipleship_runtime($publicBranch);
    $branchPeopleById = is_array($branchRuntime['people_by_id'] ?? null) ? $branchRuntime['people_by_id'] : [];
    $branchGroups = is_array($branchRuntime['groups'] ?? null) ? $branchRuntime['groups'] : [];
    $dgFormData = build_dg_public_form_data($branchGroups, $branchPeopleById);
    $leaderOptions = $dgFormData['leaders'] ?? [];
    $groupOptions = $dgFormData['groups'] ?? [];
    $groupMap = $dgFormData['group_map'] ?? [];
    $oldLeaderId = trim((string) ($old['leader_id'] ?? ''));
    $oldGroupId = trim((string) ($old['group_id'] ?? ''));
    if ($oldLeaderId === '' && $oldGroupId !== '' && isset($groupMap[$oldGroupId])) {
        $oldLeaderId = trim((string) ($groupMap[$oldGroupId]['leader_id'] ?? ''));
    }
    $oldMeetingDate = normalize_ymd_date((string) ($old['meeting_date'] ?? ''));
    $oldMaterialTopic = trim((string) ($old['material_topic'] ?? ''));
    $oldMaterialTopicOther = trim((string) ($old['material_topic_other'] ?? ''));
    $oldAbsenceReason = trim((string) ($old['absence_reason'] ?? ''));
    $oldAdditionalNotes = trim((string) ($old['additional_notes'] ?? ''));
    $oldSharingOpenness = trim((string) ($old['sharing_openness'] ?? ''));
    $initialMeditationMinTimes = 2;
    if ($oldGroupId !== '' && isset($groupMap[$oldGroupId])) {
        $initialMeditationMinTimes = dg_progress_min_share_times((string) ($groupMap[$oldGroupId]['progress'] ?? ''));
    }
    $oldAbsentMemberIds = [];
    if (isset($old['absent_member_ids']) && is_array($old['absent_member_ids'])) {
        foreach ($old['absent_member_ids'] as $memberId) {
            $memberId = trim((string) $memberId);
            if ($memberId !== '' && !in_array($memberId, $oldAbsentMemberIds, true)) {
                $oldAbsentMemberIds[] = $memberId;
            }
        }
    }
    $oldMeditationSharerIds = [];
    if (isset($old['meditation_sharer_ids']) && is_array($old['meditation_sharer_ids'])) {
        foreach ($old['meditation_sharer_ids'] as $memberId) {
            $memberId = trim((string) $memberId);
            if ($memberId !== '' && !in_array($memberId, $oldMeditationSharerIds, true)) {
                $oldMeditationSharerIds[] = $memberId;
            }
        }
    }

    $qualityPrepareChecked = parse_bool_value($old['quality_prepare'] ?? false);
    $qualityPrayChecked = parse_bool_value($old['quality_pray'] ?? false);
    $qualityShareMeditationChecked = parse_bool_value($old['quality_share_meditation'] ?? false);
    $qualityRelationalChecked = parse_bool_value($old['quality_relational'] ?? false);

    $materialOptions = [];
    for ($i = 1; $i <= 12; $i++) {
        $materialOptions[] = 'Sesi ' . $i;
    }
    $materialOptions[] = 'Lainnya';

    $formDisabled = count($groupOptions) === 0;
    $hasValidSelectedGroup = $oldGroupId !== '' && isset($groupMap[$oldGroupId]);
    if ($hasValidSelectedGroup) {
        $selectedGroupLeaderId = trim((string) ($groupMap[$oldGroupId]['leader_id'] ?? ''));
        if ($selectedGroupLeaderId === '' || $selectedGroupLeaderId !== $oldLeaderId) {
            $hasValidSelectedGroup = false;
        }
    }
    $dgFormUnlocked = !$formDisabled && $oldLeaderId !== '' && $hasValidSelectedGroup;
    $dgLockAttr = $dgFormUnlocked ? '' : ' disabled';

    if (isset($_GET['submitted'])) {
        render_alert('success', 'Laporan pertemuan DG berhasil dikirim. Terima kasih.');
    }
    if ($publicDgReportError !== '') {
        render_alert('danger', $publicDgReportError);
    }
    if ($formDisabled) {
        render_alert('danger', 'Belum ada data Kelompok DG. Hubungi admin terlebih dahulu.');
    }

    echo "<section class=\"card public-dg-report-card\">\n";
    echo "  <div class=\"card-row\">\n";
    echo "    <h2>Jurnal Temu DG - " . h($publicBranchLabel) . "</h2>\n";
    echo "  </div>\n";
    echo "  <form method=\"post\" enctype=\"multipart/form-data\" class=\"form-grid dg-public-report-form\" data-dg-public-form data-dg-hard-disabled=\"" . ($formDisabled ? '1' : '0') . "\">\n";
    echo "    <input type=\"hidden\" name=\"action\" value=\"save_public_dg_report\">\n";
    echo "    <input type=\"hidden\" name=\"public_cabang\" value=\"" . h($publicBranch) . "\">\n";

    echo "    <label class=\"dg-question dg-panel\"><span class=\"question-label\">Nama Pemimpin DG <span class=\"required-mark\">*</span></span><select name=\"leader_id\" data-dg-leader required" . ($formDisabled ? ' disabled' : '') . ">\n";
    echo "      <option value=\"\">- Pilih Pemimpin -</option>\n";
    foreach ($leaderOptions as $leaderRow) {
        $leaderId = trim((string) ($leaderRow['id'] ?? ''));
        $leaderName = trim((string) ($leaderRow['name'] ?? ''));
        if ($leaderId === '' || $leaderName === '') {
            continue;
        }
        $selected = $oldLeaderId === $leaderId ? ' selected' : '';
        echo "      <option value=\"" . h($leaderId) . "\"" . $selected . ">" . h($leaderName) . "</option>\n";
    }
    echo "    </select></label>\n";

    $groupSelectDisabled = $formDisabled || $oldLeaderId === '';
    $groupPlaceholder = $oldLeaderId === '' ? '- Pilih Pemimpin Dulu -' : '- Pilih Kelompok -';
    echo "    <label class=\"dg-question dg-panel\"><span class=\"question-label\">Kelompok DG <span class=\"required-mark\">*</span></span><select name=\"group_id\" data-dg-group required" . ($groupSelectDisabled ? ' disabled' : '') . ">\n";
    echo "      <option value=\"\">" . h($groupPlaceholder) . "</option>\n";
    if ($oldLeaderId !== '') {
        foreach ($groupOptions as $groupRow) {
            $groupId = trim((string) ($groupRow['id'] ?? ''));
            $groupLeaderId = trim((string) ($groupRow['leader_id'] ?? ''));
            if ($groupId === '' || $groupLeaderId === '' || $groupLeaderId !== $oldLeaderId) {
                continue;
            }
            $memberNames = [];
            $membersList = $groupRow['members'] ?? [];
            if (is_array($membersList)) {
                foreach ($membersList as $memberRow) {
                    $memberName = trim((string) ($memberRow['name'] ?? ''));
                    if ($memberName !== '') {
                        $memberNames[] = $memberName;
                    }
                }
            }
            $memberLabel = count($memberNames) > 0 ? implode(', ', $memberNames) : 'Belum ada anggota';
            $selected = $oldGroupId === $groupId ? ' selected' : '';
            $label = $memberLabel;
            echo "      <option value=\"" . h($groupId) . "\"" . $selected . ">" . h($label) . "</option>\n";
        }
    }
    echo "    </select></label>\n";
    echo "    <label class=\"dg-question dg-panel\"><span class=\"question-label\">Tanggal Pelaksanaan <span class=\"required-mark\">*</span></span><input type=\"date\" name=\"meeting_date\" value=\"" . h($oldMeetingDate) . "\" data-dg-requires-group" . $dgLockAttr . " required></label>\n";

    echo "    <label class=\"dg-question dg-panel\"><span class=\"question-label\">Materi DG yang Dibahas <span class=\"required-mark\">*</span></span><select name=\"material_topic\" data-dg-material-topic data-dg-requires-group required" . $dgLockAttr . ">\n";
    echo "      <option value=\"\">- Pilih Materi -</option>\n";
    foreach ($materialOptions as $materialOption) {
        $selected = $oldMaterialTopic === $materialOption ? ' selected' : '';
        echo "      <option value=\"" . h($materialOption) . "\"" . $selected . ">" . h($materialOption) . "</option>\n";
    }
    echo "    </select></label>\n";

    $otherStyle = $oldMaterialTopic === 'Lainnya' ? '' : ' style="display:none"';
    echo "    <label class=\"dg-question dg-panel\" data-dg-material-other-wrap" . $otherStyle . ">Materi Lainnya<input type=\"text\" name=\"material_topic_other\" value=\"" . h($oldMaterialTopicOther) . "\" data-dg-material-other data-dg-requires-group" . $dgLockAttr . "></label>\n";

    echo "    <div class=\"dg-absence-block dg-panel dg-section-absence\">\n";
    echo "      <label class=\"dg-question\">Anggota DG yang Tidak Hadir</label>\n";
    echo "      <div class=\"panel-note\">Pilih anggota yang tidak hadir pada pertemuan ini.</div>\n";
    echo "      <div data-dg-absent-list></div>\n";
    echo "      <label class=\"dg-question dg-absence-reason\">Alasan Anggota Tidak Hadir<textarea name=\"absence_reason\" rows=\"2\" placeholder=\"Isi alasan jika ada anggota yang tidak hadir\" data-dg-requires-group" . $dgLockAttr . ">" . h($oldAbsenceReason) . "</textarea></label>\n";
    echo "    </div>\n";

    echo "    <div class=\"dg-checklist dg-section-quality\" role=\"group\" aria-labelledby=\"dg-quality-title\">\n";
    echo "      <div class=\"dg-section-title\" id=\"dg-quality-title\">Kualitas Pemimpin DG</div>\n";
    echo "      <label class=\"check-label\"><input type=\"checkbox\" name=\"quality_prepare\" value=\"1\" data-dg-requires-group" . ($qualityPrepareChecked ? ' checked' : '') . $dgLockAttr . "> Saya sudah mempersiapkan penyampaian materi sesi yang dilaporkan saat ini</label>\n";
    echo "      <label class=\"check-label\"><input type=\"checkbox\" name=\"quality_pray\" value=\"1\" data-dg-requires-group" . ($qualityPrayChecked ? ' checked' : '') . $dgLockAttr . "> Saya sudah mendoakan tiap anggota sebelum pertemuan DG yang dilaporkan saat ini</label>\n";
    echo "      <label class=\"check-label\"><input type=\"checkbox\" name=\"quality_share_meditation\" value=\"1\" data-dg-requires-group" . ($qualityShareMeditationChecked ? ' checked' : '') . $dgLockAttr . "> Saya sudah setia membagikan hasil meditasi Injil dengan kata \"aku\" atau \"saya\" di WAG DG dalam 1 minggu terakhir</label>\n";
    echo "      <label class=\"check-label\"><input type=\"checkbox\" name=\"quality_relational\" value=\"1\" data-dg-requires-group" . ($qualityRelationalChecked ? ' checked' : '') . $dgLockAttr . "> Saya sudah melakukan komunikasi relasional dengan tiap anggota kelompok di luar pertemuan DG dalam 1-2 minggu terakhir</label>\n";
    echo "    </div>\n";

    echo "    <div class=\"dg-rating dg-section-sharing\" role=\"group\" aria-labelledby=\"dg-sharing-title\">\n";
    echo "      <div class=\"dg-section-title\" id=\"dg-sharing-title\">Sharing kelompok semakin terbuka &amp; mendalam <span class=\"required-mark\">*</span></div>\n";
    echo "      <div class=\"dg-rating-body\">\n";
    echo "        <div class=\"dg-rating-hint\"><span>Sangat tidak setuju</span><span>Sangat setuju</span></div>\n";
    echo "        <div class=\"dg-rating-scale\">\n";
    for ($score = 1; $score <= 10; $score++) {
        $checked = $oldSharingOpenness === (string) $score ? ' checked' : '';
        echo "          <label class=\"dg-rating-option\"><input type=\"radio\" name=\"sharing_openness\" value=\"" . h((string) $score) . "\" data-dg-requires-group" . $checked . $dgLockAttr . " required><span>" . h((string) $score) . "</span></label>\n";
    }
    echo "        </div>\n";
    echo "      </div>\n";
    echo "    </div>\n";

    echo "    <div class=\"dg-share-block dg-panel dg-section-sharer\">\n";
    echo "      <label class=\"dg-question\" data-dg-meditation-label>Anggota DG yang membagikan hasil meditasi Injil dengan kata \"aku\" atau \"saya\" di WAG DG minimal " . h((string) $initialMeditationMinTimes) . " kali dalam 1 minggu terakhir (boleh kosong)</label>\n";
    echo "      <div data-dg-sharer-list></div>\n";
    echo "    </div>\n";

    echo "    <label class=\"dg-question dg-panel dg-section-notes\">Catatan Tambahan / Kendala (jika ada)<textarea name=\"additional_notes\" rows=\"3\" data-dg-requires-group" . $dgLockAttr . ">" . h($oldAdditionalNotes) . "</textarea></label>\n";

    echo "    <label class=\"dg-question dg-panel dg-section-photo\"><span class=\"question-label\">Foto Pertemuan</span><span class=\"dg-upload-field\" data-dg-upload-field><span class=\"dg-upload-copy\"><span class=\"dg-upload-badge\">Pilih Foto</span><span class=\"dg-upload-meta\" data-dg-upload-label>Belum ada file dipilih</span></span><span class=\"dg-upload-hint\">JPG, PNG, atau WEBP. Bisa pilih lebih dari satu.</span><input type=\"file\" name=\"meeting_photos[]\" accept=\"image/jpeg,image/png,image/webp\" data-dg-photo-input data-dg-requires-group" . $dgLockAttr . " multiple></span></label>\n";

    echo "    <div class=\"form-actions dg-form-actions\">\n";
    echo "      <button class=\"btn\" type=\"submit\" data-dg-submit" . ($dgFormUnlocked ? '' : ' disabled') . ">Kirim Laporan</button>\n";
    echo "      <a class=\"btn ghost\" href=\"/publik/jurnal-dg\">Kembali</a>\n";
    echo "    </div>\n";
    echo "  </form>\n";
    echo "</section>\n";

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    $groupsJson = json_encode($groupOptions, $jsonFlags);
    if (!is_string($groupsJson)) {
        $groupsJson = '[]';
    }
    $oldAbsentJson = json_encode($oldAbsentMemberIds, $jsonFlags);
    if (!is_string($oldAbsentJson)) {
        $oldAbsentJson = '[]';
    }
    $oldSharerJson = json_encode($oldMeditationSharerIds, $jsonFlags);
    if (!is_string($oldSharerJson)) {
        $oldSharerJson = '[]';
    }

    echo "<script>\n";
    echo "(function () {\n";
    echo "  var form = document.querySelector('[data-dg-public-form]');\n";
    echo "  if (!form) { return; }\n";
    echo "  var groups = " . $groupsJson . ";\n";
    echo "  var selectedAbsent = new Set(" . $oldAbsentJson . ");\n";
    echo "  var selectedSharer = new Set(" . $oldSharerJson . ");\n";
    echo "  var leaderSelect = form.querySelector('[data-dg-leader]');\n";
    echo "  var groupSelect = form.querySelector('[data-dg-group]');\n";
    echo "  var absentList = form.querySelector('[data-dg-absent-list]');\n";
    echo "  var sharerList = form.querySelector('[data-dg-sharer-list]');\n";
    echo "  var meditationLabel = form.querySelector('[data-dg-meditation-label]');\n";
    echo "  var materialTopic = form.querySelector('[data-dg-material-topic]');\n";
    echo "  var materialOtherWrap = form.querySelector('[data-dg-material-other-wrap]');\n";
    echo "  var materialOtherInput = form.querySelector('[data-dg-material-other]');\n";
    echo "  var photoInput = form.querySelector('[data-dg-photo-input]');\n";
    echo "  var photoField = form.querySelector('[data-dg-upload-field]');\n";
    echo "  var photoLabel = form.querySelector('[data-dg-upload-label]');\n";
    echo "  var submitBtn = form.querySelector('[data-dg-submit]');\n";
    echo "  var hardDisabled = form.getAttribute('data-dg-hard-disabled') === '1';\n";
    echo "  var initialGroupId = groupSelect ? (groupSelect.value || '') : '';\n";
    echo "  var collectChecked = function (container, fieldName) {\n";
    echo "    var selected = new Set();\n";
    echo "    if (!container) { return selected; }\n";
    echo "    var checked = container.querySelectorAll('input[name=\"' + fieldName + '\"]:checked');\n";
    echo "    checked.forEach(function (input) {\n";
    echo "      if (input && input.value) {\n";
    echo "        selected.add(input.value);\n";
    echo "      }\n";
    echo "    });\n";
    echo "    return selected;\n";
    echo "  };\n";
    echo "  var minTimesFromProgress = function (progress) {\n";
    echo "    var text = String(progress || '').trim().toUpperCase();\n";
    echo "    var match = text.match(/^DG\\s*([1-3])$/);\n";
    echo "    if (match) {\n";
    echo "      return parseInt(match[1], 10);\n";
    echo "    }\n";
    echo "    if (/^[1-3]$/.test(text)) {\n";
    echo "      return parseInt(text, 10);\n";
    echo "    }\n";
    echo "    return 2;\n";
    echo "  };\n";
    echo "  var updatePhotoInputState = function () {\n";
    echo "    if (!photoInput || !photoLabel) { return; }\n";
    echo "    var files = photoInput.files;\n";
    echo "    if (files && files.length > 0) {\n";
    echo "      photoLabel.textContent = files.length === 1 ? files[0].name : files.length + ' file dipilih';\n";
    echo "    } else {\n";
    echo "      photoLabel.textContent = 'Belum ada file dipilih';\n";
    echo "    }\n";
    echo "    if (photoField) {\n";
    echo "      photoField.classList.toggle('is-disabled', !!photoInput.disabled);\n";
    echo "      photoField.classList.toggle('has-value', !!(files && files.length > 0));\n";
    echo "    }\n";
    echo "  };\n";
    echo "  var updateFormLockState = function () {\n";
    echo "    var leaderReady = leaderSelect ? (leaderSelect.value || '') !== '' : false;\n";
    echo "    var groupReady = groupSelect ? (!groupSelect.disabled && (groupSelect.value || '') !== '') : false;\n";
    echo "    var unlocked = !hardDisabled && leaderReady && groupReady;\n";
    echo "    var targets = form.querySelectorAll('[data-dg-requires-group]');\n";
    echo "    targets.forEach(function (input) {\n";
    echo "      input.disabled = !unlocked;\n";
    echo "    });\n";
    echo "    if (submitBtn) {\n";
    echo "      submitBtn.disabled = !unlocked;\n";
    echo "    }\n";
    echo "    updatePhotoInputState();\n";
    echo "  };\n";
    echo "\n";
    echo "  var renderGroupOptions = function () {\n";
    echo "    if (!groupSelect) { return; }\n";
    echo "    var selectedLeader = leaderSelect ? (leaderSelect.value || '') : '';\n";
    echo "    var keepGroupId = groupSelect.value || initialGroupId;\n";
    echo "    while (groupSelect.firstChild) { groupSelect.removeChild(groupSelect.firstChild); }\n";
    echo "    var placeholder = document.createElement('option');\n";
    echo "    placeholder.value = '';\n";
    echo "    if (!selectedLeader) {\n";
    echo "      placeholder.textContent = '- Pilih Pemimpin Dulu -';\n";
    echo "      groupSelect.appendChild(placeholder);\n";
    echo "      groupSelect.disabled = true;\n";
    echo "      groupSelect.value = '';\n";
    echo "      initialGroupId = '';\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    groupSelect.disabled = false;\n";
    echo "    placeholder.textContent = '- Pilih Kelompok -';\n";
    echo "    groupSelect.appendChild(placeholder);\n";
    echo "    var filtered = groups.filter(function (groupRow) {\n";
    echo "      return groupRow.leader_id === selectedLeader;\n";
    echo "    });\n";
    echo "    if (filtered.length === 0) {\n";
    echo "      placeholder.textContent = '- Tidak ada kelompok untuk pemimpin ini -';\n";
    echo "    }\n";
    echo "    filtered.forEach(function (groupRow) {\n";
    echo "      var memberNames = Array.isArray(groupRow.members) ? groupRow.members.map(function (memberRow) {\n";
    echo "        return memberRow && memberRow.name ? String(memberRow.name) : '';\n";
    echo "      }).filter(function (name) { return name !== ''; }) : [];\n";
    echo "      var memberLabel = memberNames.length > 0 ? memberNames.join(', ') : 'Belum ada anggota';\n";
    echo "      var option = document.createElement('option');\n";
    echo "      option.value = groupRow.id;\n";
    echo "      option.textContent = memberLabel;\n";
    echo "      option.title = option.textContent;\n";
    echo "      groupSelect.appendChild(option);\n";
    echo "    });\n";
    echo "    if (keepGroupId && filtered.some(function (groupRow) { return groupRow.id === keepGroupId; })) {\n";
    echo "      groupSelect.value = keepGroupId;\n";
    echo "    } else {\n";
    echo "      groupSelect.value = '';\n";
    echo "    }\n";
    echo "    initialGroupId = '';\n";
    echo "  };\n";
    echo "\n";
    echo "  var renderChecklist = function (container, fieldName, selectedSet, members, emptyText) {\n";
    echo "    if (!container) { return; }\n";
    echo "    while (container.firstChild) { container.removeChild(container.firstChild); }\n";
    echo "    if (!members || members.length === 0) {\n";
    echo "      var empty = document.createElement('div');\n";
    echo "      empty.className = 'panel-note';\n";
    echo "      empty.textContent = emptyText;\n";
    echo "      container.appendChild(empty);\n";
    echo "      return;\n";
    echo "    }\n";
    echo "    members.forEach(function (memberRow) {\n";
    echo "      var label = document.createElement('label');\n";
    echo "      label.className = 'dg-member-item';\n";
    echo "      var checkbox = document.createElement('input');\n";
    echo "      checkbox.type = 'checkbox';\n";
    echo "      checkbox.name = fieldName;\n";
    echo "      checkbox.value = memberRow.id;\n";
    echo "      checkbox.setAttribute('data-dg-requires-group', '1');\n";
    echo "      checkbox.checked = selectedSet.has(memberRow.id);\n";
    echo "      label.appendChild(checkbox);\n";
    echo "      label.appendChild(document.createTextNode(' ' + (memberRow.name || '-')));\n";
    echo "      container.appendChild(label);\n";
    echo "    });\n";
    echo "  };\n";
    echo "\n";
    echo "  var renderMembers = function () {\n";
    echo "    var groupId = groupSelect ? (groupSelect.value || '') : '';\n";
    echo "    var groupRow = groups.find(function (item) { return item.id === groupId; }) || null;\n";
    echo "    var members = groupRow && Array.isArray(groupRow.members) ? groupRow.members : [];\n";
    echo "    var minTimes = minTimesFromProgress(groupRow ? groupRow.progress : '');\n";
    echo "    if (meditationLabel) {\n";
    echo "      meditationLabel.textContent = 'Anggota DG yang membagikan hasil meditasi Injil dengan kata \"aku\" atau \"saya\" di WAG DG minimal ' + minTimes + ' kali dalam 1 minggu terakhir (boleh kosong)';\n";
    echo "    }\n";
    echo "    renderChecklist(absentList, 'absent_member_ids[]', selectedAbsent, members, 'Belum ada anggota pada kelompok ini.');\n";
    echo "    renderChecklist(sharerList, 'meditation_sharer_ids[]', selectedSharer, members, 'Belum ada anggota pada kelompok ini.');\n";
    echo "  };\n";
    echo "\n";
    echo "  var syncMaterialOther = function () {\n";
    echo "    if (!materialTopic || !materialOtherWrap || !materialOtherInput) { return; }\n";
    echo "    var isOther = !materialTopic.disabled && materialTopic.value === 'Lainnya';\n";
    echo "    materialOtherWrap.style.display = isOther ? '' : 'none';\n";
    echo "    materialOtherInput.required = isOther;\n";
    echo "  };\n";
    echo "\n";
    echo "  if (leaderSelect) {\n";
    echo "    leaderSelect.addEventListener('change', function () {\n";
    echo "      selectedAbsent = collectChecked(absentList, 'absent_member_ids[]');\n";
    echo "      selectedSharer = collectChecked(sharerList, 'meditation_sharer_ids[]');\n";
    echo "      renderGroupOptions();\n";
    echo "      renderMembers();\n";
    echo "      updateFormLockState();\n";
    echo "      syncMaterialOther();\n";
    echo "    });\n";
    echo "  }\n";
    echo "  if (groupSelect) {\n";
    echo "    groupSelect.addEventListener('change', function () {\n";
    echo "      selectedAbsent = collectChecked(absentList, 'absent_member_ids[]');\n";
    echo "      selectedSharer = collectChecked(sharerList, 'meditation_sharer_ids[]');\n";
    echo "      renderMembers();\n";
    echo "      updateFormLockState();\n";
    echo "      syncMaterialOther();\n";
    echo "    });\n";
    echo "  }\n";
    echo "  if (materialTopic) {\n";
    echo "    materialTopic.addEventListener('change', syncMaterialOther);\n";
    echo "  }\n";
    echo "  if (photoInput) {\n";
    echo "    photoInput.addEventListener('change', updatePhotoInputState);\n";
    echo "  }\n";
    echo "\n";
    echo "  renderGroupOptions();\n";
    echo "  renderMembers();\n";
    echo "  updateFormLockState();\n";
    echo "  syncMaterialOther();\n";
    echo "})();\n";
    echo "</script>\n";

    page_footer_plain();
    legacy_exit();
}
