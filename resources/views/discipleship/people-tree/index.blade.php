<?php

if ($page === 'people_tree') {
    page_header('Pohon Pemuridan', $settings, $page, false, 'page-tree-v2');
    $centralReadOnly = is_effective_central_discipleship_readonly();
    $personSourceLabel = 'Peserta MSK';
    $personSourcePlaceholder = '- Pilih Peserta MSK -';
    $personSourceLabelLower = 'peserta MSK';
    $completedMskPlaceholder = '- Pilih Peserta MSK 12/12 -';
    $peopleTreeUrls = is_array($peopleTreeUrls ?? null) ? $peopleTreeUrls : [];
    $peopleTreeSavePersonUrl = (string) ($peopleTreeUrls['save_person'] ?? '/pemuridan/pohon/orang');
    $peopleTreeDeletePersonUrl = (string) ($peopleTreeUrls['delete_person'] ?? '/pemuridan/pohon/orang/hapus');
    $peopleTreeSaveGroupUrl = (string) ($peopleTreeUrls['save_group'] ?? '/pemuridan/pohon/kelompok');
    $peopleTreeLeaveGroupUrl = (string) ($peopleTreeUrls['leave_person_group'] ?? '/pemuridan/pohon/kelompok/keluar');
    $peopleTreeCompleteGroupUrl = (string) ($peopleTreeUrls['complete_group'] ?? '/pemuridan/pohon/kelompok/selesai');
    $peopleTreeReactivateGroupUrl = (string) ($peopleTreeUrls['reactivate_group'] ?? '/pemuridan/pohon/kelompok/aktifkan');
    $peopleTreeExportDotUrl = (string) ($peopleTreeUrls['export_dot'] ?? '/pemuridan/pohon/export-dot');
    $error = $_GET['error'] ?? '';
    if ($error === 'in_use') {
        echo "<div class=\"alert danger\">Orang masih memiliki binaan.</div>\n";
    } elseif ($error === 'missing_parent') {
        echo "<div class=\"alert danger\">Pilih leader/pembina terlebih dahulu.</div>\n";
    } elseif ($error === 'invalid_parent') {
        echo "<div class=\"alert danger\">Leader/pembina tidak valid.</div>\n";
    } elseif ($error === 'missing_member') {
        echo "<div class=\"alert danger\">Pilih " . h($personSourceLabelLower) . " terlebih dahulu.</div>\n";
    } elseif ($error === 'invalid_member') {
        echo "<div class=\"alert danger\">Data " . h($personSourceLabelLower) . " tidak valid.</div>\n";
    } elseif ($error === 'member_exists') {
        echo "<div class=\"alert danger\">" . h($personSourceLabel) . " sudah terdaftar di DG.</div>\n";
    } elseif ($error === 'member_not_complete') {
        echo "<div class=\"alert danger\">Peserta MSK harus sudah menyelesaikan 12 sesi.</div>\n";
    } elseif ($error === 'missing_person_name') {
        echo "<div class=\"alert danger\">Nama peserta DG wajib diisi.</div>\n";
    } elseif ($error === 'invalid_person') {
        echo "<div class=\"alert danger\">Data peserta DG tidak valid.</div>\n";
    } elseif ($error === 'invalid_group') {
        echo "<div class=\"alert danger\">Data kelompok tidak valid.</div>\n";

    } elseif ($error === 'dot_export_branch_required') {
        echo "<div class=\"alert danger\">Pilih satu cabang terlebih dahulu sebelum export DOT.</div>\n";
    } elseif ($error === 'dot_export_failed') {
        echo "<div class=\"alert danger\">Export DOT gagal dibuat.</div>\n";
    } elseif ($error === 'missing_group') {
        echo "<div class=\"alert danger\">Tambah anggota harus melalui kelompok.</div>\n";
    } elseif ($error === 'member_in_group') {
        $conflict = trim((string) ($_GET['conflict'] ?? ''));
        if ($conflict !== '') {
            echo "<div class=\"alert danger\">Anggota sudah bergabung di kelompok lain: " . h($conflict) . ".</div>\n";
        } else {
            echo "<div class=\"alert danger\">Anggota sudah bergabung di kelompok lain.</div>\n";
        }
    } elseif ($error === 'root_locked') {
        echo "<div class=\"alert danger\">Leader utama tidak bisa dihapus.</div>\n";
    } elseif ($error === 'reserved_name') {
        echo "<div class=\"alert danger\">Nama \"" . h($rootLeaderName) . "\" sudah dipakai sebagai leader utama.</div>\n";
    } elseif ($error === 'not_in_group') {
        echo "<div class=\"alert danger\">Orang tersebut tidak sedang berada di kelompok yang dipilih.</div>\n";
    } elseif ($error === 'group_not_active') {
        echo "<div class=\"alert danger\">DG ini sudah tidak aktif atau sudah selesai.</div>\n";
    } elseif ($error === 'group_not_completed') {
        echo "<div class=\"alert danger\">Hanya DG yang berstatus selesai yang bisa diaktifkan kembali.</div>\n";
    }
    if (isset($_GET['left_group'])) {
        echo "<div class=\"alert success\">Orang berhasil dikeluarkan dari DG tersebut dan tetap tersedia untuk bergabung ke DG lain.</div>\n";
    }
    if (isset($_GET['person_archived'])) {
        echo "<div class=\"alert success\">Data anggota berhasil dinonaktifkan dari pohon aktif. Riwayat pemuridannya tetap tersimpan.</div>\n";
    }
    if (isset($_GET['group_completed'])) {
        echo "<div class=\"alert success\">DG berhasil ditandai selesai dan sekarang sudah tidak aktif lagi.</div>\n";
    }
    if (isset($_GET['group_reactivated'])) {
        echo "<div class=\"alert success\">DG berhasil diaktifkan kembali.</div>\n";
    }

    $editId = $_GET['edit'] ?? '';
    $peopleSorted = $people;
    usort($peopleSorted, function ($a, $b) {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    $leaderCandidatesSorted = is_array($leaderCandidates ?? null) ? $leaderCandidates : $people;
    usort($leaderCandidatesSorted, function ($a, $b) {
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    $membersSorted = discipleship_person_sources($members, $mskClasses);
    usort($membersSorted, function ($a, $b) {
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });
    $completedMskSorted = completed_msk_person_sources($mskClasses);
    usort($completedMskSorted, function ($a, $b) {
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });
    $memberToPersonId = [];
    foreach ($people as $personRow) {
        if (!is_array($personRow)) {
            continue;
        }
        $personRowId = trim((string) ($personRow['id'] ?? ''));
        $personMemberId = trim((string) ($personRow['member_id'] ?? ''));
        if ($personRowId === '' || $personMemberId === '' || isset($memberToPersonId[$personMemberId])) {
            continue;
        }
        $memberToPersonId[$personMemberId] = $personRowId;
    }

    $peopleById = index_by_id($people);
    $treeRootConfigs = [];
    $branchRootConfigByCode = [];
    if ($centralReadOnly) {
        $selectedCentralBranch = isset($centralSelectedBranch)
            ? normalize_central_recap_branch((string) $centralSelectedBranch)
            : central_recap_selected_branch();
        foreach (central_recap_branch_options() as $branchOption) {
            $branchCode = normalize_central_recap_branch((string) ($branchOption['code'] ?? 'all'));
            if ($branchCode === 'all') {
                continue;
            }
            if ($selectedCentralBranch !== 'all' && $branchCode !== $selectedCentralBranch) {
                continue;
            }
            $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
            if ($branchLabel === '') {
                $branchLabel = strtoupper($branchCode);
            }
            $branchRootId = $rootLeaderId . '__' . $branchCode;
            $branchRootConfig = [
                'id' => $branchRootId,
                'branch_code' => $branchCode,
                'branch_label' => $branchLabel,
            ];
            $treeRootConfigs[] = $branchRootConfig;
            $branchRootConfigByCode[$branchCode] = $branchRootConfig;
            $peopleById[$branchRootId] = [
                'id' => $branchRootId,
                'name' => $branchLabel,
                'phone' => '',
                'role' => 'Leader',
                'notes' => 'Akar pemuridan cabang ' . $branchLabel,
                'kampus' => '',
                'jurusan' => '',
                'pekerjaan' => '',
                'parent_ids' => [],
                'branch_code' => $branchCode,
                'branch_label' => $branchLabel,
            ];
        }
    }
    if (count($treeRootConfigs) === 0) {
        $treeRootConfigs[] = [
            'id' => $rootLeaderId,
            'branch_code' => normalize_public_branch_code(current_user_branch()),
            'branch_label' => $rootLeaderName,
        ];
        $peopleById[$rootLeaderId] = [
            'id' => $rootLeaderId,
            'name' => $rootLeaderName,
            'phone' => '',
            'role' => 'Leader',
            'notes' => 'Pemimpin utama',
            'kampus' => '',
            'jurusan' => '',
            'pekerjaan' => '',
            'parent_ids' => [],
        ];
    }
    $treeGroups = build_people_tree_group_rows($discipleshipV2Model, $peopleById);
    $groupAssignedPersonIds = [];
    $leaderContextRootByPersonId = [];
    foreach ($treeGroups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $groupLeaderId = trim((string) ($groupRow['leader_id'] ?? ''));
        $groupId = trim((string) ($groupRow['id'] ?? ''));
        $groupBranchCode = '';
        if ($centralReadOnly && $groupId !== '') {
            foreach (($discipleshipV2Model['discipleship_groups'] ?? []) as $sourceGroupRow) {
                if (!is_array($sourceGroupRow) || trim((string) ($sourceGroupRow['id'] ?? '')) !== $groupId) {
                    continue;
                }
                $groupBranchCode = normalize_public_branch_code((string) ($sourceGroupRow['branch_code'] ?? ''));
                break;
            }
        }
        if ($centralReadOnly && $groupLeaderId !== '' && $groupBranchCode !== '' && isset($branchRootConfigByCode[$groupBranchCode])) {
            $leaderContextRootByPersonId[$groupLeaderId] = (string) ($branchRootConfigByCode[$groupBranchCode]['id'] ?? '');
        }
        $groupMemberIds = $groupRow['member_ids'] ?? [];
        if (!is_array($groupMemberIds)) {
            continue;
        }
        foreach ($groupMemberIds as $groupMemberIdRaw) {
            $groupMemberId = trim((string) $groupMemberIdRaw);
            if ($groupMemberId !== '') {
                $groupAssignedPersonIds[$groupMemberId] = true;
            }
        }
    }
    $childrenMap = [];
    foreach ($people as $personRow) {
        if (!is_array($personRow)) {
            continue;
        }
        $personRowId = trim((string) ($personRow['id'] ?? ''));
        if ($personRowId === '') {
            continue;
        }
        $personBranchCode = normalize_public_branch_code((string) ($personRow['branch_code'] ?? current_user_branch()));
        $rootParentId = $rootLeaderId;
        if ($centralReadOnly) {
            $contextRootId = trim((string) ($leaderContextRootByPersonId[$personRowId] ?? ''));
            $branchRootConfig = $contextRootId !== '' && isset($peopleById[$contextRootId])
                ? ['id' => $contextRootId]
                : ($branchRootConfigByCode[$personBranchCode] ?? null);
            if (!is_array($branchRootConfig)) {
                continue;
            }
            $rootParentId = (string) ($branchRootConfig['id'] ?? $rootLeaderId);
        }
        $parentIds = get_parent_ids($personRow);
        $primaryParent = trim((string) ($parentIds[0] ?? ''));
        if (
            $centralReadOnly
            && $primaryParent !== ''
            && (
                !isset($peopleById[$primaryParent])
                || normalize_public_branch_code((string) ($peopleById[$primaryParent]['branch_code'] ?? '')) !== $personBranchCode
            )
            && !isset($leaderContextRootByPersonId[$personRowId])
        ) {
            $primaryParent = '';
        }
        if ($primaryParent === '') {
            if (isset($groupAssignedPersonIds[$personRowId])) {
                continue;
            }
            $primaryParent = $rootParentId;
        }
        $childrenMap[$primaryParent][] = $personRow;
    }
    $treeGroupHistoryViews = build_people_tree_group_history_views($discipleshipV2Model, $peopleById, $dgMeetingReports);
    $membershipsByGroupId = [];
    foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRecord) {
        if (!is_array($membershipRecord)) {
            continue;
        }
        $groupId = trim((string) ($membershipRecord['group_id'] ?? ''));
        if ($groupId !== '') {
            $membershipsByGroupId[$groupId][] = $membershipRecord;
        }
    }
    $leadershipsByGroupId = [];
    foreach (($discipleshipV2Model['group_leaderships'] ?? []) as $leadershipRecord) {
        if (!is_array($leadershipRecord)) {
            continue;
        }
        $groupId = trim((string) ($leadershipRecord['group_id'] ?? ''));
        if ($groupId !== '') {
            $leadershipsByGroupId[$groupId][] = $leadershipRecord;
        }
    }
    $treePersonProfiles = is_array($treePersonProfiles ?? null) ? $treePersonProfiles : [];
    $groupsByLeader = [];
    foreach ($treeGroups as $groupRow) {
        if (!is_array($groupRow)) {
            continue;
        }
        $leaderId = trim((string) ($groupRow['leader_id'] ?? ''));
        if ($leaderId === '') {
            continue;
        }
        $groupsByLeader[$leaderId][] = $groupRow;
    }
    $membersById = index_by_id($members);
    $dotExportBranch = normalize_public_branch_code(current_user_branch());
    $dotExportDisabled = false;
    if ($centralReadOnly) {
        $dotExportBranch = isset($selectedCentralBranch)
            ? normalize_central_recap_branch((string) $selectedCentralBranch)
            : central_recap_selected_branch();
        $dotExportDisabled = $dotExportBranch === 'all';
    }

    echo "<style>\n";
    echo ".dg-member-picker{gap:14px;padding-top:6px;}\n";
    echo ".dg-member-picker-list{display:flex;flex-direction:column;gap:12px;}\n";
    echo ".dg-member-picker-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:12px;padding:14px 14px;border:1px solid rgba(148,163,184,.24);border-radius:18px;background:linear-gradient(180deg,rgba(255,255,255,.99),rgba(248,250,252,.96));box-shadow:inset 0 1px 0 rgba(255,255,255,.78);}\n";
    echo ".dg-member-picker-row select{margin:0;width:100%;min-width:0;}\n";
    echo ".dg-member-picker-remove{position:relative;z-index:2;display:inline-flex;align-items:center;justify-content:center;align-self:center;width:40px;min-width:40px;height:40px;padding:0;border-radius:999px;color:#b42318;border-color:rgba(180,35,24,.18);background:rgba(254,242,242,.98);box-shadow:none;}\n";
    echo ".dg-member-picker-remove:hover:not(:disabled),.dg-member-picker-remove:focus-visible:not(:disabled){background:rgba(254,226,226,.98);border-color:rgba(180,35,24,.28);color:#912018;}\n";
    echo ".dg-member-picker-remove:disabled{opacity:.42;cursor:not-allowed;}\n";
    echo ".dg-member-picker-remove .icon{width:16px;height:16px;}\n";
    echo ".dg-member-picker-remove span{font-size:20px;line-height:1;font-weight:600;transform:translateY(-1px);}\n";
    echo ".dg-member-picker-append{display:inline-flex;align-items:center;gap:10px;align-self:flex-start;margin-top:2px;padding:11px 18px;border-radius:999px;border:1px solid rgba(15,118,110,.18);background:linear-gradient(135deg,#0f766e,#155e75);color:#fff;box-shadow:0 10px 22px rgba(15,118,110,.18);}\n";
    echo ".dg-member-picker-append:hover,.dg-member-picker-append:focus-visible{border-color:rgba(15,118,110,.24);background:linear-gradient(135deg,#115e59,#164e63);color:#fff;transform:translateY(-1px);box-shadow:0 14px 28px rgba(15,118,110,.22);}\n";
    echo ".dg-member-picker-append .dg-member-picker-append-icon{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:rgba(255,255,255,.18);}\n";
    echo ".dg-member-picker-append .dg-member-picker-append-icon .icon{width:14px;height:14px;}\n";
    echo ".dg-member-picker-append .dg-member-picker-append-label{font-weight:600;letter-spacing:.01em;}\n";
    echo ".tree-v2-surface{position:relative;height:calc(100dvh - 120px);min-height:520px;background:transparent;border:0;box-shadow:none;padding:0;}\n";
    echo ".tree-v2-toolbar{position:absolute;top:0;right:0;z-index:5;display:flex;align-items:center;justify-content:flex-end;gap:10px;pointer-events:none;}\n";
    echo ".tree-v2-search{display:flex;align-items:center;gap:8px;pointer-events:auto;padding:8px 10px;border-radius:999px;background:rgba(255,255,255,.92);backdrop-filter:blur(10px);box-shadow:0 16px 36px rgba(15,23,42,.14);}\n";
    echo ".tree-v2-export-form{display:inline-flex;margin:0;}\n";
    echo ".tree-v2-export-dot{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;}\n";
    echo ".tree-v2-export-dot .icon{width:14px;height:14px;}\n";
    echo ".tree-v2-search input{width:220px;min-width:0;margin:0;padding:9px 14px;border-radius:999px;border:1px solid rgba(148,163,184,.28);background:#fff;}\n";
    echo ".tree-v2-search button{white-space:nowrap;}\n";
    echo ".tree-v2-toolbar .zoom-controls{pointer-events:auto;box-shadow:0 16px 36px rgba(15,23,42,.14);background:rgba(255,255,255,.92);backdrop-filter:blur(10px);}\n";
    echo ".tree-v2-scroll{height:100%;min-height:0;overflow:auto;padding-top:0 !important;overscroll-behavior:contain;}\n";
    echo ".tree-v2-root{gap:24px;align-items:flex-start;}\n";
    echo ".tree-v2-node.is-search-hit{box-shadow:0 0 0 3px rgba(245,158,11,.35),0 18px 32px rgba(245,158,11,.18) !important;}\n";
    echo ".tree-group-history-view{padding-right:4px;gap:12px;}\n";
    echo ".tree-group-history-view .journey-history-summary{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;padding:10px 12px;border-radius:16px;gap:8px;}\n";
    echo ".tree-group-history-view .journey-history-summary-main{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;min-width:0;}\n";
    echo ".tree-group-history-view .journey-history-summary-name{font-size:15px;line-height:1.25;}\n";
    echo ".tree-group-history-view .journey-history-summary-sub{font-size:12px;}\n";
    echo ".tree-group-history-view .journey-history-summary-badges{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;}\n";
    echo ".tree-group-history-view .journey-history-chip{padding:5px 10px;font-size:11px;}\n";
    echo ".tree-group-history-view .journey-history-facts{display:none;}\n";
    echo ".tree-group-history-view .journey-history-section-title{font-size:12px;letter-spacing:.03em;text-transform:uppercase;color:#475569;margin-top:2px;}\n";
    echo ".tree-group-history-view .journey-history-timeline{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;}\n";
    echo ".tree-group-history-view .journey-history-item{padding:9px 11px;border-radius:14px;gap:5px;box-shadow:none;min-height:0;}\n";
    echo ".tree-group-history-view .journey-history-item-head{gap:6px;align-items:flex-start;}\n";
    echo ".tree-group-history-view .journey-history-item-title{font-size:12px;line-height:1.25;}\n";
    echo ".tree-group-history-view .journey-history-item-date{font-size:10px;line-height:1.3;}\n";
    echo ".tree-group-history-view .journey-history-item-meta{gap:5px;}\n";
    echo ".tree-group-history-view .journey-history-item-meta .journey-history-chip{min-height:24px;padding:3px 9px;font-size:10px;}\n";
    echo ".tree-group-history-view .journey-history-item-note{font-size:10px;line-height:1.4;}\n";
    echo ".tree-group-history-view .journey-history-empty{padding:12px 14px;border-radius:14px;font-size:12px;line-height:1.55;}\n";
    echo ".tree-group-history-note{margin-bottom:6px;}\n";
    echo "#people-modal .modal-field{margin-bottom:18px;}\n";
    echo "#people-modal .modal-field:last-of-type{margin-bottom:0;}\n";
    echo "#people-modal .modal-field > span:first-child{display:inline-block;margin-bottom:8px;}\n";
    echo "#people-modal textarea[name=\"notes\"]{margin-top:4px;}\n";
    echo "#people-modal .modal-actions{margin-top:24px;padding-top:6px;}\n";
    echo "@media (max-width: 640px){.dg-member-picker{gap:12px;padding-top:4px;}.dg-member-picker-list{gap:10px;}.dg-member-picker-row{padding:12px;gap:10px;}.dg-member-picker-remove{width:36px;min-width:36px;height:36px;}.dg-member-picker-append{width:100%;justify-content:center;margin-top:0;}.tree-v2-surface{height:calc(100dvh - 92px);min-height:360px;}.tree-v2-toolbar{top:4px;right:4px;left:4px;gap:8px;align-items:flex-end;flex-direction:column;}.tree-v2-search{width:min(100%,360px);flex-wrap:wrap;border-radius:18px;}.tree-v2-search input{width:100%;flex:1 0 100%;}.tree-v2-toolbar .zoom-controls{transform:scale(.94);transform-origin:top right;}.tree-group-history-view .journey-history-timeline{grid-template-columns:1fr;}.tree-group-history-view .journey-history-item-head{grid-template-columns:1fr;}.tree-group-history-view .journey-history-summary{padding:12px;align-items:flex-start;}.tree-group-history-view .journey-history-summary-badges{justify-content:flex-start;}}\n";
    echo "</style>\n";

    echo "<section class=\"tree-v2-surface\">\n";
    echo "  <div class=\"tree-v2-toolbar\">\n";
    echo "    <div class=\"tree-v2-search\">\n";
    echo "      <form method=\"post\" action=\"" . h($peopleTreeExportDotUrl) . "\" class=\"tree-v2-export-form\">\n";
    echo "        " . csrf_field() . "\n";
    echo "        <input type=\"hidden\" name=\"action\" value=\"export_pohon_pemuridan_dot\">\n";
    echo "        <input type=\"hidden\" name=\"export_cabang\" value=\"" . h($dotExportBranch) . "\">\n";
    $dotExportButtonAttrs = $dotExportDisabled ? ' disabled aria-disabled="true" title="Pilih satu cabang dulu"' : ' title="Export .dot"';
    echo "        <button class=\"btn tiny ghost tree-v2-export-dot\" type=\"submit\"" . $dotExportButtonAttrs . ">" . icon_svg('download') . "<span>Export .dot</span></button>\n";
    echo "      </form>\n";
    echo "      <input type=\"search\" list=\"tree-person-search-list\" placeholder=\"Cari nama di pohon...\" data-tree-search-input>\n";
    echo "      <button class=\"btn tiny ghost\" type=\"button\" data-tree-search-submit>Cari</button>\n";
    echo "      <datalist id=\"tree-person-search-list\">\n";
    foreach ($peopleSorted as $searchPerson) {
        $searchName = trim((string) ($searchPerson['name'] ?? ''));
        if ($searchName === '') {
            continue;
        }
        echo "        <option value=\"" . h($searchName) . "\"></option>\n";
    }
    echo "      </datalist>\n";
    echo "    </div>\n";
    echo "    <div class=\"zoom-controls tree-hero-zoom-controls\" data-zoom-controls>\n";
    echo "      <button class=\"btn tiny ghost\" type=\"button\" data-zoom-out aria-label=\"Perkecil zoom\">-</button>\n";
    echo "      <span class=\"zoom-value\" data-zoom-value>50%</span>\n";
    echo "      <button class=\"btn tiny ghost\" type=\"button\" data-zoom-in aria-label=\"Perbesar zoom\">+</button>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "  <div class=\"tree-v2-scroll drag-scroll\" data-drag-scroll>\n";
    echo "    <div class=\"tree-v2-zoom\" data-tree-zoom>\n";
    echo "      <div class=\"tree-v2-graph\" role=\"tree\" aria-label=\"Grafik pohon DG vertikal\">\n";
    echo "        <ul class=\"tree-v2-root\">\n";
    foreach ($treeRootConfigs as $treeRootConfig) {
        if (!is_array($treeRootConfig)) {
            continue;
        }
        $treeRootId = trim((string) ($treeRootConfig['id'] ?? ''));
        if ($treeRootId === '') {
            continue;
        }
        render_people_tree_v3($treeRootId, $peopleById, $childrenMap, $groupsByLeader, $membersById, $treeRootId, [], 0, !$centralReadOnly);
    }
    echo "        </ul>\n";
    echo "      </div>\n";
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<div class=\"is-hidden\" data-tree-v2-history-templates>\n";
    foreach ($treeGroupHistoryViews as $historyGroupId => $historyView) {
        $templateId = trim((string) $historyGroupId);
        if ($templateId === '') {
            continue;
        }
        $templateTitle = trim((string) ($historyView['title'] ?? 'Riwayat Kelompok'));
        $templateContent = (string) ($historyView['content'] ?? '');
        echo "<template data-tree-v2-history-template=\"" . h($templateId) . "\" data-tree-v2-history-template-title=\"" . h($templateTitle) . "\">" . $templateContent . "</template>\n";
    }
    echo "</div>\n";

    echo view('partials.modal', [
        'id' => 'tree-v2-history-modal',
        'size' => 'standard',
        'modalAttrs' => ['data-tree-v2-history-modal' => true],
        'title' => 'Riwayat Kelompok',
        'titleAttrs' => ['data-tree-v2-history-title' => true],
        'closeAttrs' => ['data-tree-v2-history-close' => true],
        'bodyAttrs' => ['data-tree-v2-history-body' => true],
        'bodyHtml' => '<div class="journey-history-empty">Riwayat kelompok belum tersedia.</div>',
    ])->render();

    echo "<div class=\"is-hidden\" data-tree-v2-person-profile-templates>\n";
    foreach ($treePersonProfiles as $profilePersonId => $profile) {
        $templateId = trim((string) $profilePersonId);
        if ($templateId === '' || !is_array($profile)) {
            continue;
        }
        $templateTitle = trim((string) ($profile['full_name'] ?? 'Profil Orang'));
        if ($templateTitle === '') {
            $templateTitle = 'Profil Orang';
        }
        $templateContent = view('discipleship.msk-participants.profile', ['profile' => $profile])->render();
        echo "<template data-tree-v2-person-profile-template=\"" . h($templateId) . "\" data-tree-v2-person-profile-template-title=\"" . h($templateTitle) . "\">" . $templateContent . "</template>\n";
    }
    echo "</div>\n";

    $treePersonProfileFooterHtml = '';
    if (!$centralReadOnly) {
        $treePersonProfileFooterHtml .= '<div class="tree-v2-profile-actions">';
        $treePersonProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-add" type="button" data-tree-v2-profile-action="add_group">'.icon_svg('plus').'<span>Tambah Kelompok</span></button>';
        $treePersonProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-edit" type="button" data-tree-v2-profile-action="edit_person">'.icon_svg('edit').'<span>Edit Orang</span></button>';
        $treePersonProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-leave" type="button" data-tree-v2-profile-action="leave_group">'.icon_svg('exit').'<span>Keluar DG</span></button>';
        $treePersonProfileFooterHtml .= '<button class="btn tiny tree-v2-profile-action is-delete" type="button" data-tree-v2-profile-action="delete_person">'.icon_svg('trash').'<span>Hapus Anggota</span></button>';
        $treePersonProfileFooterHtml .= '</div>';
    }
    echo view('partials.modal', [
        'id' => 'tree-v2-person-profile-modal',
        'size' => 'standard',
        'modalAttrs' => ['data-tree-v2-person-profile-modal' => true],
        'cardClass' => 'member-view-modal-card msk-view-modal-card',
        'title' => 'Profil Orang',
        'titleAttrs' => ['data-tree-v2-person-profile-title' => true],
        'closeAttrs' => ['data-tree-v2-person-profile-close' => true],
        'bodyAttrs' => ['data-tree-v2-person-profile-body' => true],
        'bodyHtml' => '<div class="panel-note">Klik orang pada pohon untuk melihat profil.</div>',
        'footerHtml' => $treePersonProfileFooterHtml,
    ])->render();

    if (!$centralReadOnly) {
        ob_start();
        echo "      <form method=\"post\" action=\"" . h($peopleTreeSavePersonUrl) . "\" class=\"modal-form\" data-modal-form=\"add\">\n";
        echo "        " . csrf_field() . "\n";
        echo "        <input type=\"hidden\" name=\"action\" value=\"save_person\">\n";
        echo "        <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "        <input type=\"hidden\" name=\"group_id\" value=\"\">\n";
        echo "        <input type=\"hidden\" name=\"leader_id\" value=\"\">\n";
        echo "        <div class=\"modal-field\" data-add-member-source-wrap>\n";
        echo "          <span>Peserta MSK Selesai</span>\n";
        echo "          <div class=\"stack-sm dg-member-picker\" data-add-member-picker>\n";
        echo "            <div class=\"stack-sm dg-member-picker-list\" data-add-member-list>\n";
        echo "              <div class=\"dg-member-picker-row\" data-add-member-row>\n";
        echo "                <select name=\"member_ids[]\" required>\n";
        echo "                  <option value=\"\">" . h($completedMskPlaceholder) . "</option>\n";
        foreach ($completedMskSorted as $memberRow) {
            $memberRowId = trim((string) ($memberRow['id'] ?? ''));
            $memberRowName = trim((string) ($memberRow['full_name'] ?? ''));
            if ($memberRowId === '' || $memberRowName === '') {
                continue;
            }
            $assignedPersonId = trim((string) ($memberToPersonId[$memberRowId] ?? ''));
            echo "                  <option value=\"" . h($memberRowId) . "\" data-person-id=\"" . h($assignedPersonId) . "\">" . h($memberRowName) . "</option>\n";
        }
        echo "                </select>\n";
        echo "                <button class=\"btn ghost tiny icon-btn dg-member-picker-remove\" type=\"button\" data-add-member-remove aria-label=\"Hapus peserta\" title=\"Hapus peserta\" disabled><span aria-hidden=\"true\">&times;</span></button>\n";
        echo "              </div>\n";
        echo "            </div>\n";
        echo "            <button class=\"btn tiny dg-member-picker-append\" type=\"button\" data-add-member-append><span class=\"dg-member-picker-append-icon\" aria-hidden=\"true\">" . icon_svg('plus') . "</span><span class=\"dg-member-picker-append-label\">Tambah Peserta</span></button>\n";
        echo "          </div>\n";
        echo "        </div>\n";
        echo "        <label class=\"modal-field is-hidden\" data-add-external-name-wrap>Nama External<input type=\"text\" name=\"full_name\" value=\"\" placeholder=\"Isi nama external\"></label>\n";
        echo "        <label class=\"modal-field\">Catatan<textarea name=\"notes\" rows=\"3\"></textarea></label>\n";
        echo "        <div class=\"modal-actions\">\n";
        echo "          <button class=\"btn\" type=\"submit\">Simpan</button>\n";
        echo "          <button class=\"btn ghost\" type=\"button\" data-modal-close>Batal</button>\n";
        echo "        </div>\n";
        echo "      </form>\n";

        echo "      <form method=\"post\" action=\"" . h($peopleTreeSavePersonUrl) . "\" class=\"modal-form is-hidden\" data-modal-form=\"edit\">\n";
        echo "        " . csrf_field() . "\n";
        echo "        <input type=\"hidden\" name=\"action\" value=\"save_person\">\n";
        echo "        <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "        <input type=\"hidden\" name=\"id\" value=\"\">\n";
        echo "        <input type=\"hidden\" name=\"member_id\" value=\"\">\n";
        echo "        <input type=\"hidden\" name=\"leader_id\" value=\"\">\n";
        echo "        <label class=\"modal-field\">Kelompok DG<select name=\"group_id\" required>";
        foreach ($groups as $existingGroup) {
            $existingGroupId = trim((string) ($existingGroup['id'] ?? ''));
            if ($existingGroupId === '') {
                continue;
            }
            $existingGroupLeader = trim((string) ($existingGroup['leader_name'] ?? ''));
            $existingGroupProgress = trim((string) ($existingGroup['progress'] ?? ''));
            $existingMemberIds = $existingGroup['member_ids'] ?? [];
            $existingMemberFirstNames = [];
            if (is_array($existingMemberIds)) {
                foreach ($existingMemberIds as $existingMemberId) {
                    $existingMemberId = trim((string) $existingMemberId);
                    if ($existingMemberId === '' || !isset($peopleById[$existingMemberId])) {
                        continue;
                    }
                    $existingMemberName = trim((string) ($peopleById[$existingMemberId]['name'] ?? ''));
                    if ($existingMemberName === '') {
                        continue;
                    }
                    $existingMemberNameParts = preg_split('/\s+/', $existingMemberName) ?: [];
                    $existingMemberFirstName = trim((string) ($existingMemberNameParts[0] ?? ''));
                    if ($existingMemberFirstName === '') {
                        continue;
                    }
                    $existingMemberFirstNames[] = $existingMemberFirstName;
                }
            }
            $existingMemberLabel = count($existingMemberFirstNames) > 0 ? implode(', ', $existingMemberFirstNames) : '-';
            echo "<option value=\"" . h($existingGroupId) . "\">" . h($existingGroupLeader . ' | ' . $existingGroupProgress . ' | ' . $existingMemberLabel) . "</option>";
        }
        echo "</select></label>\n";
        echo "        <label class=\"modal-field\">Catatan<textarea name=\"notes\" rows=\"3\"></textarea></label>\n";
        echo "        <div class=\"modal-actions\">\n";
        echo "          <button class=\"btn\" type=\"submit\">Simpan</button>\n";
        echo "          <button class=\"btn ghost\" type=\"button\" data-modal-close>Batal</button>\n";
        echo "        </div>\n";
        echo "      </form>\n";
        $peopleModalBodyHtml = ob_get_clean();
        echo view('partials.modal', [
            'id' => 'people-modal',
            'size' => 'standard',
            'modalAttrs' => [
                'data-modal' => true,
                'data-edit-id' => $editId,
            ],
            'title' => 'Modal',
            'titleAttrs' => ['data-modal-title' => true],
            'closeAttrs' => ['data-modal-close' => true],
            'bodyHtml' => $peopleModalBodyHtml,
        ])->render();

        $groupUpgradeSources = [];
        foreach (($discipleshipV2Model['discipleship_groups'] ?? []) as $groupRecord) {
            if (!is_array($groupRecord)) {
                continue;
            }
            $existingGroupId = trim((string) ($groupRecord['id'] ?? ''));
            if ($existingGroupId === '') {
                continue;
            }
            $existingGroupName = trim((string) ($groupRecord['name'] ?? 'Kelompok'));
            if ($existingGroupName === '') {
                $existingGroupName = 'Kelompok';
            }
            $existingGroupProgress = normalize_dg_progress_value((string) ($groupRecord['current_stage'] ?? $groupRecord['start_stage'] ?? $groupRecord['progress'] ?? ''));
            if ($existingGroupProgress === '') {
                $existingGroupProgress = 'DG 1';
            }
            $existingGroupStatus = strtolower(trim((string) ($groupRecord['status'] ?? 'active')));
            $leaderName = '-';
            $latestLeaderSort = '';
            foreach (($leadershipsByGroupId[$existingGroupId] ?? []) as $leadershipRecord) {
                if (!is_array($leadershipRecord)) {
                    continue;
                }
                $leaderPersonId = trim((string) ($leadershipRecord['leader_person_id'] ?? ''));
                if ($leaderPersonId === '' || !isset($peopleById[$leaderPersonId])) {
                    continue;
                }
                $sortDate = trim((string) ($leadershipRecord['end_date'] ?? ''));
                if ($sortDate === '') {
                    $sortDate = trim((string) ($leadershipRecord['start_date'] ?? ''));
                }
                if ($sortDate === '') {
                    $sortDate = trim((string) ($leadershipRecord['updated_at'] ?? $leadershipRecord['created_at'] ?? ''));
                }
                if ($latestLeaderSort !== '' && $sortDate !== '' && strcmp($sortDate, $latestLeaderSort) < 0) {
                    continue;
                }
                $leaderName = trim((string) ($peopleById[$leaderPersonId]['name'] ?? ''));
                if ($leaderName === '') {
                    $leaderName = '-';
                }
                $latestLeaderSort = $sortDate;
            }
            $memberRows = [];
            $memberNameParts = [];
            $seenMemberIds = [];
            foreach (($membershipsByGroupId[$existingGroupId] ?? []) as $membershipRecord) {
                if (!is_array($membershipRecord)) {
                    continue;
                }
                $existingMemberId = trim((string) ($membershipRecord['person_id'] ?? ''));
                if ($existingMemberId === '' || isset($seenMemberIds[$existingMemberId]) || !isset($peopleById[$existingMemberId])) {
                    continue;
                }
                $existingMemberName = trim((string) ($peopleById[$existingMemberId]['name'] ?? ''));
                if ($existingMemberName === '') {
                    continue;
                }
                $seenMemberIds[$existingMemberId] = true;
                $memberRows[] = [
                    'id' => $existingMemberId,
                    'name' => $existingMemberName,
                ];
                $existingMemberNamePieces = preg_split('/\s+/', $existingMemberName) ?: [];
                $existingMemberFirstName = trim((string) ($existingMemberNamePieces[0] ?? ''));
                if ($existingMemberFirstName !== '') {
                    $memberNameParts[] = $existingMemberFirstName;
                }
            }
            $groupUpgradeSources[] = [
                'id' => $existingGroupId,
                'name' => $existingGroupName,
                'leader_name' => $leaderName,
                'progress' => $existingGroupProgress,
                'status' => $existingGroupStatus,
                'member_rows' => $memberRows,
                'member_label' => count($memberNameParts) > 0 ? implode(', ', $memberNameParts) : '-',
            ];
        }
        usort($groupUpgradeSources, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['leader_name'] ?? ''), (string) ($right['leader_name'] ?? ''));
        });

        ob_start();
        echo "      <form method=\"post\" action=\"" . h($peopleTreeSaveGroupUrl) . "\" class=\"modal-form\" data-group-form=\"add\">\n";
        echo "        " . csrf_field() . "\n";
        echo "        <input type=\"hidden\" name=\"action\" value=\"save_group\">\n";
        echo "        <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "        <input type=\"hidden\" name=\"id\" value=\"\">\n";
        echo "        <label class=\"modal-field\">Leader<select name=\"leader_id\" required>";
        foreach ($leaderCandidatesSorted as $p) {
            $pid = trim((string) ($p['id'] ?? ''));
            if ($pid === '' || $pid === $rootLeaderId) {
                continue;
            }
            echo "<option value=\"" . h($pid) . "\">" . h((string) ($p['name'] ?? '')) . "</option>";
        }
        echo "</select></label>\n";
        echo "        <label class=\"modal-field\">Pendamping (Opsional)<select name=\"assistant_id\">";
        echo "<option value=\"\">- Tidak ada -</option>";
        foreach ($leaderCandidatesSorted as $p) {
            $pid = $p['id'] ?? '';
            echo "<option value=\"" . h($pid) . "\">" . h($p['name'] ?? '') . "</option>";
        }
        echo "</select></label>\n";
        echo "        <input type=\"hidden\" name=\"progress\" value=\"DG 1\">\n";
        echo "        <input type=\"hidden\" name=\"parent_group_id\" value=\"\">\n";
        echo "        <div class=\"modal-field is-hidden\" data-group-transition-wrap>\n";
        echo "          <fieldset class=\"dg-checklist msk-session-checklist\" data-group-transition-list>\n";
            echo "            <legend>Peserta yang Lanjut</legend>\n";
        echo "          </fieldset>\n";
        echo "        </div>\n";
        echo "        <label class=\"modal-field\">Catatan<textarea name=\"notes\" rows=\"3\"></textarea></label>\n";
        echo "        <div class=\"modal-actions\">\n";
        echo "          <button class=\"btn\" type=\"submit\">Simpan</button>\n";
        echo "          <button class=\"btn ghost\" type=\"button\" data-group-close>Batal</button>\n";
        echo "        </div>\n";
        echo "      </form>\n";

        echo "      <form method=\"post\" action=\"" . h($peopleTreeSaveGroupUrl) . "\" class=\"modal-form is-hidden\" data-group-form=\"edit\">\n";
        echo "        " . csrf_field() . "\n";
        echo "        <input type=\"hidden\" name=\"action\" value=\"save_group\">\n";
        echo "        <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "        <input type=\"hidden\" name=\"id\" value=\"\">\n";
        echo "        <label class=\"modal-field\">Leader<select name=\"leader_id\" required>";
        foreach ($leaderCandidatesSorted as $p) {
            $pid = trim((string) ($p['id'] ?? ''));
            if ($pid === '' || $pid === $rootLeaderId) {
                continue;
            }
            echo "<option value=\"" . h($pid) . "\">" . h((string) ($p['name'] ?? '')) . "</option>";
        }
        echo "</select></label>\n";
        echo "        <label class=\"modal-field\">Pendamping (Opsional)<select name=\"assistant_id\">";
        echo "<option value=\"\">- Tidak ada -</option>";
        foreach ($leaderCandidatesSorted as $p) {
            $pid = $p['id'] ?? '';
            echo "<option value=\"" . h($pid) . "\">" . h($p['name'] ?? '') . "</option>";
        }
        echo "</select></label>\n";
        echo "        <label class=\"modal-field\">Progress<select name=\"progress\">";
        foreach ($progressOptions as $opt) {
            echo "<option value=\"" . h($opt) . "\">" . h($opt) . "</option>";
        }
        echo "</select></label>\n";
        echo "        <label class=\"modal-field\">Kelompok Asal (opsional)<select name=\"parent_group_id\">";
        echo "<option value=\"\">- Tidak ada -</option>";
        foreach ($groupUpgradeSources as $existingGroup) {
            $existingGroupId = trim((string) ($existingGroup['id'] ?? ''));
            if ($existingGroupId === '') {
                continue;
            }
            $existingGroupLabel = trim((string) ($existingGroup['progress'] ?? ''));
            $existingGroupStatus = trim((string) ($existingGroup['status'] ?? 'active'));
            $existingGroupLeader = trim((string) ($existingGroup['leader_name'] ?? ''));
            $existingGroupName = trim((string) ($existingGroup['name'] ?? 'Kelompok'));
            $existingGroupStatusLabel = in_array($existingGroupStatus, ['completed', 'inactive', 'archived', 'closed', 'finished'], true) ? 'Nonaktif' : 'Aktif';
            echo "<option value=\"" . h($existingGroupId) . "\">" . h($existingGroupName . ' | ' . $existingGroupLeader . ' | ' . $existingGroupLabel . ' | ' . $existingGroupStatusLabel) . "</option>";
        }
        echo "</select></label>\n";
        echo "        <label class=\"modal-field\">Catatan<textarea name=\"notes\" rows=\"3\"></textarea></label>\n";
        echo "        <div class=\"modal-actions\">\n";
        echo "          <button class=\"btn\" type=\"submit\">Simpan</button>\n";
        echo "          <button class=\"btn ghost\" type=\"button\" data-group-close>Batal</button>\n";
        echo "        </div>\n";
        echo "      </form>\n";
        $groupModalBodyHtml = ob_get_clean();
        echo view('partials.modal', [
            'id' => 'group-modal',
            'size' => 'standard',
            'modalAttrs' => ['data-group-modal' => true],
            'title' => 'Kelompok',
            'titleAttrs' => ['data-group-title' => true],
            'closeAttrs' => ['data-group-close' => true],
            'bodyHtml' => $groupModalBodyHtml,
        ])->render();

        echo "<div class=\"is-hidden\" data-group-member-sources>\n";
        foreach ($groupUpgradeSources as $existingGroup) {
            $existingGroupId = trim((string) ($existingGroup['id'] ?? ''));
            if ($existingGroupId === '') {
                continue;
            }
            $existingGroupProgress = normalize_dg_progress_value((string) ($existingGroup['progress'] ?? ''));
            if ($existingGroupProgress === '') {
                $existingGroupProgress = 'DG 1';
            }
            $memberRows = is_array($existingGroup['member_rows'] ?? null) ? $existingGroup['member_rows'] : [];
            echo "<script type=\"application/json\" data-group-member-source=\"" . h($existingGroupId) . "\">" . json_encode([
                'group_id' => $existingGroupId,
                'progress' => $existingGroupProgress,
                'status' => trim((string) ($existingGroup['status'] ?? 'active')),
                'members' => $memberRows,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . "</script>\n";
        }
        echo "</div>\n";

        echo view('partials.modal', [
            'id' => 'tree-v2-action-modal',
            'size' => 'compact',
            'modalAttrs' => ['data-tree-v2-action-modal' => true],
            'cardClass' => 'tree-v2-action-modal-card',
            'title' => 'Aksi',
            'titleAttrs' => ['data-tree-v2-action-title' => true],
            'closeAttrs' => ['data-tree-v2-action-close' => true],
            'bodyHtml' => '<div class="modal-actions tree-v2-action-buttons">'
                .'<button class="btn ghost" type="button" data-tree-v2-action-do="view_history">Lihat History</button>'
                .'<button class="btn" type="button" data-tree-v2-action-do="add_group">Tambah Kelompok</button>'
                .'<button class="btn" type="button" data-tree-v2-action-do="add_member">Tambah Anggota</button>'
                .'<button class="btn" type="button" data-tree-v2-action-do="edit_person">Edit Orang</button>'
                .'<button class="btn danger" type="button" data-tree-v2-action-do="leave_group">Keluar dari DG Ini</button>'
                .'<button class="btn danger" type="button" data-tree-v2-action-do="delete_person">Hapus Data Anggota</button>'
                .'<button class="btn secondary" type="button" data-tree-v2-action-do="complete_group">Tandai DG Selesai</button>'
                .'<button class="btn secondary" type="button" data-tree-v2-action-do="reactivate_group">Aktifkan Kembali DG</button>'
                .'<button class="btn" type="button" data-tree-v2-action-do="upgrade_group">Upgrade DG</button>'
                .'</div>',
        ])->render();

        echo "<button class=\"is-hidden\" type=\"button\" data-tree-v2-proxy=\"add-member\" data-modal-open=\"add\"></button>\n";
        echo "<button class=\"is-hidden\" type=\"button\" data-tree-v2-proxy=\"edit-person\" data-modal-open=\"edit\"></button>\n";
        echo "<button class=\"is-hidden\" type=\"button\" data-tree-v2-proxy=\"add-group\" data-group-open=\"add\"></button>\n";
        echo "<button class=\"is-hidden\" type=\"button\" data-tree-v2-proxy=\"view-history\" data-tree-v2-history-open=\"\"></button>\n";
        echo "<form method=\"post\" action=\"" . h($peopleTreeLeaveGroupUrl) . "\" class=\"is-hidden\" data-tree-v2-leave-form>\n";
        echo "  " . csrf_field() . "\n";
        echo "  <input type=\"hidden\" name=\"action\" value=\"leave_person_group\">\n";
        echo "  <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "  <input type=\"hidden\" name=\"id\" value=\"\">\n";
        echo "  <input type=\"hidden\" name=\"group_id\" value=\"\">\n";
        echo "</form>\n";
        echo "<form method=\"post\" action=\"" . h($peopleTreeDeletePersonUrl) . "\" class=\"is-hidden\" data-tree-v2-delete-person-form>\n";
        echo "  " . csrf_field() . "\n";
        echo "  <input type=\"hidden\" name=\"action\" value=\"delete_person\">\n";
        echo "  <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "  <input type=\"hidden\" name=\"id\" value=\"\">\n";
        echo "</form>\n";
        echo "<form method=\"post\" action=\"" . h($peopleTreeCompleteGroupUrl) . "\" class=\"is-hidden\" data-tree-v2-complete-group-form>\n";
        echo "  " . csrf_field() . "\n";
        echo "  <input type=\"hidden\" name=\"action\" value=\"complete_group\">\n";
        echo "  <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "  <input type=\"hidden\" name=\"id\" value=\"\">\n";
        echo "</form>\n";
        echo "<form method=\"post\" action=\"" . h($peopleTreeReactivateGroupUrl) . "\" class=\"is-hidden\" data-tree-v2-reactivate-group-form>\n";
        echo "  " . csrf_field() . "\n";
        echo "  <input type=\"hidden\" name=\"action\" value=\"reactivate_group\">\n";
        echo "  <input type=\"hidden\" name=\"return_page\" value=\"people_tree\">\n";
        echo "  <input type=\"hidden\" name=\"id\" value=\"\">\n";
        echo "</form>\n";
    }

    page_footer();
}
