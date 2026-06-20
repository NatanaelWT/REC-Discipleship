@extends('layouts.rec_plain', [
    'title' => 'Form Laporan Pertemuan DG',
    'settings' => $settings,
    'bodyClass' => 'page-dg-public page-public-dg-report',
])

@section('content')
    @php
        $old = is_array($old) ? $old : [];
        $old['public_cabang'] = $publicBranch;
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

        $oldAbsentMemberIds = [];
        if (isset($old['absent_member_ids']) && is_array($old['absent_member_ids'])) {
            foreach ($old['absent_member_ids'] as $memberId) {
                $memberId = trim((string) $memberId);
                if ($memberId !== '' && ! in_array($memberId, $oldAbsentMemberIds, true)) {
                    $oldAbsentMemberIds[] = $memberId;
                }
            }
        }

        $oldMeditationSharerIds = [];
        if (isset($old['meditation_sharer_ids']) && is_array($old['meditation_sharer_ids'])) {
            foreach ($old['meditation_sharer_ids'] as $memberId) {
                $memberId = trim((string) $memberId);
                if ($memberId !== '' && ! in_array($memberId, $oldMeditationSharerIds, true)) {
                    $oldMeditationSharerIds[] = $memberId;
                }
            }
        }

        $qualityPrepareChecked = parse_bool_value($old['quality_prepare'] ?? false);
        $qualityPrayChecked = parse_bool_value($old['quality_pray'] ?? false);
        $qualityShareMeditationChecked = parse_bool_value($old['quality_share_meditation'] ?? false);
        $qualityRelationalChecked = parse_bool_value($old['quality_relational'] ?? false);
        $formDisabled = count($groupOptions) === 0;
        $hasValidSelectedGroup = $oldGroupId !== '' && isset($groupMap[$oldGroupId]);
        if ($hasValidSelectedGroup) {
            $selectedGroupLeaderId = trim((string) ($groupMap[$oldGroupId]['leader_id'] ?? ''));
            if ($selectedGroupLeaderId === '' || $selectedGroupLeaderId !== $oldLeaderId) {
                $hasValidSelectedGroup = false;
            }
        }
        $dgFormUnlocked = ! $formDisabled && $oldLeaderId !== '' && $hasValidSelectedGroup;
        $initialMeditationMinTimes = 2;
        if ($oldGroupId !== '' && isset($groupMap[$oldGroupId])) {
            $initialMeditationMinTimes = dg_progress_min_share_times((string) ($groupMap[$oldGroupId]['progress'] ?? ''));
        }
    @endphp

    @if ($submitted)
        <div class="alert success">Laporan pertemuan DG berhasil dikirim. Terima kasih.</div>
    @endif

    @if ($publicDgReportError !== '')
        <div class="alert danger">{{ $publicDgReportError }}</div>
    @endif

    @if ($formDisabled)
        <div class="alert danger">Belum ada data Kelompok DG. Hubungi admin terlebih dahulu.</div>
    @endif

    <section class="card public-dg-report-card">
      <div class="card-row">
        <h2>Jurnal Temu DG - {{ $publicBranchLabel }}</h2>
      </div>
      <form method="post" action="{{ route('public.dg.report.store', ['branch' => $publicBranch]) }}" enctype="multipart/form-data" class="form-grid dg-public-report-form" data-dg-public-form data-dg-hard-disabled="{{ $formDisabled ? '1' : '0' }}">
        @csrf
        <input type="hidden" name="action" value="save_public_dg_report">
        <input type="hidden" name="public_cabang" value="{{ $publicBranch }}">

        <label class="dg-question dg-panel"><span class="question-label">Nama Pemimpin DG <span class="required-mark">*</span></span><select name="leader_id" data-dg-leader required @disabled($formDisabled)>
          <option value="">- Pilih Pemimpin -</option>
          @foreach ($leaderOptions as $leaderRow)
            @php
                $leaderId = trim((string) ($leaderRow['id'] ?? ''));
                $leaderName = trim((string) ($leaderRow['name'] ?? ''));
            @endphp
            @continue($leaderId === '' || $leaderName === '')
            <option value="{{ $leaderId }}" @selected($oldLeaderId === $leaderId)>{{ $leaderName }}</option>
          @endforeach
        </select></label>

        @php
            $groupSelectDisabled = $formDisabled || $oldLeaderId === '';
            $groupPlaceholder = $oldLeaderId === '' ? '- Pilih Pemimpin Dulu -' : '- Pilih Kelompok -';
        @endphp
        <label class="dg-question dg-panel"><span class="question-label">Kelompok DG <span class="required-mark">*</span></span><select name="group_id" data-dg-group required @disabled($groupSelectDisabled)>
          <option value="">{{ $groupPlaceholder }}</option>
          @if ($oldLeaderId !== '')
            @foreach ($groupOptions as $groupRow)
              @php
                  $groupId = trim((string) ($groupRow['id'] ?? ''));
                  $groupLeaderId = trim((string) ($groupRow['leader_id'] ?? ''));
              @endphp
              @continue($groupId === '' || $groupLeaderId === '' || $groupLeaderId !== $oldLeaderId)
              @php
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
              @endphp
              <option value="{{ $groupId }}" @selected($oldGroupId === $groupId)>{{ $memberLabel }}</option>
            @endforeach
          @endif
        </select></label>

        <label class="dg-question dg-panel"><span class="question-label">Tanggal Pelaksanaan <span class="required-mark">*</span></span><input type="date" name="meeting_date" value="{{ $oldMeetingDate }}" data-dg-requires-group @disabled(! $dgFormUnlocked) required></label>

        <label class="dg-question dg-panel"><span class="question-label">Materi DG yang Dibahas <span class="required-mark">*</span></span><select name="material_topic" data-dg-material-topic data-dg-requires-group required @disabled(! $dgFormUnlocked)>
          <option value="">- Pilih Materi -</option>
          @foreach ($materialOptions as $materialOption)
            <option value="{{ $materialOption }}" @selected($oldMaterialTopic === $materialOption)>{{ $materialOption }}</option>
          @endforeach
        </select></label>

        <label class="dg-question dg-panel" data-dg-material-other-wrap @style(['display:none' => $oldMaterialTopic !== 'Lainnya'])>Materi Lainnya<input type="text" name="material_topic_other" value="{{ $oldMaterialTopicOther }}" data-dg-material-other data-dg-requires-group @disabled(! $dgFormUnlocked)></label>

        <div class="dg-absence-block dg-panel dg-section-absence">
          <label class="dg-question">Anggota DG yang Tidak Hadir</label>
          <div class="panel-note">Pilih anggota yang tidak hadir pada pertemuan ini.</div>
          <div data-dg-absent-list></div>
          <label class="dg-question dg-absence-reason">Alasan Anggota Tidak Hadir<textarea name="absence_reason" rows="2" placeholder="Isi alasan jika ada anggota yang tidak hadir" data-dg-requires-group @disabled(! $dgFormUnlocked)>{{ $oldAbsenceReason }}</textarea></label>
        </div>

        <div class="dg-checklist dg-section-quality" role="group" aria-labelledby="dg-quality-title">
          <div class="dg-section-title" id="dg-quality-title">Kualitas Pemimpin DG</div>
          <label class="check-label"><input type="checkbox" name="quality_prepare" value="1" data-dg-requires-group @checked($qualityPrepareChecked) @disabled(! $dgFormUnlocked)> Saya sudah mempersiapkan penyampaian materi sesi yang dilaporkan saat ini</label>
          <label class="check-label"><input type="checkbox" name="quality_pray" value="1" data-dg-requires-group @checked($qualityPrayChecked) @disabled(! $dgFormUnlocked)> Saya sudah mendoakan tiap anggota sebelum pertemuan DG yang dilaporkan saat ini</label>
          <label class="check-label"><input type="checkbox" name="quality_share_meditation" value="1" data-dg-requires-group @checked($qualityShareMeditationChecked) @disabled(! $dgFormUnlocked)> Saya sudah setia membagikan hasil meditasi Injil dengan kata "aku" atau "saya" di WAG DG dalam 1 minggu terakhir</label>
          <label class="check-label"><input type="checkbox" name="quality_relational" value="1" data-dg-requires-group @checked($qualityRelationalChecked) @disabled(! $dgFormUnlocked)> Saya sudah melakukan komunikasi relasional dengan tiap anggota kelompok di luar pertemuan DG dalam 1-2 minggu terakhir</label>
        </div>

        <div class="dg-rating dg-section-sharing" role="group" aria-labelledby="dg-sharing-title">
          <div class="dg-section-title" id="dg-sharing-title">Sharing kelompok semakin terbuka &amp; mendalam <span class="required-mark">*</span></div>
          <div class="dg-rating-body">
            <div class="dg-rating-hint"><span>Sangat tidak setuju</span><span>Sangat setuju</span></div>
            <div class="dg-rating-scale">
              @for ($score = 1; $score <= 10; $score++)
                <label class="dg-rating-option"><input type="radio" name="sharing_openness" value="{{ (string) $score }}" data-dg-requires-group @checked($oldSharingOpenness === (string) $score) @disabled(! $dgFormUnlocked) required><span>{{ (string) $score }}</span></label>
              @endfor
            </div>
          </div>
        </div>

        <div class="dg-share-block dg-panel dg-section-sharer">
          <label class="dg-question" data-dg-meditation-label>Anggota DG yang membagikan hasil meditasi Injil dengan kata "aku" atau "saya" di WAG DG minimal {{ (string) $initialMeditationMinTimes }} kali dalam 1 minggu terakhir (boleh kosong)</label>
          <div data-dg-sharer-list></div>
        </div>

        <label class="dg-question dg-panel dg-section-notes">Catatan Tambahan / Kendala (jika ada)<textarea name="additional_notes" rows="3" data-dg-requires-group @disabled(! $dgFormUnlocked)>{{ $oldAdditionalNotes }}</textarea></label>

        <label class="dg-question dg-panel dg-section-photo"><span class="question-label">Foto Pertemuan</span><span class="dg-upload-field" data-dg-upload-field><span class="dg-upload-copy"><span class="dg-upload-badge">Pilih Foto</span><span class="dg-upload-meta" data-dg-upload-label>Belum ada file dipilih</span></span><span class="dg-upload-hint">JPG, PNG, atau WEBP. Bisa pilih lebih dari satu.</span><input type="file" name="meeting_photos[]" accept="image/jpeg,image/png,image/webp" data-dg-photo-input data-dg-requires-group @disabled(! $dgFormUnlocked) multiple></span></label>

        <div class="form-actions dg-form-actions">
          <button class="btn" type="submit" data-dg-submit @disabled(! $dgFormUnlocked)>Kirim Laporan</button>
          <a class="btn ghost" href="{{ route('public.dg.branch') }}">Kembali</a>
        </div>
      </form>
    </section>

    <script>
    (function () {
      var form = document.querySelector('[data-dg-public-form]');
      if (!form) { return; }
      var rawGroups = {!! \Illuminate\Support\Js::from($groupOptions) !!};
      var groups = rawGroups.map(function (groupRow) {
        if (!groupRow || typeof groupRow !== 'object') { return groupRow; }
        groupRow.id = String(groupRow.id || '');
        groupRow.leader_id = String(groupRow.leader_id || '');
        if (Array.isArray(groupRow.members)) {
          groupRow.members.forEach(function (memberRow) {
            if (memberRow && typeof memberRow === 'object') {
              memberRow.id = String(memberRow.id || '');
            }
          });
        }
        return groupRow;
      });
      var selectedAbsent = new Set({!! \Illuminate\Support\Js::from($oldAbsentMemberIds) !!});
      var selectedSharer = new Set({!! \Illuminate\Support\Js::from($oldMeditationSharerIds) !!});
      var leaderSelect = form.querySelector('[data-dg-leader]');
      var groupSelect = form.querySelector('[data-dg-group]');
      var absentList = form.querySelector('[data-dg-absent-list]');
      var sharerList = form.querySelector('[data-dg-sharer-list]');
      var meditationLabel = form.querySelector('[data-dg-meditation-label]');
      var materialTopic = form.querySelector('[data-dg-material-topic]');
      var materialOtherWrap = form.querySelector('[data-dg-material-other-wrap]');
      var materialOtherInput = form.querySelector('[data-dg-material-other]');
      var photoInput = form.querySelector('[data-dg-photo-input]');
      var photoField = form.querySelector('[data-dg-upload-field]');
      var photoLabel = form.querySelector('[data-dg-upload-label]');
      var submitBtn = form.querySelector('[data-dg-submit]');
      var hardDisabled = form.getAttribute('data-dg-hard-disabled') === '1';
      var initialGroupId = groupSelect ? (groupSelect.value || '') : '';
      var collectChecked = function (container, fieldName) {
        var selected = new Set();
        if (!container) { return selected; }
        var checked = container.querySelectorAll('input[name="' + fieldName + '"]:checked');
        checked.forEach(function (input) {
          if (input && input.value) {
            selected.add(input.value);
          }
        });
        return selected;
      };
      var minTimesFromProgress = function (progress) {
        var text = String(progress || '').trim().toUpperCase();
        var match = text.match(/^DG\s*([1-3])$/);
        if (match) {
          return parseInt(match[1], 10);
        }
        if (/^[1-3]$/.test(text)) {
          return parseInt(text, 10);
        }
        return 2;
      };
      var updatePhotoInputState = function () {
        if (!photoInput || !photoLabel) { return; }
        var files = photoInput.files;
        if (files && files.length > 0) {
          photoLabel.textContent = files.length === 1 ? files[0].name : files.length + ' file dipilih';
        } else {
          photoLabel.textContent = 'Belum ada file dipilih';
        }
        if (photoField) {
          photoField.classList.toggle('is-disabled', !!photoInput.disabled);
          photoField.classList.toggle('has-value', !!(files && files.length > 0));
        }
      };
      var updateFormLockState = function () {
        var leaderReady = leaderSelect ? (leaderSelect.value || '') !== '' : false;
        var groupReady = groupSelect ? (!groupSelect.disabled && (groupSelect.value || '') !== '') : false;
        var unlocked = !hardDisabled && leaderReady && groupReady;
        var targets = form.querySelectorAll('[data-dg-requires-group]');
        targets.forEach(function (input) {
          input.disabled = !unlocked;
        });
        if (submitBtn) {
          submitBtn.disabled = !unlocked;
        }
        updatePhotoInputState();
      };

      var renderGroupOptions = function () {
        if (!groupSelect) { return; }
        var selectedLeader = leaderSelect ? (leaderSelect.value || '') : '';
        var keepGroupId = groupSelect.value || initialGroupId;
        while (groupSelect.firstChild) { groupSelect.removeChild(groupSelect.firstChild); }
        var placeholder = document.createElement('option');
        placeholder.value = '';
        if (!selectedLeader) {
          placeholder.textContent = '- Pilih Pemimpin Dulu -';
          groupSelect.appendChild(placeholder);
          groupSelect.disabled = true;
          groupSelect.value = '';
          initialGroupId = '';
          return;
        }
        groupSelect.disabled = false;
        placeholder.textContent = '- Pilih Kelompok -';
        groupSelect.appendChild(placeholder);
        var filtered = groups.filter(function (groupRow) {
          return groupRow.leader_id === selectedLeader;
        });
        if (filtered.length === 0) {
          placeholder.textContent = '- Tidak ada kelompok untuk pemimpin ini -';
        }
        filtered.forEach(function (groupRow) {
          var memberNames = Array.isArray(groupRow.members) ? groupRow.members.map(function (memberRow) {
            return memberRow && memberRow.name ? String(memberRow.name) : '';
          }).filter(function (name) { return name !== ''; }) : [];
          var memberLabel = memberNames.length > 0 ? memberNames.join(', ') : 'Belum ada anggota';
          var option = document.createElement('option');
          option.value = groupRow.id;
          option.textContent = memberLabel;
          option.title = option.textContent;
          groupSelect.appendChild(option);
        });
        if (keepGroupId && filtered.some(function (groupRow) { return groupRow.id === keepGroupId; })) {
          groupSelect.value = keepGroupId;
        } else {
          groupSelect.value = '';
        }
        initialGroupId = '';
      };

      var renderChecklist = function (container, fieldName, selectedSet, members, emptyText) {
        if (!container) { return; }
        while (container.firstChild) { container.removeChild(container.firstChild); }
        if (!members || members.length === 0) {
          var empty = document.createElement('div');
          empty.className = 'panel-note';
          empty.textContent = emptyText;
          container.appendChild(empty);
          return;
        }
        members.forEach(function (memberRow) {
          var label = document.createElement('label');
          label.className = 'dg-member-item';
          var checkbox = document.createElement('input');
          checkbox.type = 'checkbox';
          checkbox.name = fieldName;
          checkbox.value = memberRow.id;
          checkbox.setAttribute('data-dg-requires-group', '1');
          checkbox.checked = selectedSet.has(memberRow.id);
          label.appendChild(checkbox);
          label.appendChild(document.createTextNode(' ' + (memberRow.name || '-')));
          container.appendChild(label);
        });
      };

      var renderMembers = function () {
        var groupId = groupSelect ? (groupSelect.value || '') : '';
        var groupRow = groups.find(function (item) { return item.id === groupId; }) || null;
        var members = groupRow && Array.isArray(groupRow.members) ? groupRow.members : [];
        var minTimes = minTimesFromProgress(groupRow ? groupRow.progress : '');
        if (meditationLabel) {
          meditationLabel.textContent = 'Anggota DG yang membagikan hasil meditasi Injil dengan kata "aku" atau "saya" di WAG DG minimal ' + minTimes + ' kali dalam 1 minggu terakhir (boleh kosong)';
        }
        renderChecklist(absentList, 'absent_member_ids[]', selectedAbsent, members, 'Belum ada anggota pada kelompok ini.');
        renderChecklist(sharerList, 'meditation_sharer_ids[]', selectedSharer, members, 'Belum ada anggota pada kelompok ini.');
      };

      var syncMaterialOther = function () {
        if (!materialTopic || !materialOtherWrap || !materialOtherInput) { return; }
        var isOther = !materialTopic.disabled && materialTopic.value === 'Lainnya';
        materialOtherWrap.style.display = isOther ? '' : 'none';
        materialOtherInput.required = isOther;
      };

      if (leaderSelect) {
        leaderSelect.addEventListener('change', function () {
          selectedAbsent = collectChecked(absentList, 'absent_member_ids[]');
          selectedSharer = collectChecked(sharerList, 'meditation_sharer_ids[]');
          renderGroupOptions();
          renderMembers();
          updateFormLockState();
          syncMaterialOther();
        });
      }
      if (groupSelect) {
        groupSelect.addEventListener('change', function () {
          selectedAbsent = collectChecked(absentList, 'absent_member_ids[]');
          selectedSharer = collectChecked(sharerList, 'meditation_sharer_ids[]');
          renderMembers();
          updateFormLockState();
          syncMaterialOther();
        });
      }
      if (materialTopic) {
        materialTopic.addEventListener('change', syncMaterialOther);
      }
      if (photoInput) {
        photoInput.addEventListener('change', updatePhotoInputState);
      }

      renderGroupOptions();
      renderMembers();
      updateFormLockState();
      syncMaterialOther();
    })();
    </script>
@endsection
