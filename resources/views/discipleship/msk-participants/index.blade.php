<?php

if ($page === 'msk_classes') {
    $renderAsTabPanel = (bool) ($renderAsTabPanel ?? false);
    if (! $renderAsTabPanel) {
        page_header('Kelas MSK', $settings, $page, false, 'page-discipleship-table-scroll');
    } else {
        echo '<section class="discipleship-tab-panel discipleship-workspace__panel discipleship-list-panel journey-workspace-panel msk-classes-panel" id="discipleship-tabpanel-msk" role="tabpanel" aria-labelledby="discipleship-tab-msk" tabindex="0" data-discipleship-tab-panel data-tab-key="msk" data-page-title="Kelas MSK" data-body-class="page-msk_classes">'."\n";
    }
    $centralReadOnly = is_effective_central_discipleship_readonly();
    $mskIndexAction = route('discipleship.msk-classes');
    $mskStoreAction = route('discipleship.msk-classes.store');
    $mskImportAction = route('discipleship.msk-classes.import');
    $mskExportAction = route('discipleship.msk-classes.export');

    render_condition_alerts([
        ['when' => isset($_GET['saved']), 'tone' => 'success', 'message' => 'Data peserta kelas MSK berhasil disimpan.'],
        ['when' => isset($_GET['reactivated']), 'tone' => 'success', 'message' => 'Data peserta kelas MSK berhasil diaktifkan kembali.'],
        ['when' => isset($_GET['deleted']), 'tone' => 'success', 'message' => 'Data peserta kelas MSK berhasil dinonaktifkan.'],
        ['when' => isset($_GET['converted']), 'tone' => 'success', 'message' => 'Peserta luar yang menyelesaikan 12 sesi otomatis ditambahkan ke data pemuridan.'],
    ]);
    $error = trim((string) ($_GET['error'] ?? ''));
    render_mapped_error_alert($error, [
        'invalid_msk_source' => 'Jenis peserta tidak valid. Pilih dari peserta terdaftar atau peserta luar.',
        'missing_msk_member' => 'Pilih peserta jika jenis peserta adalah dari daftar peserta terdaftar.',
        'duplicate_msk_member' => 'Peserta tersebut sudah terdaftar di kelas MSK dan tidak bisa dipilih lagi.',
        'missing_msk_name' => 'Nama peserta luar wajib diisi.',
        'invalid_msk_batch_month' => 'Bulan batch MSK tidak valid. Pilih bulan dan tahun yang benar.',
        'invalid_msk_birth_date' => 'Tanggal lahir tidak valid. Gunakan tanggal lengkap atau format dd-mm untuk tanggal-bulan saja.',
        'invalid_msk_email' => 'Email peserta tidak valid.',
        'invalid_msk_photo_type' => 'Format foto peserta tidak didukung. Gunakan JPG/PNG/WEBP.',
        'msk_photo_too_large' => 'Ukuran foto peserta terlalu besar. Maksimal 5 MB per file.',
        'msk_photo_upload_failed' => 'Upload foto peserta gagal. Coba ulangi lagi.',
        'invalid_msk_participant' => 'Data peserta kelas MSK tidak ditemukan.',
        'export_zip_unavailable' => 'Fitur export Excel belum tersedia di server (ekstensi ZipArchive belum aktif).',
        'export_template_missing' => 'Template export MSK tidak ditemukan atau rusak.',
        'export_failed' => 'Export data kelas MSK gagal. Coba ulangi lagi.',
    ]);
    if (! $centralReadOnly) {
        render_pemuridan_import_feedback();
    }

    $membersSorted = filter_active_members($members);
    usort($membersSorted, function ($a, $b) {
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });

    $participantsById = is_array($participantsById ?? null) ? $participantsById : index_by_id($mskClasses);
    $editId = trim((string) ($editId ?? request()->query('edit', '')));
    $editParticipant = is_array($editParticipant ?? null)
        ? $editParticipant
        : ($editId !== '' ? ($participantsById[$editId] ?? null) : null);
    $autoOpenEditParticipantId = trim((string) ($autoOpenEditParticipantId ?? ''));
    if ($editParticipant !== null) {
        $autoOpenEditParticipantId = $editId;
    }
    if ($editId !== '' && $editParticipant === null && $error === '') {
        echo "<div class=\"alert danger\">Data peserta kelas MSK yang ingin diedit tidak ditemukan.</div>\n";
    }
    $requestedViewId = trim((string) ($requestedViewId ?? request()->query('view', '')));
    $autoOpenViewParticipantId = trim((string) ($autoOpenViewParticipantId ?? ''));
    if ($requestedViewId !== '') {
        if (isset($participantsById[$requestedViewId])) {
            $autoOpenViewParticipantId = $requestedViewId;
        } elseif ($error === '') {
            echo "<div class=\"alert danger\">Data peserta kelas MSK yang ingin dilihat tidak ditemukan.</div>\n";
        }
    }

    $participantsSorted = $mskClasses;
    usort($participantsSorted, function ($a, $b) {
        return strcasecmp((string) ($a['full_name'] ?? ''), (string) ($b['full_name'] ?? ''));
    });

    $renderMskParticipantForm = function (array $participant, string $batchMonth, string $closeActionAttr = '') use ($mskStoreAction): string {
        $participantId = trim((string) ($participant['id'] ?? ''));
        $fullName = trim((string) ($participant['full_name'] ?? ''));
        $gender = normalize_member_gender_value((string) ($participant['gender'] ?? ''));
        $birthDate = normalize_ymd_date((string) ($participant['birth_date'] ?? ''));
        $whatsapp = trim((string) ($participant['whatsapp'] ?? ''));
        $birthPlace = trim((string) ($participant['birth_place'] ?? ''));
        $address = trim((string) ($participant['address'] ?? ''));
        $email = strtolower(trim((string) ($participant['email'] ?? '')));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = '';
        }
        $notes = trim((string) ($participant['notes'] ?? ''));
        $mskMonth = import_normalize_month_strict((string) ($participant['msk_month'] ?? ''));
        if ($participantId === '' && $mskMonth === '') {
            $mskMonth = import_normalize_month_strict($batchMonth);
        }
        if ($participantId === '' && $mskMonth === '') {
            $mskMonth = date('Y-m');
        }
        $mskMonthBadgeLabel = $mskMonth !== '' ? format_indo_month($mskMonth) : 'Belum dipilih';

        $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        $sessionMap = [];
        foreach ($sessionNumbers as $sessionNumber) {
            $sessionMap[(string) $sessionNumber] = true;
        }

        $photos = [];
        foreach (extract_msk_participant_photos($participant) as $photo) {
            $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($photoPath === '') {
                continue;
            }
            $photoName = trim((string) ($photo['name'] ?? ''));
            if ($photoName === '') {
                $photoName = 'Foto';
            }
            $photos[] = [
                'path' => $photoPath,
                'name' => $photoName,
            ];
        }

        $sessionCount = count($sessionMap);
        $progressPercent = max(0, min(100, (int) round(($sessionCount / 12) * 100)));
        $statusLabel = 'Belum';
        $statusClass = 'is-pending';
        if ($sessionCount === 12) {
            $statusLabel = 'Selesai';
            $statusClass = 'is-complete';
        } elseif ($sessionCount > 0) {
            $statusLabel = 'Proses';
            $statusClass = 'is-progress';
        }
        $mskField = static function (string $label, string $controlHtml, string $extraClass = ''): string {
            $fieldClass = trim('msk-form-field '.$extraClass);

            return '<label class="'.h($fieldClass).'"><span class="msk-form-field-label">'.h($label).'</span>'.$controlHtml."</label>\n";
        };

        $identityFields = [];
        $identityFields[] = $mskField(
            'Nama Peserta',
            '<input type="text" name="full_name" value="'.h($fullName).'" placeholder="Nama lengkap" data-msk-name-input>'
        );

        $identityFields[] = $mskField(
            'Jenis Kelamin',
            '<select name="gender" data-msk-gender>'
                .'<option value="">- Pilih -</option>'
                .'<option value="Laki-laki" '.($gender === 'Laki-laki' ? 'selected' : '').'>Laki-laki</option>'
                .'<option value="Perempuan" '.($gender === 'Perempuan' ? 'selected' : '').'>Perempuan</option>'
                .'</select>'
        );
        $identityFields[] = $mskField(
            'Tanggal Lahir',
            '<input type="date" name="birth_date" value="'.h($birthDate).'" data-msk-birth-date>'
        );
        $identityFields[] = $mskField(
            'Tempat Lahir',
            '<input type="text" name="birth_place" value="'.h($birthPlace).'" placeholder="Kota lahir" data-msk-birth-place>'
        );

        $contactFields = [];
        $contactFields[] = $mskField(
            'Alamat',
            '<textarea name="address" rows="3" placeholder="Alamat domisili" data-msk-address>'.h($address).'</textarea>',
            'is-wide'
        );
        $contactFields[] = $mskField(
            'Email',
            '<input type="email" name="email" value="'.h($email).'" placeholder="email@contoh.com" data-msk-email>'
        );
        $contactFields[] = $mskField(
            'Nomor WhatsApp',
            '<input type="text" name="whatsapp" value="'.h($whatsapp).'" placeholder="08xxxxxxxxxx" data-msk-whatsapp>'
        );
        $contactFields[] = $mskField(
            'Upload Foto Peserta',
            '<input type="file" name="participant_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple><span class="msk-form-field-hint">JPG, PNG, atau WEBP. Bisa pilih lebih dari satu file.</span>',
            'is-upload is-wide'
        );
        if (count($photos) > 0) {
            $photoListHtml = '<div class="member-photo-list msk-photo-list">';
            $photoListHtml .= '<div class="member-photo-current">Foto saat ini</div>';
            foreach ($photos as $idx => $photo) {
                $photoPath = (string) ($photo['path'] ?? '');
                $photoUrl = secure_upload_url($photoPath);
                if ($photoUrl === '') {
                    continue;
                }
                $photoLabel = trim((string) ($photo['name'] ?? ''));
                if ($photoLabel === '') {
                    $photoLabel = 'Foto '.(string) ($idx + 1);
                }
                $photoListHtml .= '<div class="member-photo-item">'
                    .'<a class="note-link" href="'.h($photoUrl).'" target="_blank" rel="noopener">'.h($photoLabel).'</a>'
                    .'<label class="check-label"><input type="checkbox" name="remove_photo_paths[]" value="'.h($photoPath).'">Hapus</label>'
                    .'</div>';
            }
            $photoListHtml .= '</div>';
            $contactFields[] = '<div class="msk-form-meta-card is-wide">'.$photoListHtml."</div>\n";
        }

        $progressFields = [];
        $progressFields[] = $mskField(
            'Bulan-Tahun MSK Diikuti',
            '<input type="month" name="batch_month" value="'.h($mskMonth).'" required>'
        );
        $progressFields[] = $mskField(
            'Keterangan',
            '<textarea name="notes" rows="3" placeholder="Catatan peserta...">'.h($notes).'</textarea>',
            'is-wide'
        );

        ob_start();
        echo '<form method="post" action="'.h($mskStoreAction)."\" enctype=\"multipart/form-data\" class=\"form-grid msk-participant-form\" data-msk-form>\n";
        echo csrf_field()."\n";
        echo "  <input type=\"hidden\" name=\"action\" value=\"save_msk_participant\">\n";
        echo '  <input type="hidden" name="id" value="'.h($participantId)."\">\n";
        echo '  <input type="hidden" name="return_batch_month" value="'.h($batchMonth)."\">\n";
        echo "  <section class=\"msk-form-banner msk-form-full\">\n";
        echo "    <div class=\"msk-form-banner-copy\">\n";
        echo "      <span class=\"msk-form-banner-kicker\">Peserta MSK</span>\n";
        echo "      <h3>Lengkapi data peserta dengan rapi</h3>\n";
        echo "      <p>Gunakan form ini untuk menyimpan identitas, kontak, batch MSK, dan progres sesi dalam satu alur yang ringkas.</p>\n";
        echo "    </div>\n";
        echo "    <div class=\"msk-form-banner-meta\">\n";
        echo '      <span class="msk-form-badge">Batch: '.h($mskMonthBadgeLabel)."</span>\n";
        echo '      <span class="msk-form-badge is-status '.h($statusClass).'">'.h($statusLabel).' - '.h((string) $sessionCount).'/12 sesi - '.h((string) $progressPercent)."%</span>\n";
        echo "    </div>\n";
        echo "  </section>\n";
        echo "  <section class=\"msk-form-section msk-form-full\">\n";
        echo "    <div class=\"msk-form-section-head\">\n";
        echo "      <div>\n";
        echo "        <span class=\"msk-form-section-kicker\">Identitas</span>\n";
        echo "        <h3>Data dasar peserta</h3>\n";
        echo "      </div>\n";
        echo "      <p>Isi identitas utama peserta.</p>\n";
        echo "    </div>\n";
        echo "    <div class=\"msk-form-section-grid\">\n";
        echo implode('', $identityFields);
        echo "    </div>\n";
        echo "  </section>\n";
        echo "  <section class=\"msk-form-section msk-form-full\">\n";
        echo "    <div class=\"msk-form-section-head\">\n";
        echo "      <div>\n";
        echo "        <span class=\"msk-form-section-kicker\">Kontak</span>\n";
        echo "        <h3>Kontak dan lampiran</h3>\n";
        echo "      </div>\n";
        echo "      <p>Simpan alamat, nomor WhatsApp, email, dan lampiran foto peserta di area yang sama.</p>\n";
        echo "    </div>\n";
        echo "    <div class=\"msk-form-section-grid\">\n";
        echo implode('', $contactFields);
        echo "    </div>\n";
        echo "  </section>\n";
        echo "  <section class=\"msk-form-section msk-form-full\">\n";
        echo "    <div class=\"msk-form-section-head\">\n";
        echo "      <div>\n";
        echo "        <span class=\"msk-form-section-kicker\">Progress</span>\n";
        echo "        <h3>Batch dan progres sesi</h3>\n";
        echo "      </div>\n";
        echo "      <p>Atur batch MSK peserta, isi keterangan jika perlu, lalu tandai sesi yang sudah selesai.</p>\n";
        echo "    </div>\n";
        echo "    <div class=\"msk-form-section-grid\">\n";
        echo implode('', $progressFields);
        echo "    </div>\n";
        echo "  </section>\n";
        echo "  <fieldset class=\"dg-checklist msk-session-checklist msk-progress-fieldset msk-form-full\">\n";
        echo "    <legend>Checklist 12 Sesi MSK</legend>\n";
        echo "    <div class=\"msk-session-grid\">\n";
        for ($session = 1; $session <= 12; $session++) {
            $checked = isset($sessionMap[(string) $session]) ? 'checked' : '';
            echo '    <label class="check-label"><input type="checkbox" name="session_numbers[]" value="'.h((string) $session).'" '.$checked.'>Sesi '.h((string) $session)."</label>\n";
        }
        echo "    </div>\n";
        echo "  </fieldset>\n";
        $mskFormActionsClass = 'form-actions';
        if ($closeActionAttr !== '') {
            $mskFormActionsClass .= ' msk-form-actions is-right';
        }
        echo '  <div class="'.h($mskFormActionsClass)."\">\n";
        echo "    <button class=\"btn\" type=\"submit\">Simpan Peserta MSK</button>\n";
        if ($closeActionAttr === '') {
            echo '    <a class="btn ghost" href="'.h(route('discipleship.msk-classes', ['batch_month' => $batchMonth]))."\">Batal</a>\n";
        }
        echo "  </div>\n";
        echo "</form>\n";

        return (string) ob_get_clean();
    };

    $createMskFormContent = '';
    if (! $centralReadOnly) {
        $createMskParticipant = [
            'id' => '',
            'full_name' => '',
            'gender' => '',
            'birth_date' => '',
            'birth_place' => '',
            'address' => '',
            'email' => '',
            'whatsapp' => '',
            'photos' => [],
            'msk_month' => $latestBatchMonth,
            'session_numbers' => [],
            'notes' => '',
        ];
        $createMskFormContent = $renderMskParticipantForm($createMskParticipant, $batchMonthFilterParam, 'data-msk-create-close');
    }

    $mskModalTemplates = [];
    $mskEditModalTemplates = [];
    $appendMskViewTemplate = function (array $participant) use (&$mskModalTemplates, $batchMonthFilterParam, $centralReadOnly, $participantHistories, $participantProfiles): void {
        $viewParticipantId = trim((string) ($participant['id'] ?? ''));
        if ($viewParticipantId === '') {
            return;
        }

        $viewFullName = trim((string) ($participant['full_name'] ?? ''));
        if ($viewFullName === '') {
            $viewFullName = '-';
        }
        if (is_array($participantProfiles[$viewParticipantId] ?? null)) {
            $templateData = [
                'title' => $viewFullName,
                'content' => view('discipleship.msk-participants.profile', [
                    'profile' => $participantProfiles[$viewParticipantId],
                ])->render(),
            ];
            if (! $centralReadOnly) {
                $templateData['edit_href'] = route('discipleship.msk-classes', ['edit' => $viewParticipantId, 'batch_month' => $batchMonthFilterParam]);
            }
            $mskModalTemplates[$viewParticipantId] = $templateData;

            return;
        }

        $viewGender = normalize_member_gender_value((string) ($participant['gender'] ?? ''));
        if ($viewGender === '') {
            $viewGender = '-';
        }
        $viewBirthPlace = trim((string) ($participant['birth_place'] ?? ''));
        $viewBirthDate = normalize_ymd_date((string) ($participant['birth_date'] ?? ''));
        $viewBirthDateLabel = '-';
        if ($viewBirthDate !== '') {
            $viewBirthDateLabel = format_indo_date($viewBirthDate);
        }
        $viewBirthPlaceLabel = $viewBirthPlace !== '' ? $viewBirthPlace : '-';

        $viewAddress = trim((string) ($participant['address'] ?? ''));
        if ($viewAddress === '') {
            $viewAddress = '-';
        }

        $viewEmail = strtolower(trim((string) ($participant['email'] ?? '')));
        if ($viewEmail !== '' && filter_var($viewEmail, FILTER_VALIDATE_EMAIL) === false) {
            $viewEmail = '';
        }
        $viewEmailHtml = '-';
        if ($viewEmail !== '') {
            $viewEmailHtml = '<a class="note-link" href="'.h('mailto:'.$viewEmail).'">'.h($viewEmail).'</a>';
        }

        $viewWhatsapp = trim((string) ($participant['whatsapp'] ?? ''));
        $viewWaDisplay = $viewWhatsapp !== '' ? $viewWhatsapp : '-';
        $viewWaDigits = preg_replace('/\\D+/', '', $viewWhatsapp) ?? '';
        if ($viewWaDigits !== '' && strpos($viewWaDigits, '0') === 0) {
            $viewWaDigits = '62'.substr($viewWaDigits, 1);
        }
        $viewWaHtml = h($viewWaDisplay);
        if ($viewWaDigits !== '') {
            $viewWaHtml = '<a class="note-link" href="'.h('https://wa.me/'.$viewWaDigits).'" target="_blank" rel="noopener">'.h($viewWaDisplay).'</a>';
        }

        $viewMskMonth = import_normalize_month_strict((string) ($participant['msk_month'] ?? ''));
        $viewMskMonthLabel = $viewMskMonth !== '' ? format_indo_month($viewMskMonth) : '-';
        $viewSessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        $viewSessionCount = count($viewSessionNumbers);
        $viewProgressPercent = max(0, min(100, (int) round(($viewSessionCount / 12) * 100)));
        $viewProgressLabel = (string) $viewSessionCount.'/12 sesi';
        $viewParticipantStatus = normalize_msk_participant_status((string) ($participant['status'] ?? 'active'));
        $viewStatusClass = 'is-pending';
        $viewStatusBadge = '<span class="msk-status-badge is-pending">Belum</span>';
        if ($viewParticipantStatus === 'inactive') {
            $viewStatusClass = 'is-inactive';
            $viewStatusBadge = '<span class="msk-status-badge is-inactive">Nonaktif</span>';
        } elseif ($viewSessionCount === 12) {
            $viewStatusClass = 'is-complete';
            $viewStatusBadge = '<span class="msk-status-badge is-complete">Selesai</span>';
        } elseif ($viewSessionCount > 0) {
            $viewStatusClass = 'is-progress';
            $viewStatusBadge = '<span class="msk-status-badge is-progress">Proses</span>';
        }
        $viewSessionLabel = $viewSessionCount > 0 ? 'Sesi '.implode(', ', array_map('strval', $viewSessionNumbers)) : '-';

        $viewNotes = trim((string) ($participant['notes'] ?? ''));
        if ($viewNotes === '') {
            $viewNotes = '-';
        }

        $viewPhotoLinks = [];
        $viewPhotoNumber = 0;
        foreach (extract_msk_participant_photos($participant) as $photo) {
            $photoPath = sanitize_relative_upload_path((string) ($photo['path'] ?? ''));
            if ($photoPath === '') {
                continue;
            }
            $photoUrl = secure_upload_url($photoPath);
            if ($photoUrl === '') {
                continue;
            }
            $viewPhotoNumber++;
            $photoLabel = 'Foto '.(string) $viewPhotoNumber;
            $viewPhotoLinks[] = '<a class="note-link" href="'.h($photoUrl).'" target="_blank" rel="noopener">'.h($photoLabel).'</a>';
        }
        $viewPhotoHtml = count($viewPhotoLinks) > 0 ? '<div class="member-photo-links">'.implode(' ', $viewPhotoLinks).'</div>' : '-';

        $viewHistory = is_array($participantHistories[$viewParticipantId] ?? null)
            ? $participantHistories[$viewParticipantId]
            : [
                'linked' => false,
                'person_id' => '',
                'current_mentors' => [],
                'current_groups' => [],
                'current_stage' => '',
                'member_items' => [],
                'leader_items' => [],
            ];
        $viewCurrentGroupLeaders = is_array($viewHistory['current_group_leaders'] ?? null)
            ? $viewHistory['current_group_leaders']
            : (is_array($viewHistory['current_mentors'] ?? null) ? $viewHistory['current_mentors'] : []);
        $viewCurrentGroups = is_array($viewHistory['current_groups'] ?? null) ? $viewHistory['current_groups'] : [];
        $viewCurrentStage = normalize_dg_progress_value((string) ($viewHistory['current_stage'] ?? ''));
        $viewMemberItems = is_array($viewHistory['member_items'] ?? null) ? $viewHistory['member_items'] : [];
        $viewLeaderItems = is_array($viewHistory['leader_items'] ?? null) ? $viewHistory['leader_items'] : [];
        $viewLinked = ! empty($viewHistory['linked']);
        $viewPersonId = trim((string) ($viewHistory['person_id'] ?? ''));
        $viewInitials = '';
        foreach (array_slice(preg_split('/\s+/', $viewFullName) ?: [], 0, 2) as $namePart) {
            $viewInitials .= strtoupper(substr(trim((string) $namePart), 0, 1));
        }
        if ($viewInitials === '') {
            $viewInitials = 'MS';
        }
        $viewStageBadge = $viewCurrentStage !== ''
            ? '<span class="journey-track-badge is-'.strtolower(str_replace(' ', '', $viewCurrentStage)).'">'.h($viewCurrentStage).'</span>'
            : '<span class="journey-track-badge is-muted">Belum DG</span>';
        $renderHistoryItems = static function (array $items): string {
            if ($items === []) {
                return '';
            }
            ob_start();
            echo '<div class="journey-history-timeline">';
            foreach ($items as $item) {
                $stage = normalize_dg_progress_value((string) ($item['stage'] ?? ''));
                $stageBadge = $stage !== ''
                    ? '<span class="journey-track-badge is-'.h(strtolower(str_replace(' ', '', $stage))).'">'.h($stage).'</span>'
                    : '';
                echo '<article class="journey-history-item">';
                echo '<div class="journey-history-item-head"><div class="journey-history-item-title">'.h((string) ($item['title'] ?? 'Kelompok')).'</div><div class="journey-history-item-date">'.h((string) ($item['date'] ?? '-')).'</div></div>';
                echo '<div class="journey-history-item-meta">';
                echo $stageBadge;
                echo '<span class="journey-history-chip">'.h((string) ($item['role'] ?? '-')).'</span>';
                $leader = trim((string) ($item['leader'] ?? ''));
                if ($leader !== '') {
                    echo '<span class="journey-history-chip">Leader kelompok: '.h($leader).'</span>';
                }
                if (! empty($item['active'])) {
                    echo '<span class="journey-history-chip is-active">Aktif</span>';
                }
                echo '</div>';
                $members = is_array($item['members'] ?? null) ? array_filter($item['members']) : [];
                if ($members !== []) {
                    echo '<div class="journey-history-item-members">Anggota: '.h(implode(', ', $members)).'</div>';
                }
                $note = trim((string) ($item['note'] ?? ''));
                if ($note !== '') {
                    echo '<div class="journey-history-item-note">Catatan: '.h($note).'</div>';
                }
                echo '</article>';
            }
            echo '</div>';

            return (string) ob_get_clean();
        };

        ob_start();
        echo '<div class="msk-view-sheet">';
        echo '  <section class="msk-view-person-hero">';
        echo '    <div class="msk-view-person-avatar">'.h($viewInitials).'</div>';
        echo '    <div class="msk-view-person-copy"><div class="msk-view-person-name">'.h($viewFullName).'</div><div class="msk-view-person-sub">Peserta Kelas MSK'.($viewPersonId !== '' ? ' · ID Pemuridan '.h($viewPersonId) : '').'</div></div>';
        echo '    <div class="msk-view-person-badges">'.$viewStatusBadge.'<span class="journey-track-badge is-msk">Batch '.h($viewMskMonthLabel).'</span>'.$viewStageBadge.'</div>';
        echo '  </section>';
        echo '  <div class="msk-view-sections">';
        echo '    <section class="msk-view-section">';
        echo '      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Identitas</span><h4>Profil peserta</h4></div>';
        echo '      <dl class="msk-view-details">';
        echo '        <div class="msk-view-detail"><dt>Nama Peserta</dt><dd>'.h($viewFullName).'</dd></div>';
        echo '        <div class="msk-view-detail"><dt>Jenis Kelamin</dt><dd>'.h($viewGender).'</dd></div>';
        echo '        <div class="msk-view-detail"><dt>Tempat Lahir</dt><dd>'.h($viewBirthPlaceLabel).'</dd></div>';
        echo '        <div class="msk-view-detail"><dt>Tanggal Lahir</dt><dd>'.h($viewBirthDateLabel).'</dd></div>';
        echo '      </dl>';
        echo '    </section>';
        echo '    <section class="msk-view-section">';
        echo '      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Kontak</span><h4>Kontak dan akses</h4></div>';
        echo '      <dl class="msk-view-details">';
        echo '        <div class="msk-view-detail is-wide"><dt>Alamat</dt><dd>'.h($viewAddress).'</dd></div>';
        echo '        <div class="msk-view-detail"><dt>Email</dt><dd>'.$viewEmailHtml.'</dd></div>';
        echo '        <div class="msk-view-detail"><dt>WhatsApp</dt><dd>'.$viewWaHtml.'</dd></div>';
        echo '      </dl>';
        echo '    </section>';
        echo '    <section class="msk-view-section is-wide">';
        echo '      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Lampiran</span><h4>Foto dan keterangan</h4></div>';
        echo '      <div class="msk-view-rich-grid">';
        echo '        <div class="msk-view-rich-card"><span>Foto</span><div>'.$viewPhotoHtml.'</div></div>';
        echo '        <div class="msk-view-rich-card"><span>Keterangan</span><div>'.h($viewNotes).'</div></div>';
        echo '      </div>';
        echo '    </section>';
        echo '  </div>';
        echo '  <section class="msk-view-summary-card">';
        echo '    <div class="msk-view-section-head"><span class="msk-view-section-kicker">Perjalanan</span><h4>MSK dan pemuridan aktif</h4></div>';
        echo '    <div class="msk-view-summary-grid">';
        echo '      <div class="msk-view-summary-item"><span>Sesi MSK</span><strong>'.h($viewProgressLabel).'</strong></div>';
        echo '      <div class="msk-view-summary-item"><span>Leader Kelompok Aktif</span><strong>'.h($viewCurrentGroupLeaders !== [] ? implode(', ', $viewCurrentGroupLeaders) : '-').'</strong></div>';
        echo '      <div class="msk-view-summary-item"><span>Kelompok Aktif</span><strong>'.h($viewCurrentGroups !== [] ? implode(', ', $viewCurrentGroups) : '-').'</strong></div>';
        echo '      <div class="msk-view-summary-item"><span>Tahap DG</span><strong>'.h($viewCurrentStage !== '' ? $viewCurrentStage : 'Belum DG').'</strong></div>';
        echo '    </div>';
        echo '    <div class="msk-view-progress">';
        echo '      <div class="msk-progress-top"><span class="msk-progress-value">'.h($viewProgressLabel).'</span><span class="msk-progress-percent">'.h((string) $viewProgressPercent).'%</span></div>';
        echo '      <div class="msk-progress-bar" aria-hidden="true"><span style="width:'.h((string) $viewProgressPercent).'%"></span></div>';
        echo '      <div class="msk-progress-meta">'.h($viewSessionLabel).'</div>';
        echo '    </div>';
        echo '  </section>';
        echo '  <section class="msk-view-section is-wide msk-view-history-section">';
        echo '      <div class="msk-view-section-head"><span class="msk-view-section-kicker">Pemuridan</span><h4>Riwayat pemuridan</h4></div>';
        if (! $viewLinked) {
            echo '      <div class="journey-history-empty">Peserta ini belum terhubung ke data pemuridan. Setelah peserta dihubungkan ke Anggota DG, riwayat kelompok dan kepemimpinan akan muncul di sini.</div>';
        } elseif ($viewMemberItems === [] && $viewLeaderItems === []) {
            echo '      <div class="journey-history-empty">Peserta sudah terhubung ke data pemuridan, tetapi belum memiliki riwayat kelompok atau kepemimpinan.</div>';
        } else {
            echo '      <div class="journey-history-split-section"><div class="journey-history-split-header">Riwayat Sebagai Anggota</div>';
            echo $viewMemberItems !== [] ? $renderHistoryItems($viewMemberItems) : '<div class="journey-history-empty">Belum ada riwayat sebagai anggota.</div>';
            echo '      </div><div class="journey-history-split-divider"></div>';
            echo '      <div class="journey-history-split-section"><div class="journey-history-split-header">Riwayat Memimpin</div>';
            echo $viewLeaderItems !== [] ? $renderHistoryItems($viewLeaderItems) : '<div class="journey-history-empty">Belum ada riwayat memimpin kelompok.</div>';
            echo '      </div>';
        }
        echo '  </section>';
        echo '</div>';

        $templateData = [
            'title' => $viewFullName,
            'content' => (string) ob_get_clean(),
        ];
        if (! $centralReadOnly) {
            $templateData['edit_href'] = route('discipleship.msk-classes', ['edit' => $viewParticipantId, 'batch_month' => $batchMonthFilterParam]);
        }
        $mskModalTemplates[$viewParticipantId] = $templateData;
    };
    foreach ($participantsSorted as $participant) {
        $appendMskViewTemplate($participant);
    }

    echo view('discipleship.partials.page-header', [
        'header' => [
            'kicker' => 'Mengapa Saya Kristen',
            'title' => 'Kelas MSK',
            'description' => 'Pantau batch aktif, progres penyelesaian sesi, dan kelola peserta MSK dari satu panel yang lebih ringkas.',
            'tools' => [
                'element' => 'div',
                'attributes' => ['class' => 'table-tools msk-table-tools'],
                'partial' => 'discipleship.partials.page-header-controls.msk',
                'data' => compact(
                    'centralReadOnly',
                    'mskIndexAction',
                    'mskImportAction',
                    'mskExportAction',
                    'batchMonthOptions',
                    'editId',
                    'editParticipant',
                    'autoOpenViewParticipantId',
                    'participantsSearch',
                    'batchMonthFilterIsAll',
                    'totalParticipantsAll',
                    'batchMonthFilter',
                    'batchMonthMap',
                    'batchMonthFilterParam',
                ),
            ],
        ],
    ])->render();

    if (! $centralReadOnly) {
        foreach ($participantsFilteredByBatch as $participant) {
            $participantId = trim((string) ($participant['id'] ?? ''));
            $fullName = trim((string) ($participant['full_name'] ?? ''));
            if ($fullName === '') {
                $fullName = '-';
            }
            if ($participantId !== '') {
                $mskEditModalTemplates[$participantId] = [
                    'title' => 'Edit Peserta MSK: '.$fullName,
                    'content' => $renderMskParticipantForm($participant, $batchMonthFilterParam, 'data-msk-edit-close'),
                ];
            }
        }
    }

    echo '<section class="card table-card-plain" data-msk-list data-rows-url="'.h(route('discipleship.msk-classes.rows')).'" data-page="'.h((string) ($mskPage ?? 1)).'" data-per-page="'.h((string) ($mskPerPage ?? 50)).'" data-has-more="'.(! empty($hasMoreMskRows) ? '1' : '0').'" data-next-page="'.h((string) ($nextMskPage ?? '')).'">'."\n";
    echo "  <div class=\"table-wrap\" data-msk-scroll>\n";
    echo "    <table class=\"table\" id=\"msk-table\">\n";
    echo "      <thead><tr><th>Nama Peserta</th><th>Bulan MSK</th><th>Progress Sesi</th><th>Status</th><th>WhatsApp</th><th class=\"actions-head\">Aksi</th></tr></thead>\n";
    echo "      <tbody data-msk-list-body>\n";
    echo view('discipleship.msk-participants.partials.rows', compact('participantsFilteredByBatch', 'centralReadOnly', 'batchMonthFilterParam'))->render();
    echo '<tr data-msk-search-empty '.(((int) $totalParticipantsFiltered !== 0) ? 'hidden' : '').'><td colspan="6" aria-live="polite">'.h((string) ($mskEmptyMessage ?? 'Peserta tidak ditemukan.'))."</td></tr>\n";
    echo "<tr data-msk-loading hidden><td colspan=\"6\" aria-live=\"polite\">Memuat peserta...</td></tr>\n";
    /*
    foreach ($participantsFilteredByBatch as $participant) {
        $participantId = trim((string) ($participant['id'] ?? ''));
        $fullName = trim((string) ($participant['full_name'] ?? ''));
        if ($fullName === '') {
            $fullName = '-';
        }
        if (! $centralReadOnly && $participantId !== '') {
            $mskEditModalTemplates[$participantId] = [
                'title' => 'Edit Peserta MSK: '.$fullName,
                'content' => $renderMskParticipantForm($participant, $batchMonthFilterParam, 'data-msk-edit-close'),
            ];
        }
        $tableMskMonth = import_normalize_month_strict((string) ($participant['msk_month'] ?? ''));
        $mskMonthLabel = $tableMskMonth !== '' ? format_indo_month($tableMskMonth) : '-';

        $sessionNumbers = normalize_msk_session_numbers($participant['session_numbers'] ?? []);
        $sessionCount = count($sessionNumbers);
        $progressLabel = (string) $sessionCount.'/12 sesi';
        $progressPercent = max(0, min(100, (int) round(($sessionCount / 12) * 100)));
        $progressMeta = $sessionCount > 0 ? ('Sesi '.implode(', ', array_map('strval', $sessionNumbers))) : 'Belum ada sesi yang ditandai.';

        $participantStatus = normalize_msk_participant_status((string) ($participant['status'] ?? 'active'));
        $statusBadge = '<span class="msk-status-badge is-pending">Belum</span>';
        if ($participantStatus === 'inactive') {
            $statusBadge = '<span class="msk-status-badge is-inactive">Nonaktif</span>';
        } elseif ($sessionCount === 12) {
            $statusBadge = '<span class="msk-status-badge is-complete">Selesai</span>';
        } elseif ($sessionCount > 0) {
            $statusBadge = '<span class="msk-status-badge is-progress">Proses</span>';
        }

        $whatsapp = trim((string) ($participant['whatsapp'] ?? ''));
        $waDisplay = $whatsapp !== '' ? $whatsapp : '-';
        $waDigits = preg_replace('/\\D+/', '', $whatsapp) ?? '';
        if ($waDigits !== '' && strpos($waDigits, '0') === 0) {
            $waDigits = '62'.substr($waDigits, 1);
        }
        $waHtml = h($waDisplay);
        if ($waDigits !== '') {
            $waHtml = '<a class="note-link msk-wa-link" href="'.h('https://wa.me/'.$waDigits).'" target="_blank" rel="noopener">Hubungi <span>'.h($waDisplay).'</span></a>';
        } else {
            $waHtml = '<span class="msk-wa-empty">Tidak ada nomor</span>';
        }

        $searchText = trim($fullName.' '.$whatsapp.' '.(string) ($participant['email'] ?? ''));
        echo '<tr data-msk-search-row data-search-text="'.h($searchText).'">';
        $nameSubLabel = $participantStatus === 'inactive' ? 'Peserta kelas MSK · Nonaktif' : 'Peserta kelas MSK';
        echo '<td><div class="msk-name-cell"><span class="msk-name-main">'.h($fullName).'</span><span class="msk-name-sub">'.h($nameSubLabel).'</span></div></td>';
        echo '<td><div class="msk-month-cell"><span class="msk-month-main">'.h($mskMonthLabel).'</span><span class="msk-month-sub">Batch pembinaan</span></div></td>';
        echo '<td><div class="msk-progress-cell"><div class="msk-progress-top"><span class="msk-progress-value">'.h($progressLabel).'</span><span class="msk-progress-percent">'.h((string) $progressPercent).'%</span></div><div class="msk-progress-bar" aria-hidden="true"><span style="width:'.h((string) $progressPercent).'%"></span></div><div class="msk-progress-meta">'.h($progressMeta).'</div></div></td>';
        echo '<td>'.$statusBadge.'</td>';
        echo '<td>'.$waHtml.'</td>';
        echo '<td class="actions">';
        echo '<button class="btn tiny secondary icon-btn" type="button" data-msk-view-open="'.h($participantId).'" aria-label="Lihat" title="Lihat">'.icon_svg('eye').'</button>';
        if (! $centralReadOnly) {
            $toggleAction = $participantStatus === 'inactive' ? 'reactivate_msk_participant' : 'delete_msk_participant';
            $toggleRouteName = $participantStatus === 'inactive' ? 'discipleship.msk-classes.reactivate' : 'discipleship.msk-classes.deactivate';
            $toggleRoute = route($toggleRouteName, ['participant' => $participantId]);
            $toggleLabel = $participantStatus === 'inactive' ? 'Aktifkan' : 'Nonaktifkan';
            $toggleConfirm = $participantStatus === 'inactive'
                ? 'Aktifkan kembali data peserta MSK ini?'
                : 'Nonaktifkan data peserta MSK ini?';
            $toggleClass = $participantStatus === 'inactive' ? 'btn tiny secondary icon-btn' : 'btn tiny danger icon-btn';
            $toggleIcon = $participantStatus === 'inactive' ? icon_svg('check') : icon_svg('trash');
            echo '<button class="btn tiny icon-btn" type="button" data-msk-edit-open="'.h($participantId).'" aria-label="Edit" title="Edit">'.icon_svg('edit').'</button>';
            echo '<form method="post" action="'.h($toggleRoute)."\" class=\"inline\" onsubmit=\"return confirm('".h($toggleConfirm)."');\">";
            echo csrf_field();
            echo '<input type="hidden" name="action" value="'.h($toggleAction).'">';
            echo '<input type="hidden" name="id" value="'.h($participantId).'">';
            echo '<input type="hidden" name="batch_month" value="'.h($batchMonthFilterParam).'">';
            echo '<button class="'.h($toggleClass).'" type="submit" aria-label="'.h($toggleLabel).'" title="'.h($toggleLabel).'">'.$toggleIcon.'</button>';
            echo '</form>';
        }
        echo '</td>';
        echo "</tr>\n";
    }
    if ($totalParticipantsFiltered === 0) {
        echo "<tr><td colspan=\"6\">Belum ada data peserta kelas MSK.</td></tr>\n";
    } else {
        echo "<tr data-msk-search-empty hidden><td colspan=\"6\" aria-live=\"polite\">Peserta tidak ditemukan.</td></tr>\n";
    }
    */
    echo "      </tbody>\n";
    echo "    </table>\n";
    echo "  </div>\n";
    echo "</section>\n";

    echo "<div class=\"is-hidden\" data-msk-view-templates>\n";
    foreach ($mskModalTemplates as $templateId => $templateData) {
        $templateTitle = trim((string) ($templateData['title'] ?? 'Detail Peserta MSK'));
        if ($templateTitle === '') {
            $templateTitle = 'Detail Peserta MSK';
        }
        $templateEditHref = (string) ($templateData['edit_href'] ?? '');
        $templateContent = (string) ($templateData['content'] ?? '');
        echo '<template data-msk-view-template="'.h($templateId).'" data-msk-view-template-title="'.h($templateTitle).'" data-msk-view-template-edit="'.h($templateEditHref).'">'.$templateContent."</template>\n";
    }
    echo "</div>\n";

    $mskViewFooterHtml = '';
    if (! $centralReadOnly) {
        $mskViewFooterHtml .= '<a class="btn tiny secondary is-hidden" href="#" data-msk-view-edit-link>Edit</a>';
    }
    $mskViewFooterHtml .= '<button class="btn tiny ghost" type="button" data-msk-view-close>Tutup</button>';
    echo view('partials.modal', [
        'id' => 'msk-view-modal',
        'size' => 'standard',
        'modalAttrs' => [
            'data-msk-view-modal' => true,
            'data-msk-view-auto-open' => $autoOpenViewParticipantId,
        ],
        'cardClass' => 'member-view-modal-card msk-view-modal-card',
        'title' => 'Detail Peserta MSK',
        'titleAttrs' => ['data-msk-view-title' => true],
        'closeAttrs' => ['data-msk-view-close' => true],
        'bodyAttrs' => ['data-msk-view-body' => true],
        'bodyHtml' => '<div class="panel-note msk-modal-empty-state">Pilih tombol Lihat pada baris peserta untuk membuka detail peserta MSK.</div>',
        'footerHtml' => $mskViewFooterHtml,
    ])->render();

    if (! $centralReadOnly) {
        echo view('partials.modal', [
            'id' => 'msk-create-modal',
            'size' => 'standard',
            'modalAttrs' => ['data-msk-create-modal' => true],
            'cardClass' => 'member-view-modal-card msk-form-modal-card',
            'title' => 'Tambah Peserta MSK',
            'closeAttrs' => ['data-msk-create-close' => true],
            'bodyClass' => 'msk-form-modal-body',
            'bodyHtml' => $createMskFormContent,
        ])->render();
    }

    if (! $centralReadOnly && count($mskEditModalTemplates) > 0) {
        echo "<div class=\"is-hidden\" data-msk-edit-templates>\n";
        foreach ($mskEditModalTemplates as $templateId => $templateData) {
            $templateTitle = trim((string) ($templateData['title'] ?? 'Edit Peserta MSK'));
            if ($templateTitle === '') {
                $templateTitle = 'Edit Peserta MSK';
            }
            $templateContent = (string) ($templateData['content'] ?? '');
            echo '<template data-msk-edit-template="'.h($templateId).'" data-msk-edit-template-title="'.h($templateTitle).'">'.$templateContent."</template>\n";
        }
        echo "</div>\n";

        echo view('partials.modal', [
            'id' => 'msk-edit-modal',
            'size' => 'standard',
            'modalAttrs' => [
                'data-msk-edit-modal' => true,
                'data-msk-edit-auto-open' => $autoOpenEditParticipantId,
            ],
            'cardClass' => 'member-view-modal-card msk-form-modal-card',
            'title' => 'Edit Peserta MSK',
            'titleAttrs' => ['data-msk-edit-title' => true],
            'closeAttrs' => ['data-msk-edit-close' => true],
            'bodyClass' => 'msk-form-modal-body',
            'bodyAttrs' => ['data-msk-edit-body' => true],
            'bodyHtml' => '<div class="panel-note msk-modal-empty-state">Pilih tombol Edit pada baris peserta untuk membuka form edit peserta MSK.</div>',
        ])->render();
    }

    if ($renderAsTabPanel) {
        echo "</section>\n";
    } else {
        page_footer();
    }
}
