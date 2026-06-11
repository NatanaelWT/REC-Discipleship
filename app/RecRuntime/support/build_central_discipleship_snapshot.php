<?php

function build_central_discipleship_snapshot(): array {
    $combinedPeopleById = [];
    $combinedGroupsById = [];
    $combinedReportsById = [];
    $combinedMembersById = [];
    $combinedMskById = [];
    $combinedDiscipleshipV2Model = dgv2_empty_model();

    foreach (public_dg_branch_options() as $branchOption) {
        $branchCode = normalize_public_branch_code((string) ($branchOption['code'] ?? 'kutisari'));
        $branchLabel = trim((string) ($branchOption['label'] ?? strtoupper($branchCode)));
        if ($branchLabel === '') {
            $branchLabel = strtoupper($branchCode);
        }

        $memberMskUnifiedRecords = read_json(scoped_data_path(PEOPLE_REGISTRY_DATA_NAME, $branchCode), []);
        if (!is_array($memberMskUnifiedRecords)) {
            $memberMskUnifiedRecords = [];
        }
        $memberMskUnifiedRecords = normalize_people_registry_records($memberMskUnifiedRecords);
        $memberMskViews = people_registry_views($memberMskUnifiedRecords);

        $branchMembers = is_array($memberMskViews['members'] ?? null) ? $memberMskViews['members'] : [];
        $branchMskClasses = is_array($memberMskViews['msk_classes'] ?? null) ? $memberMskViews['msk_classes'] : [];
        $branchReports = read_json(scoped_data_path('dg_meeting_reports', $branchCode), []);
        if (!is_array($branchReports)) {
            $branchReports = [];
        }
        $branchV2Model = dgv2_read_model($branchCode);
        $branchPeople = dgv2_people_projection($branchV2Model, $branchMembers, $branchMskClasses);

        $memberIdMap = [];
        $branchMemberRowsById = [];
        foreach ($branchMembers as $memberRow) {
            if (!is_array($memberRow)) {
                continue;
            }
            $memberIdRaw = trim((string) ($memberRow['id'] ?? ''));
            if ($memberIdRaw === '') {
                continue;
            }
            $memberIdMap[$memberIdRaw] = scoped_virtual_id($branchCode, $memberIdRaw);
            $branchMemberRowsById[$memberIdRaw] = $memberRow;
        }

        foreach ($branchMembers as $memberRow) {
            if (!is_array($memberRow)) {
                continue;
            }
            $memberIdRaw = trim((string) ($memberRow['id'] ?? ''));
            if ($memberIdRaw === '') {
                continue;
            }
            $memberId = $memberIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw);
            if ($memberId === '') {
                continue;
            }
            $familyIdsInput = $memberRow['family_ids'] ?? [];
            if (!is_array($familyIdsInput)) {
                $familyIdsInput = [];
            }
            $familyIds = [];
            foreach ($familyIdsInput as $familyIdRaw) {
                $familyIdRaw = trim((string) $familyIdRaw);
                if ($familyIdRaw === '') {
                    continue;
                }
                $mappedFamilyId = $memberIdMap[$familyIdRaw] ?? scoped_virtual_id($branchCode, $familyIdRaw);
                if ($mappedFamilyId !== '' && $mappedFamilyId !== $memberId) {
                    $familyIds[] = $mappedFamilyId;
                }
            }
            $familyIds = array_values(array_unique($familyIds));

            $memberOut = $memberRow;
            $memberOut['id'] = $memberId;
            $memberOut['full_name'] = append_branch_suffix((string) ($memberRow['full_name'] ?? ''), $branchLabel);
            $memberOut['family_ids'] = $familyIds;
            $memberOut['branch_code'] = $branchCode;
            $memberOut['branch_label'] = $branchLabel;
            $combinedMembersById[$memberId] = $memberOut;
        }

        foreach ($branchMskClasses as $participantRow) {
            if (!is_array($participantRow)) {
                continue;
            }
            $participantIdRaw = trim((string) ($participantRow['id'] ?? ''));
            if ($participantIdRaw === '') {
                continue;
            }
            $participantId = scoped_virtual_id($branchCode, $participantIdRaw);
            if ($participantId === '') {
                continue;
            }

            $memberIdRaw = trim((string) ($participantRow['member_id'] ?? ''));
            $memberId = $memberIdRaw !== '' ? ($memberIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw)) : '';

            $participantOut = $participantRow;
            $participantOut['id'] = $participantId;
            $participantOut['member_id'] = $memberId;
            $participantOut['full_name'] = append_branch_suffix((string) ($participantRow['full_name'] ?? ''), $branchLabel);
            $participantOut['branch_code'] = $branchCode;
            $participantOut['branch_label'] = $branchLabel;
            $combinedMskById[$participantId] = $participantOut;
        }

        $personIdMap = [];
        foreach ($branchPeople as $personRow) {
            if (!is_array($personRow)) {
                continue;
            }
            $personIdRaw = trim((string) ($personRow['id'] ?? ''));
            if ($personIdRaw === '') {
                continue;
            }
            $personIdMap[$personIdRaw] = scoped_virtual_id($branchCode, $personIdRaw);
        }
        $personNameById = [];
        foreach ($branchPeople as $personRow) {
            if (!is_array($personRow)) {
                continue;
            }
            $personIdRaw = trim((string) ($personRow['id'] ?? ''));
            if ($personIdRaw === '') {
                continue;
            }
            $personId = $personIdMap[$personIdRaw] ?? scoped_virtual_id($branchCode, $personIdRaw);
            if ($personId === '') {
                continue;
            }
            $parentIdsInput = $personRow['parent_ids'] ?? [];
            if (!is_array($parentIdsInput)) {
                $parentIdsInput = [];
            }
            $parentIds = [];
            foreach ($parentIdsInput as $parentIdRaw) {
                $parentIdRaw = trim((string) $parentIdRaw);
                if ($parentIdRaw === '') {
                    continue;
                }
                $parentId = $personIdMap[$parentIdRaw] ?? scoped_virtual_id($branchCode, $parentIdRaw);
                if ($parentId !== '' && $parentId !== $personId) {
                    $parentIds[] = $parentId;
                }
            }
            $parentIds = array_values(array_unique($parentIds));

            $memberIdRaw = trim((string) ($personRow['member_id'] ?? ''));
            $personMemberId = $memberIdRaw !== '' ? ($memberIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw)) : '';

            $personName = trim((string) ($personRow['name'] ?? ''));
            $personPhone = trim((string) ($personRow['phone'] ?? ''));
            if ($memberIdRaw !== '' && isset($branchMemberRowsById[$memberIdRaw])) {
                $memberSource = $branchMemberRowsById[$memberIdRaw];
                if ($personName === '') {
                    $personName = trim((string) ($memberSource['full_name'] ?? ''));
                }
                if ($personPhone === '') {
                    $personPhone = trim((string) ($memberSource['whatsapp'] ?? ''));
                }
            }
            $personDisplayName = $personName !== '' ? append_branch_suffix($personName, $branchLabel) : '';

            $personOut = $personRow;
            $personOut['id'] = $personId;
            $personOut['member_id'] = $personMemberId;
            $personOut['parent_ids'] = $parentIds;
            $personOut['name'] = $personDisplayName;
            $personOut['phone'] = $personPhone;
            $personOut['branch_code'] = $branchCode;
            $personOut['branch_label'] = $branchLabel;
            $combinedPeopleById[$personId] = $personOut;
            $personNameById[$personId] = (string) $personOut['name'];
        }

        $branchPeopleById = index_by_id($branchPeople);
        $branchGroups = dgv2_groups_projection($branchV2Model, $branchPeopleById);
        $groupIdMap = [];
        $groupNameById = [];
        foreach ($branchGroups as $groupRow) {
            if (!is_array($groupRow)) {
                continue;
            }
            $groupIdRaw = trim((string) ($groupRow['id'] ?? ''));
            if ($groupIdRaw === '') {
                continue;
            }
            $groupIdMap[$groupIdRaw] = scoped_virtual_id($branchCode, $groupIdRaw);
        }
        foreach ($branchGroups as $groupRow) {
            if (!is_array($groupRow)) {
                continue;
            }
            $groupIdRaw = trim((string) ($groupRow['id'] ?? ''));
            if ($groupIdRaw === '') {
                continue;
            }
            $groupId = $groupIdMap[$groupIdRaw] ?? scoped_virtual_id($branchCode, $groupIdRaw);
            if ($groupId === '') {
                continue;
            }

            $leaderIdRaw = trim((string) ($groupRow['leader_id'] ?? ''));
            $leaderId = $leaderIdRaw !== '' ? ($personIdMap[$leaderIdRaw] ?? scoped_virtual_id($branchCode, $leaderIdRaw)) : '';
            $assistantIdRaw = trim((string) ($groupRow['assistant_id'] ?? ''));
            $assistantId = $assistantIdRaw !== '' ? ($personIdMap[$assistantIdRaw] ?? scoped_virtual_id($branchCode, $assistantIdRaw)) : '';

            $memberIdsInput = $groupRow['member_ids'] ?? [];
            if (!is_array($memberIdsInput)) {
                $memberIdsInput = [];
            }
            $memberIds = [];
            foreach ($memberIdsInput as $memberIdRaw) {
                $memberIdRaw = trim((string) $memberIdRaw);
                if ($memberIdRaw === '') {
                    continue;
                }
                $mappedMemberId = $personIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw);
                if ($mappedMemberId !== '') {
                    $memberIds[] = $mappedMemberId;
                }
            }
            $memberIds = array_values(array_unique($memberIds));

            $memberNamesInput = normalize_group_member_names($groupRow['member_names'] ?? []);
            $memberNames = [];
            foreach ($memberNamesInput as $memberIdRaw => $memberNameRaw) {
                $memberIdRaw = trim((string) $memberIdRaw);
                if ($memberIdRaw === '') {
                    continue;
                }
                $mappedMemberId = $personIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw);
                if ($mappedMemberId === '') {
                    continue;
                }
                $memberNames[$mappedMemberId] = append_branch_suffix((string) $memberNameRaw, $branchLabel);
            }

            $groupName = trim((string) ($groupRow['name'] ?? ''));
            if ($groupName === '') {
                $groupName = 'Kelompok';
            }
            $groupPrefix = '[' . $branchLabel . '] ';
            if (strpos($groupName, $groupPrefix) !== 0) {
                $groupName = $groupPrefix . $groupName;
            }

            $leaderName = trim((string) ($groupRow['leader_name'] ?? ''));
            if ($leaderName === '' && $leaderId !== '' && isset($personNameById[$leaderId])) {
                $leaderName = (string) $personNameById[$leaderId];
            }
            $leaderName = append_branch_suffix($leaderName, $branchLabel);

            $groupOut = $groupRow;
            $groupOut['id'] = $groupId;
            $groupOut['leader_id'] = $leaderId;
            $groupOut['assistant_id'] = $assistantId;
            $groupOut['member_ids'] = $memberIds;
            $groupOut['member_names'] = $memberNames;
            $groupOut['name'] = $groupName;
            $groupOut['leader_name'] = $leaderName;
            $groupOut['branch_code'] = $branchCode;
            $groupOut['branch_label'] = $branchLabel;
            $combinedGroupsById[$groupId] = $groupOut;
            $groupNameById[$groupId] = $groupName;
        }

        foreach (($branchV2Model['discipleship_persons'] ?? []) as $personRecord) {
            if (!is_array($personRecord)) {
                continue;
            }
            $personIdRaw = trim((string) ($personRecord['id'] ?? ''));
            if ($personIdRaw === '') {
                continue;
            }
            $personRecordOut = $personRecord;
            $personRecordOut['id'] = $personIdMap[$personIdRaw] ?? scoped_virtual_id($branchCode, $personIdRaw);
            $memberIdRaw = trim((string) ($personRecord['member_id'] ?? ''));
            if ($memberIdRaw !== '') {
                $personRecordOut['member_id'] = $memberIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw);
            }
            $personRecordOut['branch_code'] = $branchCode;
            $personRecordOut['branch_label'] = $branchLabel;
            $combinedDiscipleshipV2Model['discipleship_persons'][] = $personRecordOut;
        }

        foreach (($branchV2Model['discipleship_groups'] ?? []) as $groupRecord) {
            if (!is_array($groupRecord)) {
                continue;
            }
            $groupIdRaw = trim((string) ($groupRecord['id'] ?? ''));
            if ($groupIdRaw === '') {
                continue;
            }
            $groupRecordOut = $groupRecord;
            $groupRecordOut['id'] = $groupIdMap[$groupIdRaw] ?? scoped_virtual_id($branchCode, $groupIdRaw);
            $parentGroupIdRaw = trim((string) ($groupRecord['parent_group_id'] ?? ''));
            if ($parentGroupIdRaw !== '') {
                $groupRecordOut['parent_group_id'] = $groupIdMap[$parentGroupIdRaw] ?? scoped_virtual_id($branchCode, $parentGroupIdRaw);
            }
            $groupRecordOut['branch_code'] = $branchCode;
            $groupRecordOut['branch_label'] = $branchLabel;
            $combinedDiscipleshipV2Model['discipleship_groups'][] = $groupRecordOut;
        }

        foreach (($branchV2Model['group_memberships'] ?? []) as $membershipRecord) {
            if (!is_array($membershipRecord)) {
                continue;
            }
            $membershipRecordOut = $membershipRecord;
            $recordIdRaw = trim((string) ($membershipRecord['id'] ?? ''));
            if ($recordIdRaw !== '') {
                $membershipRecordOut['id'] = scoped_virtual_id($branchCode, $recordIdRaw);
            }
            $personIdRaw = trim((string) ($membershipRecord['person_id'] ?? ''));
            $groupIdRaw = trim((string) ($membershipRecord['group_id'] ?? ''));
            if ($personIdRaw !== '') {
                $membershipRecordOut['person_id'] = $personIdMap[$personIdRaw] ?? scoped_virtual_id($branchCode, $personIdRaw);
            }
            if ($groupIdRaw !== '') {
                $membershipRecordOut['group_id'] = $groupIdMap[$groupIdRaw] ?? scoped_virtual_id($branchCode, $groupIdRaw);
            }
            $membershipRecordOut['branch_code'] = $branchCode;
            $membershipRecordOut['branch_label'] = $branchLabel;
            $combinedDiscipleshipV2Model['group_memberships'][] = $membershipRecordOut;
        }

        foreach (($branchV2Model['group_leaderships'] ?? []) as $leadershipRecord) {
            if (!is_array($leadershipRecord)) {
                continue;
            }
            $leadershipRecordOut = $leadershipRecord;
            $recordIdRaw = trim((string) ($leadershipRecord['id'] ?? ''));
            if ($recordIdRaw !== '') {
                $leadershipRecordOut['id'] = scoped_virtual_id($branchCode, $recordIdRaw);
            }
            $personIdRaw = trim((string) ($leadershipRecord['leader_person_id'] ?? ''));
            $groupIdRaw = trim((string) ($leadershipRecord['group_id'] ?? ''));
            if ($personIdRaw !== '') {
                $leadershipRecordOut['leader_person_id'] = $personIdMap[$personIdRaw] ?? scoped_virtual_id($branchCode, $personIdRaw);
            }
            if ($groupIdRaw !== '') {
                $leadershipRecordOut['group_id'] = $groupIdMap[$groupIdRaw] ?? scoped_virtual_id($branchCode, $groupIdRaw);
            }
            $leadershipRecordOut['branch_code'] = $branchCode;
            $leadershipRecordOut['branch_label'] = $branchLabel;
            $combinedDiscipleshipV2Model['group_leaderships'][] = $leadershipRecordOut;
        }

        foreach (($branchV2Model['discipleship_relations'] ?? []) as $relationRecord) {
            if (!is_array($relationRecord)) {
                continue;
            }
            $relationRecordOut = $relationRecord;
            $recordIdRaw = trim((string) ($relationRecord['id'] ?? ''));
            if ($recordIdRaw !== '') {
                $relationRecordOut['id'] = scoped_virtual_id($branchCode, $recordIdRaw);
            }
            $mentorIdRaw = trim((string) ($relationRecord['mentor_person_id'] ?? ''));
            $discipleIdRaw = trim((string) ($relationRecord['disciple_person_id'] ?? ''));
            $contextGroupIdRaw = trim((string) ($relationRecord['context_group_id'] ?? ''));
            if ($mentorIdRaw !== '' && $mentorIdRaw !== 'virtual_injil') {
                $relationRecordOut['mentor_person_id'] = $personIdMap[$mentorIdRaw] ?? scoped_virtual_id($branchCode, $mentorIdRaw);
            }
            if ($discipleIdRaw !== '') {
                $relationRecordOut['disciple_person_id'] = $personIdMap[$discipleIdRaw] ?? scoped_virtual_id($branchCode, $discipleIdRaw);
            }
            if ($contextGroupIdRaw !== '') {
                $relationRecordOut['context_group_id'] = $groupIdMap[$contextGroupIdRaw] ?? scoped_virtual_id($branchCode, $contextGroupIdRaw);
            }
            $relationRecordOut['branch_code'] = $branchCode;
            $relationRecordOut['branch_label'] = $branchLabel;
            $combinedDiscipleshipV2Model['discipleship_relations'][] = $relationRecordOut;
        }

        foreach ($branchReports as $reportRow) {
            if (!is_array($reportRow)) {
                continue;
            }
            $reportIdRaw = trim((string) ($reportRow['id'] ?? ''));
            if ($reportIdRaw === '') {
                continue;
            }
            $reportId = scoped_virtual_id($branchCode, $reportIdRaw);
            if ($reportId === '') {
                continue;
            }

            $leaderIdRaw = trim((string) ($reportRow['leader_id'] ?? ''));
            $leaderId = $leaderIdRaw !== '' ? ($personIdMap[$leaderIdRaw] ?? scoped_virtual_id($branchCode, $leaderIdRaw)) : '';
            $groupIdRaw = trim((string) ($reportRow['group_id'] ?? ''));
            $groupId = $groupIdRaw !== '' ? ($groupIdMap[$groupIdRaw] ?? scoped_virtual_id($branchCode, $groupIdRaw)) : '';

            $absentIdsInput = $reportRow['absent_member_ids'] ?? [];
            if (!is_array($absentIdsInput)) {
                $absentIdsInput = [];
            }
            $absentIds = [];
            foreach ($absentIdsInput as $memberIdRaw) {
                $memberIdRaw = trim((string) $memberIdRaw);
                if ($memberIdRaw === '') {
                    continue;
                }
                $mappedMemberId = $personIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw);
                if ($mappedMemberId !== '') {
                    $absentIds[] = $mappedMemberId;
                }
            }
            $absentIds = array_values(array_unique($absentIds));

            $sharerIdsInput = $reportRow['meditation_sharer_ids'] ?? [];
            if (!is_array($sharerIdsInput)) {
                $sharerIdsInput = [];
            }
            $sharerIds = [];
            foreach ($sharerIdsInput as $memberIdRaw) {
                $memberIdRaw = trim((string) $memberIdRaw);
                if ($memberIdRaw === '') {
                    continue;
                }
                $mappedMemberId = $personIdMap[$memberIdRaw] ?? scoped_virtual_id($branchCode, $memberIdRaw);
                if ($mappedMemberId !== '') {
                    $sharerIds[] = $mappedMemberId;
                }
            }
            $sharerIds = array_values(array_unique($sharerIds));

            $leaderName = append_branch_suffix(trim((string) ($reportRow['leader_name'] ?? '')), $branchLabel);
            if ($leaderName === '' && $leaderId !== '' && isset($personNameById[$leaderId])) {
                $leaderName = (string) $personNameById[$leaderId];
            }

            $groupName = trim((string) ($reportRow['group_name'] ?? ''));
            if ($groupName === '' && $groupId !== '' && isset($groupNameById[$groupId])) {
                $groupName = (string) $groupNameById[$groupId];
            }
            if ($groupName === '') {
                $groupName = 'Kelompok';
            }
            $groupPrefix = '[' . $branchLabel . '] ';
            if (strpos($groupName, $groupPrefix) !== 0) {
                $groupName = $groupPrefix . $groupName;
            }

            $reportOut = $reportRow;
            $reportOut['id'] = $reportId;
            $reportOut['leader_id'] = $leaderId;
            $reportOut['group_id'] = $groupId;
            $reportOut['absent_member_ids'] = $absentIds;
            $reportOut['meditation_sharer_ids'] = $sharerIds;
            $reportOut['leader_name'] = $leaderName;
            $reportOut['group_name'] = $groupName;
            $absentMemberNames = [];
            if (isset($reportOut['absent_member_names']) && is_array($reportOut['absent_member_names'])) {
                $absentMemberNames = array_map(function ($name) use ($branchLabel) {
                    return append_branch_suffix((string) $name, $branchLabel);
                }, $reportOut['absent_member_names']);
            }
            foreach ($absentIds as $absentId) {
                if (isset($personNameById[$absentId])) {
                    $resolvedName = trim((string) $personNameById[$absentId]);
                    if ($resolvedName !== '') {
                        $absentMemberNames[] = $resolvedName;
                    }
                }
            }
            $reportOut['absent_member_names'] = array_values(array_unique(array_filter($absentMemberNames, function ($value) {
                return trim((string) $value) !== '';
            })));

            $meditationSharerNames = [];
            if (isset($reportOut['meditation_sharer_names']) && is_array($reportOut['meditation_sharer_names'])) {
                $meditationSharerNames = array_map(function ($name) use ($branchLabel) {
                    return append_branch_suffix((string) $name, $branchLabel);
                }, $reportOut['meditation_sharer_names']);
            }
            foreach ($sharerIds as $sharerId) {
                if (isset($personNameById[$sharerId])) {
                    $resolvedName = trim((string) $personNameById[$sharerId]);
                    if ($resolvedName !== '') {
                        $meditationSharerNames[] = $resolvedName;
                    }
                }
            }
            $reportOut['meditation_sharer_names'] = array_values(array_unique(array_filter($meditationSharerNames, function ($value) {
                return trim((string) $value) !== '';
            })));
            $reportOut['branch_code'] = $branchCode;
            $reportOut['branch_label'] = $branchLabel;
            $combinedReportsById[$reportId] = $reportOut;
        }
    }

    $combinedPeople = array_values($combinedPeopleById);
    usort($combinedPeople, function ($a, $b) {
        $nameCmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        if ($nameCmp !== 0) {
            return $nameCmp;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $combinedGroups = array_values($combinedGroupsById);
    usort($combinedGroups, function ($a, $b) {
        $leaderCmp = strcasecmp((string) ($a['leader_name'] ?? ''), (string) ($b['leader_name'] ?? ''));
        if ($leaderCmp !== 0) {
            return $leaderCmp;
        }
        $groupCmp = strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        if ($groupCmp !== 0) {
            return $groupCmp;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $combinedMembers = array_values($combinedMembersById);
    usort($combinedMembers, function ($a, $b) {
        $nameCmp = strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        if ($nameCmp !== 0) {
            return $nameCmp;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $combinedMskClasses = array_values($combinedMskById);
    usort($combinedMskClasses, function ($a, $b) {
        $monthCmp = strcmp((string) ($b['msk_month'] ?? ''), (string) ($a['msk_month'] ?? ''));
        if ($monthCmp !== 0) {
            return $monthCmp;
        }
        $nameCmp = strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
        if ($nameCmp !== 0) {
            return $nameCmp;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    $combinedReports = array_values($combinedReportsById);
    usort($combinedReports, function ($a, $b) {
        $dateCmp = strcmp((string) ($b['meeting_date'] ?? ''), (string) ($a['meeting_date'] ?? ''));
        if ($dateCmp !== 0) {
            return $dateCmp;
        }
        $createdCmp = strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        if ($createdCmp !== 0) {
            return $createdCmp;
        }
        return strcmp((string) ($a['id'] ?? ''), (string) ($b['id'] ?? ''));
    });

    return [
        'people' => $combinedPeople,
        'groups' => $combinedGroups,
        'dg_meeting_reports' => $combinedReports,
        'members' => $combinedMembers,
        'msk_classes' => $combinedMskClasses,
        PEOPLE_REGISTRY_DATA_NAME => [],
        'discipleship_v2_model' => $combinedDiscipleshipV2Model,
    ];
}
