<?php

if ($page === 'spiritual_journey') {
    $renderAsTabPanel = (bool) ($renderAsTabPanel ?? false);
    if (! $renderAsTabPanel) {
        page_header('Spiritual Journey', $settings, $page, false, 'page-discipleship-table-scroll');
    } else {
        echo '<section class="discipleship-tab-panel discipleship-workspace__panel discipleship-list-panel journey-workspace-panel spiritual-journey-panel" id="discipleship-tabpanel-spiritual" role="tabpanel" aria-labelledby="discipleship-tab-spiritual" tabindex="0" data-discipleship-tab-panel data-tab-key="spiritual" data-page-title="Spiritual Journey" data-body-class="page-spiritual_journey" data-spiritual-detail-url-template="'.h(route('discipleship.spiritual-journey.detail', ['participant' => '__id__'])).'">'."\n";
    }
    $peopleByMemberId = [];
    $peopleByName = [];
    foreach ($people as $personRow) {
        if (! is_array($personRow)) {
            continue;
        }
        $personId = trim((string) ($personRow['id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $personMemberId = trim((string) ($personRow['member_id'] ?? ''));
        if ($personMemberId !== '' && ! isset($peopleByMemberId[$personMemberId])) {
            $peopleByMemberId[$personMemberId] = $personId;
        }
        $personNameKey = strtolower(trim((string) ($personRow['name'] ?? '')));
        if ($personNameKey !== '' && ! isset($peopleByName[$personNameKey])) {
            $peopleByName[$personNameKey] = $personId;
        }
    }
    $discipleshipPersonsById = [];
    foreach (($discipleshipV2Model['discipleship_persons'] ?? []) as $personRecord) {
        if (! is_array($personRecord)) {
            continue;
        }
        $personId = trim((string) ($personRecord['id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $discipleshipPersonsById[$personId] = $personRecord;
    }

    $allGroupsById = [];
    foreach (($discipleshipV2Model['discipleship_groups'] ?? []) as $groupRecord) {
        if (! is_array($groupRecord)) {
            continue;
        }
        $groupId = trim((string) ($groupRecord['id'] ?? ''));
        if ($groupId === '') {
            continue;
        }
        $allGroupsById[$groupId] = $groupRecord;
    }

    $membershipsByPersonId = [];
    $membershipsByGroupId = [];
    foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRecord) {
        if (! is_array($membershipRecord)) {
            continue;
        }
        $personId = trim((string) ($membershipRecord['person_id'] ?? ''));
        $groupId = trim((string) ($membershipRecord['group_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $membershipsByPersonId[$personId][] = $membershipRecord;
        if ($groupId !== '') {
            $membershipsByGroupId[$groupId][] = $membershipRecord;
        }
    }

    $leadershipsByPersonId = [];
    $leadershipsByGroupId = [];
    foreach (($discipleshipV2Model['group_leaderships'] ?? []) as $leadershipRecord) {
        if (! is_array($leadershipRecord)) {
            continue;
        }
        $personId = trim((string) ($leadershipRecord['leader_person_id'] ?? ''));
        $groupId = trim((string) ($leadershipRecord['group_id'] ?? ''));
        if ($personId !== '') {
            $leadershipsByPersonId[$personId][] = $leadershipRecord;
        }
        if ($groupId !== '') {
            $leadershipsByGroupId[$groupId][] = $leadershipRecord;
        }
    }

    $historyTextLabel = static function (string $value, string $fallback = '-'): string {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }
        $normalized = strtolower(str_replace(['-', ' '], '_', $value));
        $labelMap = [
            'member' => 'Anggota',
            'anggota' => 'Anggota',
            'leader' => 'Pemimpin',
            'co_leader' => 'Pendamping',
            'pendamping' => 'Pendamping',
            'continued_to_child_group' => 'Naik ke kelompok lanjutan',
            'group_completed' => 'Kelompok selesai',
            'group_archived' => 'Kelompok diarsipkan',
            'stage_transition' => 'Transisi tahap',
            'removed_from_group' => 'Dikeluarkan dari kelompok',
            'left_group' => 'Keluar dari kelompok',
            'continued_to_child' => 'Naik ke kelompok lanjutan',
            'group_completed.' => 'Kelompok selesai',
            'group_completed_' => 'Kelompok selesai',
            'manual_completion' => 'Ditandai selesai manual',
        ];
        if (isset($labelMap[$normalized])) {
            return $labelMap[$normalized];
        }

        return ucwords(str_replace('_', ' ', $value));
    };
    $historyUpgradeNoteLabel = static function (string $reason, string $stage): string {
        $reason = trim($reason);
        $stage = normalize_dg_progress_value($stage);
        if ($reason === 'continued_to_child_group') {
            return 'Kelompok selesai';
        }
        if ($reason === 'group_completed') {
            return 'Kelompok selesai';
        }
        if ($reason === 'group_archived') {
            return 'Kelompok diarsipkan';
        }
        if ($reason === 'left_group') {
            return 'Keluar dari kelompok';
        }
        if ($reason === 'removed_from_group') {
            return 'Dikeluarkan dari kelompok';
        }
        if ($reason === 'stage_transition') {
            return 'Transisi tahap';
        }
        if ($reason === 'manual_completion') {
            return 'Ditandai selesai tanpa kelompok/pemimpin';
        }

        return $reason;
    };
    $historyDateLabel = static function (string $startDate, string $endDate): string {
        $startDate = normalize_ymd_date($startDate);
        $endDate = normalize_ymd_date($endDate);
        if ($startDate === '' && $endDate === '') {
            return '-';
        }
        $startLabel = $startDate !== '' ? format_indo_date($startDate) : '-';
        $endLabel = $endDate !== '' ? format_indo_date($endDate) : 'Sekarang';
        if ($startDate !== '' && $endDate !== '' && $startDate === $endDate) {
            return $startLabel;
        }

        return $startLabel.' - '.$endLabel;
    };
    $journeyStageBadgeHtml = static function (string $stage): string {
        $stage = normalize_dg_progress_value($stage);
        if ($stage === '') {
            return '';
        }
        $badgeClass = 'journey-track-badge is-muted';
        if ($stage === 'DG 1') {
            $badgeClass = 'journey-track-badge is-dg1';
        } elseif ($stage === 'DG 2') {
            $badgeClass = 'journey-track-badge is-dg2';
        } elseif ($stage === 'DG 3') {
            $badgeClass = 'journey-track-badge is-dg3';
        }

        return '<span class="'.h($badgeClass).'">'.h($stage).'</span>';
    };
    $renderJourneyHistoryHtml = static function (
        array $participant,
        string $resolvedPersonId,
        string $activeDgProgress,
        array $peopleById,
        array $discipleshipPersonsById,
        array $allGroupsById,
        array $membershipsByPersonId,
        array $membershipsByGroupId,
        array $leadershipsByGroupId,
        array $leadershipsByPersonId,
        array $relationsByDiscipleId,
        Closure $historyTextLabel,
        Closure $historyUpgradeNoteLabel,
        Closure $historyDateLabel,
        Closure $journeyStageBadgeHtml
    ): string {
        $participantName = trim((string) ($participant['full_name'] ?? ''));
        if ($participantName === '') {
            $participantName = '-';
        }
        $participantMemberId = trim((string) ($participant['member_id'] ?? ''));
        $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        $sessionCount = count($sessionNumbers);
        if ($sessionCount > 12) {
            $sessionCount = 12;
        }
        $mskProgress = $sessionCount > 0 ? ((string) $sessionCount.'/12') : '-';
        $mskBadgeClass = $sessionCount >= 12
            ? 'journey-track-badge is-msk is-msk-done'
            : 'journey-track-badge is-msk is-msk-progress';
        $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participant['journey_bridge_status'] ?? 'belum'));
        $bridgeLabels = [
            'belum' => 'Belum RG/KGAP',
            'sudah_rg' => 'Sudah RG',
            'sudah_kgap' => 'Sudah KGAP',
            'ikut_keduanya' => 'Sudah RG + KGAP',
        ];
        $bridgeBadgeClass = 'journey-track-badge is-muted';
        if ($journeyBridgeStatus === 'sudah_rg') {
            $bridgeBadgeClass = 'journey-track-badge is-dg1';
        } elseif (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
            $bridgeBadgeClass = 'journey-track-badge is-kgap';
        }

        $summaryBadges = [];
        $summaryBadges[] = '<span class="'.h($mskBadgeClass).'">MSK '.h($mskProgress).'</span>';
        if ($activeDgProgress !== '') {
            $summaryBadges[] = $journeyStageBadgeHtml($activeDgProgress);
        }
        $summaryBadges[] = '<span class="'.h($bridgeBadgeClass).'">'.h((string) ($bridgeLabels[$journeyBridgeStatus] ?? 'Belum RG/KGAP')).'</span>';

        $currentGroupLeaderNames = [];
        $currentGroupNames = [];
        $membershipTimelineItems = [];
        $leadershipTimelineItems = [];
        $stageRank = static function (string $stage): int {
            $stage = normalize_dg_progress_value($stage);
            if ($stage === 'DG 3') {
                return 3;
            }
            if ($stage === 'DG 2') {
                return 2;
            }
            if ($stage === 'DG 1') {
                return 1;
            }

            return 0;
        };
        $timelineMembershipRows = static function (array $records): array {
            $isGroupMembership = static function (array $record): bool {
                $groupId = trim((string) ($record['group_id'] ?? ''));
                $personId = trim((string) ($record['person_id'] ?? ''));

                return trim((string) ($record['source'] ?? '')) !== 'manual'
                    && strtolower(trim((string) ($record['role'] ?? ''))) === 'member'
                    && $groupId !== ''
                    && $groupId !== 'virtual_root_group'
                    && $personId !== '';
            };
            $groupKey = static fn (array $record): string => trim((string) ($record['person_id'] ?? '')).'|'.trim((string) ($record['group_id'] ?? ''));
            $compareRecency = static function (array $left, array $right): int {
                foreach (['start_date', 'updated_at', 'id'] as $field) {
                    if ($field === 'id') {
                        $comparison = (int) ($left[$field] ?? 0) <=> (int) ($right[$field] ?? 0);
                    } else {
                        $comparison = strcmp(trim((string) ($left[$field] ?? '')), trim((string) ($right[$field] ?? '')));
                    }
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }

                return 0;
            };

            $activeByGroup = [];
            foreach ($records as $record) {
                if (! is_array($record) || ! $isGroupMembership($record) || ! dgv2_is_current_period($record)) {
                    continue;
                }
                $key = $groupKey($record);
                if (! isset($activeByGroup[$key]) || $compareRecency($record, $activeByGroup[$key]) > 0) {
                    $activeByGroup[$key] = $record;
                }
            }
            if ($activeByGroup === []) {
                return $records;
            }

            return array_values(array_filter($records, static function ($record) use ($isGroupMembership, $groupKey, $activeByGroup): bool {
                if (! is_array($record) || ! $isGroupMembership($record)) {
                    return true;
                }
                $activeRecord = $activeByGroup[$groupKey($record)] ?? null;
                if (! is_array($activeRecord)) {
                    return true;
                }

                return trim((string) ($record['id'] ?? '')) === trim((string) ($activeRecord['id'] ?? ''));
            }));
        };

        if ($resolvedPersonId !== '') {
            foreach ($timelineMembershipRows($membershipsByPersonId[$resolvedPersonId] ?? []) as $membership) {
                $isManual = trim((string) ($membership['source'] ?? '')) === 'manual';
                $groupId = trim((string) ($membership['group_id'] ?? ''));
                if ($groupId === 'virtual_root_group') {
                    continue;
                }
                $groupName = trim((string) ($allGroupsById[$groupId]['name'] ?? ''));
                if ($groupName === '') {
                    $groupName = 'Kelompok';
                }
                $stage = normalize_dg_progress_value((string) ($membership['stage'] ?? ''));
                $role = trim((string) ($membership['role'] ?? 'anggota'));
                $isActive = dgv2_is_current_period($membership);
                $meta = [];
                if ($stage !== '') {
                    $meta[] = $journeyStageBadgeHtml($stage);
                }
                $meta[] = '<span class="journey-history-chip">'.h($isManual ? 'Manual' : $historyTextLabel($role)).'</span>';

                $groupLeaderName = '';
                if (! $isManual && isset($leadershipsByGroupId[$groupId])) {
                    $groupLeaderships = $leadershipsByGroupId[$groupId];
                    usort($groupLeaderships, static function (array $a, array $b): int {
                        $aActive = dgv2_is_current_period($a) ? 1 : 0;
                        $bActive = dgv2_is_current_period($b) ? 1 : 0;
                        if ($aActive !== $bActive) {
                            return $bActive <=> $aActive;
                        }

                        return strcmp(trim((string) ($b['updated_at'] ?? '')), trim((string) ($a['updated_at'] ?? '')));
                    });
                    $lPersonId = trim((string) ($groupLeaderships[0]['leader_person_id'] ?? ''));
                    if ($lPersonId !== '') {
                        $groupLeaderName = person_label($peopleById, $lPersonId, trim((string) ($discipleshipPersonsById[$lPersonId]['full_name'] ?? '')));
                    }
                }
                if ($groupLeaderName !== '' && $groupLeaderName !== '-') {
                    if ($isActive) {
                        $currentGroupLeaderNames[] = $groupLeaderName;
                    }
                    $groupName = discipleship_group_display_label([
                        'progress' => $stage,
                        'leader_name' => $groupLeaderName,
                    ]);
                    $meta[] = '<span class="journey-history-chip">Leader kelompok: '.h($groupLeaderName).'</span>';
                }

                if (! $isManual && $isActive && ! in_array($groupName, $currentGroupNames, true)) {
                    $currentGroupNames[] = $groupName;
                }

                if ($isActive) {
                    $meta[] = '<span class="journey-history-chip is-active">Aktif</span>';
                }
                $membershipTimelineItems[] = [
                    'type' => 'membership',
                    'is_active' => $isActive ? 1 : 0,
                    'stage_rank' => $stageRank($stage),
                    'sort_date' => trim((string) ($membership['end_date'] ?? $membership['start_date'] ?? $membership['created_at'] ?? '')),
                    'title' => $isManual ? ('Selesai'.($stage !== '' ? ' '.$stage : ' DG').' manual') : ('Masuk Kelompok'.($stage !== '' ? ' '.$stage : '')),
                    'date' => $historyDateLabel((string) ($membership['start_date'] ?? ''), (string) ($membership['end_date'] ?? '')),
                    'meta' => implode('', $meta),
                    'description' => $historyUpgradeNoteLabel((string) ($membership['reason_end'] ?? ''), $stage),
                ];
            }

            foreach (($leadershipsByPersonId[$resolvedPersonId] ?? []) as $leadership) {
                $groupId = trim((string) ($leadership['group_id'] ?? ''));
                $groupName = trim((string) ($allGroupsById[$groupId]['name'] ?? ''));
                if ($groupName === '') {
                    $groupName = 'Kelompok';
                }
                $groupStage = discipleship_group_stage_value($allGroupsById[$groupId] ?? []);
                if ($groupStage === '') {
                    $groupStage = normalize_dg_progress_value((string) ($allGroupsById[$groupId]['progress'] ?? ''));
                }
                if ($groupStage === '') {
                    $groupStage = 'Kelompok';
                }
                $role = trim((string) ($leadership['role'] ?? 'leader'));
                $isActive = dgv2_is_current_period($leadership);
                $leadershipStart = normalize_ymd_date((string) ($leadership['start_date'] ?? ''));
                $leadershipEnd = normalize_ymd_date((string) ($leadership['end_date'] ?? ''));
                $memberLabels = [];
                foreach (($membershipsByGroupId[$groupId] ?? []) as $membership) {
                    $memberPersonId = trim((string) ($membership['person_id'] ?? ''));
                    if ($memberPersonId === '' || $memberPersonId === $resolvedPersonId) {
                        continue;
                    }
                    $membershipStart = normalize_ymd_date((string) ($membership['start_date'] ?? ''));
                    $membershipEnd = normalize_ymd_date((string) ($membership['end_date'] ?? ''));
                    $overlapsLeadership = true;
                    if ($leadershipEnd !== '' && $membershipStart !== '' && strcmp($membershipStart, $leadershipEnd) > 0) {
                        $overlapsLeadership = false;
                    }
                    if ($leadershipStart !== '' && $membershipEnd !== '' && strcmp($membershipEnd, $leadershipStart) < 0) {
                        $overlapsLeadership = false;
                    }
                    if (! $overlapsLeadership) {
                        continue;
                    }
                    $memberLabel = person_label($peopleById, $memberPersonId, trim((string) ($discipleshipPersonsById[$memberPersonId]['full_name'] ?? '')));
                    if ($memberLabel === '' || $memberLabel === '-') {
                        continue;
                    }
                    $memberLabels[] = $memberLabel;
                }
                $memberLabels = array_values(array_unique($memberLabels));
                $meta = [
                    '<span class="journey-history-chip">'.h($historyTextLabel($role)).'</span>',
                ];
                if ($isActive) {
                    $meta[] = '<span class="journey-history-chip is-active">Aktif</span>';
                }
                $leadershipTimelineItems[] = [
                    'type' => 'leadership',
                    'is_active' => $isActive ? 1 : 0,
                    'stage_rank' => $stageRank($groupStage),
                    'sort_date' => trim((string) ($leadership['end_date'] ?? $leadership['start_date'] ?? $leadership['created_at'] ?? '')),
                    'title' => 'Memimpin kelompok '.$groupStage,
                    'date' => $historyDateLabel((string) ($leadership['start_date'] ?? ''), (string) ($leadership['end_date'] ?? '')),
                    'meta' => implode('', $meta),
                    'description' => $historyUpgradeNoteLabel((string) ($leadership['reason_change'] ?? ''), $groupStage),
                    'members' => count($memberLabels) > 0 ? ('Anggota: '.implode(', ', $memberLabels)) : '',
                ];
            }
        }

        $currentGroupLeaderNames = array_values(array_unique(array_filter($currentGroupLeaderNames, static fn ($value) => trim((string) $value) !== '')));
        $currentGroupNames = array_values(array_unique(array_filter($currentGroupNames, static fn ($value) => trim((string) $value) !== '')));

        usort($membershipTimelineItems, static function (array $a, array $b): int {
            $stageCompare = ((int) ($b['stage_rank'] ?? 0)) <=> ((int) ($a['stage_rank'] ?? 0));
            if ($stageCompare !== 0) {
                return $stageCompare;
            }
            $activeCompare = ((int) ($b['is_active'] ?? 0)) <=> ((int) ($a['is_active'] ?? 0));
            if ($activeCompare !== 0) {
                return $activeCompare;
            }
            $dateA = trim((string) ($a['sort_date'] ?? ''));
            $dateB = trim((string) ($b['sort_date'] ?? ''));
            if ($dateA !== $dateB) {
                return strcmp($dateB, $dateA);
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        usort($leadershipTimelineItems, static function (array $a, array $b): int {
            $stageCompare = ((int) ($b['stage_rank'] ?? 0)) <=> ((int) ($a['stage_rank'] ?? 0));
            if ($stageCompare !== 0) {
                return $stageCompare;
            }
            $activeCompare = ((int) ($b['is_active'] ?? 0)) <=> ((int) ($a['is_active'] ?? 0));
            if ($activeCompare !== 0) {
                return $activeCompare;
            }
            $dateA = trim((string) ($a['sort_date'] ?? ''));
            $dateB = trim((string) ($b['sort_date'] ?? ''));
            if ($dateA !== $dateB) {
                return strcmp($dateB, $dateA);
            }

            return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });

        $renderTimelineItems = static function (array $items, Closure $historyTextLabel): string {
            if (count($items) === 0) {
                return '';
            }
            ob_start();
            echo '<div class="journey-history-timeline">';
            foreach ($items as $item) {
                $description = trim((string) ($item['description'] ?? ''));
                $membersNote = trim((string) ($item['members'] ?? ''));
                echo '<article class="journey-history-item">';
                echo '<div class="journey-history-item-head"><div class="journey-history-item-title">'.h((string) ($item['title'] ?? '-')).'</div><div class="journey-history-item-date">'.h((string) ($item['date'] ?? '-')).'</div></div>';
                if (trim((string) ($item['meta'] ?? '')) !== '') {
                    echo '<div class="journey-history-item-meta">'.(string) ($item['meta'] ?? '').'</div>';
                }
                if ($membersNote !== '') {
                    echo '<div class="journey-history-item-members">'.h($membersNote).'</div>';
                }
                if ($description !== '') {
                    echo '<div class="journey-history-item-note">Catatan: '.h($historyTextLabel($description)).'</div>';
                }
                echo '</article>';
            }
            echo '</div>';

            return (string) ob_get_clean();
        };

        ob_start();
        echo '<div class="journey-history-view">';
        echo '<div class="journey-history-summary">';
        echo '<div class="journey-history-summary-main">';
        echo '<div class="journey-history-summary-name">'.h($participantName).'</div>';
        echo '<div class="journey-history-summary-sub">Member ID: '.h($participantMemberId !== '' ? $participantMemberId : '-').'</div>';
        echo '</div>';
        echo '<div class="journey-history-summary-badges">'.implode('', $summaryBadges).'</div>';
        echo '</div>';

        echo '<div class="journey-history-facts">';
        echo '<div class="journey-history-fact"><span class="journey-history-fact-label">Sesi MSK</span><strong>'.h($sessionCount > 0 ? implode(', ', array_map('strval', $sessionNumbers)) : 'Belum ada sesi').'</strong></div>';
        echo '<div class="journey-history-fact"><span class="journey-history-fact-label">Leader Kelompok Aktif</span><strong>'.h(count($currentGroupLeaderNames) > 0 ? implode(', ', $currentGroupLeaderNames) : '-').'</strong></div>';
        echo '<div class="journey-history-fact"><span class="journey-history-fact-label">Kelompok Aktif</span><strong>'.h(count($currentGroupNames) > 0 ? implode(', ', $currentGroupNames) : '-').'</strong></div>';
        echo '</div>';

        echo '<div class="journey-history-section-title">Riwayat Pemuridan</div>';
        if ($resolvedPersonId === '') {
            echo '<div class="journey-history-empty">Peserta ini belum terhubung ke data pemuridan, jadi yang tampil baru progres MSK. Hubungkan peserta ke data DG untuk melihat perpindahan kelompok dan peran pelayanan.</div>';
        } elseif (count($membershipTimelineItems) === 0 && count($leadershipTimelineItems) === 0) {
            echo '<div class="journey-history-empty">Belum ada histori kelompok yang tercatat untuk orang ini.</div>';
        } else {
            echo '<div class="journey-history-split-section">';
            echo '<div class="journey-history-split-header">Riwayat Sebagai Anggota</div>';
            if (count($membershipTimelineItems) === 0) {
                echo '<div class="journey-history-empty">Belum ada riwayat sebagai anggota.</div>';
            } else {
                echo $renderTimelineItems($membershipTimelineItems, $historyTextLabel);
            }
            echo '</div>';
            echo '<div class="journey-history-split-divider"></div>';
            echo '<div class="journey-history-split-section">';
            echo '<div class="journey-history-split-header">Riwayat Memimpin</div>';
            if (count($leadershipTimelineItems) === 0) {
                echo '<div class="journey-history-empty">Belum ada riwayat memimpin kelompok.</div>';
            } else {
                echo $renderTimelineItems($leadershipTimelineItems, $historyTextLabel);
            }
            echo '</div>';
        }
        echo '</div>';

        return (string) ob_get_clean();
    };

    $journeyStageRank = static function (string $stage): int {
        $stage = normalize_dg_progress_value($stage);
        if ($stage === 'DG 3') {
            return 3;
        }
        if ($stage === 'DG 2') {
            return 2;
        }
        if ($stage === 'DG 1') {
            return 1;
        }

        return 0;
    };
    $personCompletedDg1Map = [];
    $personCompletedDg2Map = [];
    $personCompletedDg3Map = [];
    $personJourneyMap = [];
    foreach (($discipleshipV2Model['group_memberships'] ?? []) as $membershipRecord) {
        if (! is_array($membershipRecord)) {
            continue;
        }
        $personId = trim((string) ($membershipRecord['person_id'] ?? ''));
        if ($personId === '') {
            continue;
        }
        $stage = normalize_dg_progress_value((string) ($membershipRecord['stage'] ?? ''));
        if ($stage === '') {
            continue;
        }
        $stageRank = $journeyStageRank($stage);
        $reasonEnd = trim((string) ($membershipRecord['reason_end'] ?? ''));
        if (
            $stageRank >= 2
            || ($stage === 'DG 1' && in_array($reasonEnd, ['continued_to_child_group', 'group_completed', 'stage_transition', 'manual_completion'], true))
        ) {
            $personCompletedDg1Map[$personId] = true;
        }
        if (
            $stageRank >= 3
            || ($stage === 'DG 2' && in_array($reasonEnd, ['continued_to_child_group', 'group_completed', 'stage_transition', 'manual_completion'], true))
        ) {
            $personCompletedDg2Map[$personId] = true;
        }
        if (
            $stage === 'DG 3'
            && in_array($reasonEnd, ['group_completed', 'continued_to_child_group', 'stage_transition', 'manual_completion'], true)
        ) {
            $personCompletedDg3Map[$personId] = true;
        }
        $sortDate = trim((string) ($membershipRecord['end_date'] ?? ''));
        if ($sortDate === '') {
            $sortDate = trim((string) ($membershipRecord['start_date'] ?? ''));
        }
        if ($sortDate === '') {
            $sortDate = trim((string) ($membershipRecord['updated_at'] ?? $membershipRecord['created_at'] ?? ''));
        }
        $existing = $personJourneyMap[$personId] ?? null;
        if (! is_array($existing)) {
            $personJourneyMap[$personId] = [
                'progress' => $stage,
                'sort_date' => $sortDate,
                'stage_rank' => $stageRank,
            ];

            continue;
        }
        $existingSortDate = trim((string) ($existing['sort_date'] ?? ''));
        $replaceExisting = false;
        if ($sortDate !== '' && ($existingSortDate === '' || strcmp($sortDate, $existingSortDate) > 0)) {
            $replaceExisting = true;
        } elseif ($sortDate === $existingSortDate && $stageRank > (int) ($existing['stage_rank'] ?? 0)) {
            $replaceExisting = true;
        }
        if ($replaceExisting) {
            $personJourneyMap[$personId] = [
                'progress' => $stage,
                'sort_date' => $sortDate,
                'stage_rank' => $stageRank,
            ];
        }
    }

    $rows = [];
    $journeyViewTemplates = [];
    $completedMskCount = 0;
    $completedDg1Count = 0;
    $followingKgapCount = 0;
    $completedDg2Count = 0;
    $completedDg3Count = 0;
    foreach ($mskClasses as $participant) {
        if (! is_array($participant)) {
            continue;
        }
        $fullName = trim((string) ($participant['full_name'] ?? ''));
        if ($fullName === '') {
            continue;
        }
        $participantMemberId = trim((string) ($participant['member_id'] ?? ''));
        $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        $sessionCount = count($sessionNumbers);
        if ($sessionCount > 12) {
            $sessionCount = 12;
        }
        $mskProgress = $sessionCount > 0 ? ((string) $sessionCount.'/12') : '-';
        $mskPercent = (int) round(($sessionCount / 12) * 100);
        $sessionLabel = $sessionCount > 0 ? 'Sesi '.implode(', ', array_map('strval', $sessionNumbers)) : 'Belum ada sesi';
        if ($sessionCount >= 12) {
            $completedMskCount++;
        }
        $resolvedPersonId = '';
        if ($participantMemberId !== '' && isset($peopleByMemberId[$participantMemberId])) {
            $resolvedPersonId = (string) $peopleByMemberId[$participantMemberId];
        } else {
            $participantNameKey = strtolower($fullName);
            if ($participantNameKey !== '' && isset($peopleByName[$participantNameKey])) {
                $resolvedPersonId = (string) $peopleByName[$participantNameKey];
            }
        }
        $activeDgProgress = '';
        $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participant['journey_bridge_status'] ?? 'belum'));
        if (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
            $followingKgapCount++;
        }
        $journeyViewKey = trim((string) ($participant['id'] ?? ''));
        if ($journeyViewKey === '') {
            $journeyViewKey = 'spiritual-journey-'.(string) (count($rows) + 1);
        }
        $rows[] = [
            'id' => (string) ($participant['id'] ?? ''),
            'name' => $fullName,
            'search_text' => trim($fullName.' '.(string) ($participant['whatsapp'] ?? '')),
            'msk_progress' => $mskProgress,
            'session_count' => $sessionCount,
            'msk_percent' => $mskPercent,
            'session_label' => $sessionLabel,
            'active_dg_progress' => $activeDgProgress,
            'completed_dg1' => ! empty($personCompletedDg1Map[$resolvedPersonId]),
            'completed_dg2' => ! empty($personCompletedDg2Map[$resolvedPersonId]),
            'completed_dg3' => ! empty($personCompletedDg3Map[$resolvedPersonId]),
            'journey_bridge_status' => $journeyBridgeStatus,
            'journey_view_key' => $journeyViewKey,
        ];
    }
    usort($rows, function (array $a, array $b): int {
        $sessionA = (int) ($a['session_count'] ?? 0);
        $sessionB = (int) ($b['session_count'] ?? 0);
        if ($sessionA !== $sessionB) {
            return $sessionB <=> $sessionA;
        }
        $nameA = strtolower((string) ($a['name'] ?? ''));
        $nameB = strtolower((string) ($b['name'] ?? ''));
        if ($nameA !== $nameB) {
            return $nameA <=> $nameB;
        }

        return strcmp((string) ($a['msk_progress'] ?? ''), (string) ($b['msk_progress'] ?? ''));
    });

    $totalJourneyRows = $spiritualJourneyTotalParticipants;
    $journeyTargetKgap = max(0, (int) ($discipleshipTargets['dg_total_people'] ?? 0));
    $journeyTargetDg1 = max(0, (int) ($discipleshipTargets['dg1_people'] ?? 0));
    $journeyTargetDg2 = max(0, (int) ($discipleshipTargets['dg2_people'] ?? 0));
    $journeyTargetDg3 = max(0, (int) ($discipleshipTargets['dg3_people'] ?? 0));
    $completedDg1Count = 0;
    $completedDg2Count = 0;
    $completedDg3Count = 0;
    foreach ($rows as $journeyRow) {
        if (! is_array($journeyRow)) {
            continue;
        }
        if (! empty($journeyRow['completed_dg1'])) {
            $completedDg1Count++;
        }
        if (! empty($journeyRow['completed_dg2'])) {
            $completedDg2Count++;
        }
        if (! empty($journeyRow['completed_dg3'])) {
            $completedDg3Count++;
        }
    }
    $journeyProgressRows = [
        ['label' => 'Selesai DG 1', 'value' => $completedDg1Count, 'target' => $journeyTargetDg1, 'color' => discipleship_stage_color('DG 1')],
        ['label' => 'Selesai Kamp GAP', 'value' => $followingKgapCount, 'target' => $journeyTargetKgap, 'color' => '#0ea5e9'],
        ['label' => 'Selesai DG 2', 'value' => $completedDg2Count, 'target' => $journeyTargetDg2, 'color' => discipleship_stage_color('DG 2')],
        ['label' => 'Selesai DG 3', 'value' => $completedDg3Count, 'target' => $journeyTargetDg3, 'color' => discipleship_stage_color('DG 3')],
    ];
    $journeyStats = is_array($spiritualJourneyStats ?? null) ? $spiritualJourneyStats : [];
    $journeyProgressRows[0]['value'] = (int) ($journeyStats['completed_dg1'] ?? $journeyProgressRows[0]['value']);
    $journeyProgressRows[1]['value'] = (int) ($journeyStats['following_kgap'] ?? $journeyProgressRows[1]['value']);
    $journeyProgressRows[2]['value'] = (int) ($journeyStats['completed_dg2'] ?? $journeyProgressRows[2]['value']);
    $journeyProgressRows[3]['value'] = (int) ($journeyStats['completed_dg3'] ?? $journeyProgressRows[3]['value']);

    $journeyFilter = trim((string) ($spiritualJourneyFilter ?? 'all'));
    $journeyFilterOptions = [
        'all' => 'Semua Peserta',
        'dg_without_kgap' => 'Minimal DG 1, Belum Kamp GAP',
    ];
    $spiritualJourneyFilterCounts = is_array($spiritualJourneyFilterCounts ?? null) ? $spiritualJourneyFilterCounts : [];
    $journeyHeaderStats = [];
    $journeyHeaderStatKeys = ['dg1', 'kgap', 'dg2', 'dg3'];
    foreach ($journeyProgressRows as $statIndex => $row) {
        $journeyHeaderStats[] = [
            'label' => (string) ($row['label'] ?? '-'),
            'value' => (string) max(0, (int) ($row['value'] ?? 0)),
            'value_attributes' => ['data-spiritual-journey-stat' => $journeyHeaderStatKeys[$statIndex] ?? ''],
        ];
    }
    echo view('discipleship.partials.page-header', [
        'header' => [
            'tools' => [
                'element' => 'form',
                'method' => 'get',
                'action' => route('discipleship.spiritual-journey'),
                'attributes' => ['data-spiritual-journey-search-form' => true],
                'partial' => 'discipleship.partials.page-header-controls.spiritual-journey',
                'data' => compact(
                    'journeyFilter',
                    'journeyFilterOptions',
                    'spiritualJourneyFilterCounts',
                    'spiritualJourneySearch',
                ),
            ],
        ],
    ])->render();

    $rows = is_array($spiritualJourneyRows ?? null) ? $spiritualJourneyRows : $rows;
    echo '<section class="card table-card-plain" data-spiritual-journey-list data-rows-url="'.h(route('discipleship.spiritual-journey.rows')).'" data-limit="'.h((string) ($spiritualJourneyLimit ?? 50)).'" data-has-more="'.(! empty($hasMoreSpiritualJourneyRows) ? '1' : '0').'" data-next-cursor="'.h((string) ($nextSpiritualJourneyCursor ?? '')).'">'."\n";
    echo "  <div class=\"table-wrap\" data-spiritual-journey-scroll>\n";
    echo "    <table class=\"table journey-dashboard-table\" id=\"spiritual-journey-table\">\n";
    echo "      <colgroup>\n";
    echo "        <col class=\"journey-col-name\">\n";
    echo "        <col class=\"journey-col-track\">\n";
    echo "      </colgroup>\n";
    echo "      <thead><tr><th>Nama Peserta</th><th>Spiritual Journey</th></tr></thead>\n";
    echo "      <tbody data-spiritual-journey-list-body>\n";
    echo view('discipleship.spiritual-journey.partials.rows', ['rows' => $rows])->render();
    echo '<tr data-spiritual-journey-search-empty '.(count($rows) !== 0 ? 'hidden' : '').'><td colspan="2" aria-live="polite">'.h((string) ($spiritualJourneyEmptyMessage ?? 'Peserta tidak ditemukan.'))."</td></tr>\n";
    echo "<tr data-spiritual-journey-loading hidden><td colspan=\"2\" aria-live=\"polite\">Memuat peserta...</td></tr>\n";
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo view('partials.modal', [
        'id' => 'spiritual-journey-view-modal',
        'size' => 'standard',
        'modalAttrs' => ['data-spiritual-journey-view-modal' => true],
        'cardClass' => 'member-view-modal-card msk-view-modal-card',
        'title' => 'Profil Peserta',
        'titleAttrs' => ['data-spiritual-journey-view-title' => true],
        'closeAttrs' => ['data-spiritual-journey-view-close' => true],
        'bodyAttrs' => ['data-spiritual-journey-view-body' => true],
        'bodyHtml' => '<div class="panel-note">Klik Lihat profil pada tabel untuk membuka profil peserta.</div>',
        'footerHtml' => '<button class="btn ghost" type="button" data-spiritual-journey-view-close>Tutup</button>',
    ])->render();
    if ($renderAsTabPanel) {
        echo "</section>\n";
    } else {
        page_footer();
    }
}
