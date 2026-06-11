<?php

if ($page === 'dashboard') {
    page_header('Dashboard', $settings, $page, false, 'page-dashboard');

    $totalPeople = count($people);
    $totalMembersData = count($members);
    $totalCashEntries = count($cashSmallEntries);
    $cashBalance = 0;
    foreach ($cashSmallEntries as $entry) {
        $amountIn = max(0, (int) ($entry['amount_in'] ?? 0));
        $amountOut = max(0, (int) ($entry['amount_out'] ?? 0));
        $cashBalance += ($amountIn - $amountOut);
    }
    $totalGroups = count($groups);
    $totalDgReports = count($dgMeetingReports);
    $totalMskParticipants = count($mskClasses);
    $activeMembers = filter_active_members($members);
    $totalActiveMembers = count($activeMembers);
    $totalArchivedMembers = max(0, $totalMembersData - $totalActiveMembers);
    $currentMonthValue = date('Y-m');
    $cashEntriesCurrentMonth = 0;
    foreach ($cashSmallEntries as $entry) {
        $entryDate = normalize_ymd_date((string) ($entry['entry_date'] ?? ''));
        if ($entryDate !== '' && substr($entryDate, 0, 7) === $currentMonthValue) {
            $cashEntriesCurrentMonth++;
        }
    }
    // Ringkasan progres DG & MSK (mengacu data Spiritual Journey)
    $targetMskCompleted = max(0, (int) ($discipleshipTargets['msk_completed'] ?? 0));
    $targetKgap = max(0, (int) ($discipleshipTargets['dg_total_people'] ?? 0));
    $targetDg1 = max(0, (int) ($discipleshipTargets['dg1_people'] ?? 0));
    $targetDg2 = max(0, (int) ($discipleshipTargets['dg2_people'] ?? 0));
    $targetDg3 = max(0, (int) ($discipleshipTargets['dg3_people'] ?? 0));

    $stageCounts = ['DG 1' => 0, 'DG 2' => 0, 'DG 3' => 0];
    foreach ($people as $personRow) {
        if (!is_array($personRow)) {
            continue;
        }
        $progress = normalize_dg_progress_value((string) ($personRow['progress'] ?? ''));
        if (isset($stageCounts[$progress])) {
            $stageCounts[$progress]++;
        }
    }
    $summaryCompletedMsk = 0;
    $summaryFollowingKgap = 0;
    foreach ($mskClasses as $participant) {
        if (!is_array($participant)) {
            continue;
        }
        if (normalize_msk_participant_status((string) ($participant['status'] ?? 'active')) !== 'active') {
            continue;
        }
        $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        if (count($sessionNumbers) >= 12) {
            $summaryCompletedMsk++;
        }
        $journeyBridgeStatus = normalize_journey_bridge_status((string) ($participant['journey_bridge_status'] ?? 'belum'));
        if (in_array($journeyBridgeStatus, ['sudah_kgap', 'ikut_keduanya'], true)) {
            $summaryFollowingKgap++;
        }
    }
    $summaryCompletedDg1 = $stageCounts['DG 2'] + $stageCounts['DG 3'];
    $summaryCompletedDg2 = $stageCounts['DG 3'];
    $summaryCompletedDg3 = discipleship_completed_dg3_count();

    $today = today_date();
    $todayBaseTs = strtotime($today);
    if ($todayBaseTs === false) {
        $todayBaseTs = strtotime(date('Y-m-d'));
    }
    if ($todayBaseTs === false) {
        $todayBaseTs = time();
    }
    $today = date('Y-m-d', $todayBaseTs);
    $currentYear = (int) date('Y', $todayBaseTs);

    $upcomingBirthdays = [];
    foreach ($activeMembers as $member) {
        $memberName = trim((string) ($member['full_name'] ?? ''));
        if ($memberName === '') {
            continue;
        }
        $dayMonth = member_birth_day_month($member);
        if ($dayMonth === '') {
            continue;
        }

        $day = (int) substr($dayMonth, 0, 2);
        $month = (int) substr($dayMonth, 3, 2);
        $targetYear = $currentYear;

        if (!checkdate($month, $day, $targetYear)) {
            if ($month === 2 && $day === 29) {
                $day = 28;
            } else {
                continue;
            }
        }

        $nextDate = sprintf('%04d-%02d-%02d', $targetYear, $month, $day);
        if (strcmp($nextDate, $today) < 0) {
            $targetYear++;
            if (!checkdate($month, $day, $targetYear)) {
                if ($month === 2 && $day === 29) {
                    $day = 28;
                } else {
                    continue;
                }
            }
            $nextDate = sprintf('%04d-%02d-%02d', $targetYear, $month, $day);
        }

        $nextTs = strtotime($nextDate);
        if ($nextTs === false) {
            continue;
        }
        $daysUntil = (int) floor(($nextTs - $todayBaseTs) / 86400);
        if ($daysUntil < 0) {
            continue;
        }

        $countdownLabel = $daysUntil === 0 ? 'Hari ini' : ($daysUntil === 1 ? 'Besok' : ((string) $daysUntil . ' hari lagi'));
        $upcomingBirthdays[] = [
            'name' => $memberName,
            'day_month' => $dayMonth,
            'next_date' => $nextDate,
            'days_until' => $daysUntil,
            'countdown_label' => $countdownLabel,
        ];
    }
    usort($upcomingBirthdays, function ($a, $b) {
        $cmp = ((int) ($a['days_until'] ?? 0)) <=> ((int) ($b['days_until'] ?? 0));
        if ($cmp !== 0) {
            return $cmp;
        }
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });
    $upcomingBirthdaysDisplay = array_slice($upcomingBirthdays, 0, 3);

    echo "<section class=\"card msk-hero-card dashboard-main-hero-card\">\n";
    echo "  <div class=\"dashboard-main-hero-layout\">\n";
    echo "    <div class=\"dashboard-main-hero-primary\">\n";
    echo "      <div class=\"msk-hero-copy dashboard-main-hero-copy\">\n";
    echo "        <span class=\"msk-hero-kicker\">Dashboard Utama</span>\n";
    echo "        <h1>Portal Kerja Gereja</h1>\n";
    echo "        <p>Gunakan dashboard ini untuk membuka modul pelayanan yang dibutuhkan. Pemuridan, data jemaat, dan pengaturan sudah siap dipakai dari akses internal masing-masing.</p>\n";
    echo "      </div>\n";
    echo "    </div>\n";
    echo "    <div class=\"hero-panel\">\n";
    echo "    <div class=\"panel-title\">Reminder Ulang Tahun</div>\n";
    if (count($upcomingBirthdaysDisplay) === 0) {
        echo "    <div class=\"panel-note status-pending\"><strong>Reminder Ulang Tahun:</strong> Belum ada data ulang tahun jemaat aktif.</div>\n";
    } else {
        foreach ($upcomingBirthdaysDisplay as $birthdayReminder) {
            $birthdayName = trim((string) ($birthdayReminder['name'] ?? ''));
            if ($birthdayName === '') {
                $birthdayName = 'Jemaat';
            }
            $birthdayDate = normalize_ymd_date((string) ($birthdayReminder['next_date'] ?? ''));
            $birthdayDateLabel = $birthdayDate !== '' ? format_indo_date($birthdayDate) : format_member_birth_day_month((string) ($birthdayReminder['day_month'] ?? ''));
            $countdownLabel = trim((string) ($birthdayReminder['countdown_label'] ?? ''));
            if ($countdownLabel === '') {
                $countdownLabel = '-';
            }
            $birthdayStatusClass = 'status-done';
            $birthdayDaysUntil = (int) ($birthdayReminder['days_until'] ?? 9999);
            if ($birthdayDaysUntil <= 3) {
                $birthdayStatusClass = 'status-pending';
            } elseif ($birthdayDaysUntil <= 7) {
                $birthdayStatusClass = 'status-upcoming';
            }
            echo "    <div class=\"panel-note " . h($birthdayStatusClass) . "\"><strong>" . h($birthdayName) . "</strong> - " . h($birthdayDateLabel) . " (" . h($countdownLabel) . ")</div>\n";
        }
    }
    echo "    </div>\n";
    echo "  </div>\n";
    echo "</section>\n";

    $dashboardAccounts = [];
    $dashboardCurrentUser = current_username();
    $dashboardScopeBadgeClasses = [
        'worship_only' => 'warning',
        'branch' => 'success',
        'central_discipleship_readonly' => 'central-scope',
    ];
    foreach (read_user_accounts() as $accountRow) {
        if (!is_array($accountRow)) {
            continue;
        }
        $accountUsername = trim((string) ($accountRow['username'] ?? ''));
        if ($accountUsername === '') {
            continue;
        }
        $accountScope = normalize_auth_access_scope((string) ($accountRow['access_scope'] ?? 'branch'));
        $accountBranch = normalize_user_branch((string) ($accountRow['cabang'] ?? 'kutisari'));
        $accountCanAccessWorship = username_can_access_worship($accountUsername);
        $accountBranchLabel = $accountCanAccessWorship ? 'Ibadah Umum' : user_branch_label($accountBranch);
        $accountBranchStatLabel = $accountCanAccessWorship ? 'Fungsi' : 'Cabang';
        $accountScopeLabel = auth_access_scope_label($accountScope);
        $accountScopeBadgeClass = $dashboardScopeBadgeClasses[$accountScope] ?? 'success';
        if ($accountScope === 'worship_only' && !$accountCanAccessWorship) {
            $accountScopeLabel = 'Ibadah Umum Nonaktif';
            $accountScopeBadgeClass = 'muted';
        }
        $accountMark = $accountCanAccessWorship ? 'IBD' : strtoupper(substr(preg_replace('/[^a-z0-9]+/i', '', $accountBranch), 0, 3));
        if ($accountMark === '') {
            $accountMark = 'USR';
        }
        $isCurrentDashboardAccount = $dashboardCurrentUser !== '' && $dashboardCurrentUser === $accountUsername;
        $accountLastLoginAt = normalize_iso_datetime_to_jakarta((string) ($accountRow['last_login_at'] ?? ''));
        if ($accountLastLoginAt === '' && $isCurrentDashboardAccount) {
            $accountLastLoginAt = normalize_iso_datetime_to_jakarta((string) ($_SESSION['login_at'] ?? ''));
        }
        $dashboardAccounts[] = [
            'username' => $accountUsername,
            'mark' => $accountMark,
            'branch_stat_label' => $accountBranchStatLabel,
            'branch_label' => $accountBranchLabel,
            'scope_label' => $accountScopeLabel,
            'last_login_label' => $accountLastLoginAt !== '' ? format_datetime_id($accountLastLoginAt) : 'Belum pernah',
            'scope_badge_class' => $accountScopeBadgeClass,
            'is_current' => $isCurrentDashboardAccount,
        ];
    }
    $dashboardAccountCount = count($dashboardAccounts);
    $dashboardAccountSummary = $dashboardAccountCount === 0
        ? 'Belum ada akun login yang tersimpan.'
        : $dashboardAccountCount . ' akun login internal yang tersedia beserta hak aksesnya.';

    echo "<section class=\"card dashboard-main-modules-card\">\n";
    echo "  <div class=\"section-head dashboard-main-section-head\">\n";
    echo "    <h2>Akun Tersedia</h2>\n";
    echo "    <p>" . h($dashboardAccountSummary) . "</p>\n";
    echo "  </div>\n";
    echo "  <div class=\"feature-grid\">\n";
    if ($dashboardAccountCount === 0) {
        echo "    <div class=\"feature-card is-disabled dashboard-account-access-card\">\n";
        echo "      <div class=\"feature-icon muted\">\n";
        echo "        <span class=\"icon-ring\"></span>\n";
        echo "        <span class=\"icon-mark\">USR</span>\n";
        echo "      </div>\n";
        echo "      <div class=\"feature-title\">Belum Ada Akun</div>\n";
        echo "      <div class=\"feature-desc\">Tambahkan akun di data user agar daftar akses bisa tampil di dashboard.</div>\n";
        echo "      <div class=\"feature-meta\">\n";
        echo "        <span class=\"badge muted\">Kosong</span>\n";
        echo "      </div>\n";
        echo "    </div>\n";
    } else {
        foreach ($dashboardAccounts as $dashboardAccount) {
            $cardInnerHtml = '';
            $cardInnerHtml .= "      <div class=\"feature-icon\">\n";
            $cardInnerHtml .= "        <span class=\"icon-ring\"></span>\n";
            $cardInnerHtml .= "        <span class=\"icon-mark\">" . h($dashboardAccount['mark']) . "</span>\n";
            $cardInnerHtml .= "      </div>\n";
            $cardInnerHtml .= "      <div class=\"feature-title\">" . h($dashboardAccount['username']) . "</div>\n";
            $cardInnerHtml .= "      <div class=\"feature-stats\">\n";
            $cardInnerHtml .= "        <div class=\"feature-stat\"><span>" . h($dashboardAccount['branch_stat_label']) . "</span><strong>" . h($dashboardAccount['branch_label']) . "</strong></div>\n";
            $cardInnerHtml .= "        <div class=\"feature-stat\"><span>Login Terakhir</span><strong>" . h($dashboardAccount['last_login_label']) . "</strong></div>\n";
            $cardInnerHtml .= "      </div>\n";
            $cardInnerHtml .= "      <div class=\"feature-meta\">\n";
            $cardInnerHtml .= "        <span class=\"badge " . h($dashboardAccount['scope_badge_class']) . "\">" . h($dashboardAccount['scope_label']) . "</span>\n";
            if (!empty($dashboardAccount['is_current'])) {
                $cardInnerHtml .= "        <span class=\"badge muted\">Akun Aktif</span>\n";
            }
            $cardInnerHtml .= "      </div>\n";

            echo "    <article class=\"feature-card is-active dashboard-account-access-card\">\n";
            echo $cardInnerHtml;
            echo "    </article>\n";
        }
    }
    echo "  </div>\n";
    echo "</section>\n";

    page_footer();
    legacy_exit();
}
