@extends('layouts.rec_plain', [
    'title' => 'Jurnal Umpan Balik Anggota',
    'settings' => $settings,
    'bodyClass' => 'page-dg-public page-public-member-feedback',
])

@section('content')
    @php
        $old = is_array($old) ? $old : [];
        $old['public_cabang'] = $publicBranch;
        $oldGroupId = trim((string) ($old['group_id'] ?? ''));
        $oldRespondentPersonId = trim((string) ($old['respondent_person_id'] ?? ''));
        $oldFeedbackSession = normalize_public_member_feedback_session($old['feedback_session'] ?? '');
        if ($oldFeedbackSession === 0) {
            $oldFeedbackSession = $requestedFeedbackSession;
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
            if (! is_array($memberRow)) {
                continue;
            }
            if (trim((string) ($memberRow['id'] ?? '')) === $oldRespondentPersonId) {
                $hasValidRespondent = true;
                break;
            }
        }
        $feedbackUnlocked = ! $formDisabled && $hasValidSelectedGroup && $hasValidRespondent && $oldFeedbackSession !== 0;
        $selectedLeaderLabel = 'DG Saudara';
        if (is_array($selectedGroupRow)) {
            $selectedLeaderLabel = public_member_feedback_group_title($selectedGroupRow);
        }
        $initialGroupMeta = 'Pilih kelompok DG, lalu pilih nama Saudara sebagai pengisi.';
        if (is_array($selectedGroupRow)) {
            $memberCount = count($selectedGroupMembers);
            $initialGroupMeta = public_member_feedback_group_title($selectedGroupRow) . ' - ' . (string) $memberCount . ' anggota';
        }
    @endphp

    @if ($submitted)
        <div class="alert success">Jurnal umpan balik anggota berhasil dikirim. Terima kasih.</div>
    @endif

    @if ($publicMemberFeedbackError !== '')
        <div class="alert danger">{{ $publicMemberFeedbackError }}</div>
    @endif

    @if ($formDisabled)
        <div class="alert danger">Belum ada data Kelompok DG. Hubungi admin terlebih dahulu.</div>
    @endif

    <section class="card public-feedback-card">
      <div class="card-row public-feedback-head">
        <div>
          <h2>Jurnal Umpan Balik Anggota - {{ $publicBranchLabel }}</h2>
          <p class="public-feedback-subtitle">Jurnal ini diisi oleh setiap anggota DG pada pertemuan 3 dan 12.</p>
        </div>
        <span class="badge warning">Form Publik</span>
      </div>
      <form method="post" action="{{ route('public.member-feedback.store') }}" class="form-grid public-member-feedback-form" data-public-member-feedback-form data-feedback-hard-disabled="{{ $formDisabled ? '1' : '0' }}">
        @csrf
        <input type="hidden" name="action" value="save_public_member_feedback">
        <input type="hidden" name="public_cabang" value="{{ $publicBranch }}">

        <label class="dg-question dg-panel"><span class="question-label">Kelompok DG <span class="required-mark">*</span></span><select name="group_id" data-feedback-group required{{ $formDisabled ? ' disabled' : '' }}>
          <option value="">- Pilih Kelompok DG -</option>
          @foreach ($groupOptions as $groupRow)
            @php
                if (! is_array($groupRow)) {
                    continue;
                }
                $groupId = trim((string) ($groupRow['id'] ?? ''));
                if ($groupId === '') {
                    continue;
                }
            @endphp
            <option value="{{ $groupId }}"{{ $oldGroupId === $groupId ? ' selected' : '' }}>{{ public_member_feedback_group_option_label($groupRow) }}</option>
          @endforeach
        </select></label>

        @php
            $memberSelectDisabled = $formDisabled || ! $hasValidSelectedGroup;
            $memberPlaceholder = $hasValidSelectedGroup ? '- Pilih Nama Pengisi -' : '- Pilih Kelompok Dulu -';
        @endphp
        <label class="dg-question dg-panel"><span class="question-label">Nama pengisi form ini <span class="required-mark">*</span></span><select name="respondent_person_id" data-feedback-respondent data-initial-member="{{ $oldRespondentPersonId }}" required{{ $memberSelectDisabled ? ' disabled' : '' }}>
          <option value="">{{ $memberPlaceholder }}</option>
          @if ($hasValidSelectedGroup)
            @foreach ($selectedGroupMembers as $memberRow)
              @php
                  if (! is_array($memberRow)) {
                      continue;
                  }
                  $memberId = trim((string) ($memberRow['id'] ?? ''));
                  $memberName = trim((string) ($memberRow['name'] ?? ''));
                  if ($memberId === '' || $memberName === '') {
                      continue;
                  }
              @endphp
              <option value="{{ $memberId }}"{{ $oldRespondentPersonId === $memberId ? ' selected' : '' }}>{{ $memberName }}</option>
            @endforeach
          @endif
        </select></label>

        <label class="dg-question dg-panel"><span class="question-label">Pertemuan umpan balik <span class="required-mark">*</span></span><select name="feedback_session" data-feedback-session required{{ $formDisabled ? ' disabled' : '' }}>
          <option value="">- Pilih Pertemuan -</option>
          @foreach ([3, 12] as $sessionNumber)
            <option value="{{ (string) $sessionNumber }}"{{ $oldFeedbackSession === $sessionNumber ? ' selected' : '' }}>Pertemuan {{ (string) $sessionNumber }}</option>
          @endforeach
        </select></label>
        <div class="dg-panel public-feedback-group-meta" data-feedback-group-meta>{{ $initialGroupMeta }}</div>

        @foreach ($questions as $sectionKey => $section)
          @php
              if (! is_array($section)) {
                  continue;
              }
              $sectionTitle = trim((string) ($section['title'] ?? 'Bagian'));
              $sectionIntro = trim((string) ($section['intro'] ?? ''));
          @endphp
          <section class="public-feedback-section" data-feedback-section="{{ (string) $sectionKey }}">
            <div class="public-feedback-section-head">
              <h3>{{ $sectionTitle }} - <span data-feedback-leader-name>{{ $selectedLeaderLabel }}</span></h3>
              @if ($sectionIntro !== '')
                <p>{{ $sectionIntro }}</p>
              @endif
            </div>
            <div class="public-feedback-section-grid">
              @php $sectionRatings = $section['ratings'] ?? []; @endphp
              @if (is_array($sectionRatings))
                @foreach ($sectionRatings as $question)
                  @php
                      if (! is_array($question)) {
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
                  @endphp
                  <fieldset class="{{ $ratingClass }}">
                    <legend>{{ $questionLabel }} <span class="required-mark">*</span></legend>
                    <div class="dg-rating-body">
                      <div class="dg-rating-hint public-feedback-hint{{ $middleHint !== '' ? ' has-middle' : '' }}"><span>{{ $leftHint }}</span>@if ($middleHint !== '')<span>{{ $middleHint }}</span>@endif<span>{{ $rightHint }}</span></div>
                      <div class="dg-rating-scale">
                        @for ($score = 1; $score <= $scale; $score++)
                          <label class="dg-rating-option"><input type="radio" name="ratings[{{ $questionKey }}]" value="{{ (string) $score }}" data-feedback-requires-member{{ $oldRatingValue === (string) $score ? ' checked' : '' }}{{ $feedbackUnlocked ? '' : ' disabled' }} required><span>{{ (string) $score }}</span></label>
                        @endfor
                      </div>
                    </div>
                  </fieldset>
                @endforeach
              @endif
              @php
                  $noteKey = trim((string) ($section['note_key'] ?? ''));
                  $noteLabel = trim((string) ($section['note_label'] ?? ''));
              @endphp
              @if ($noteKey !== '' && $noteLabel !== '')
                @php $oldNoteValue = trim((string) ($oldNotes[$noteKey] ?? '')); @endphp
                <label class="dg-question dg-panel public-feedback-note"><span class="question-label">{{ $noteLabel }} <span class="public-feedback-optional">Opsional</span></span><textarea name="notes[{{ $noteKey }}]" rows="3" maxlength="2500" data-feedback-requires-member{{ $feedbackUnlocked ? '' : ' disabled' }}>{{ $oldNoteValue }}</textarea></label>
              @endif
            </div>
          </section>
        @endforeach

        <div class="form-actions public-feedback-actions">
          <button class="btn" type="submit" data-feedback-submit{{ $feedbackUnlocked ? '' : ' disabled' }}>Kirim</button>
          <a class="btn ghost" href="{{ route('public.member-feedback.branch') }}">Kembali</a>
        </div>
      </form>
    </section>

    <script>
    (function () {
      var form = document.querySelector('[data-public-member-feedback-form]');
      if (!form) { return; }
      var groups = {!! \Illuminate\Support\Js::from($groupOptions) !!};
      var groupSelect = form.querySelector('[data-feedback-group]');
      var respondentSelect = form.querySelector('[data-feedback-respondent]');
      var sessionSelect = form.querySelector('[data-feedback-session]');
      var groupMeta = form.querySelector('[data-feedback-group-meta]');
      var submitBtn = form.querySelector('[data-feedback-submit]');
      var hardDisabled = form.getAttribute('data-feedback-hard-disabled') === '1';
      var initialMemberId = respondentSelect ? (respondentSelect.getAttribute('data-initial-member') || '') : '';
      var findGroup = function (groupId) {
        return groups.find(function (groupRow) { return groupRow && groupRow.id === groupId; }) || null;
      };
      var formatGroupLabel = function (groupRow) {
        if (!groupRow) { return 'DG Saudara'; }
        var progress = String(groupRow.progress || 'DG').trim() || 'DG';
        var leader = String(groupRow.leader_name || '').trim();
        return leader ? progress + ' (' + leader + ')' : progress;
      };
      var renderRespondents = function () {
        if (!respondentSelect || !groupSelect) { return; }
        var keepMemberId = respondentSelect.value || initialMemberId;
        var groupRow = findGroup(groupSelect.value || '');
        while (respondentSelect.firstChild) { respondentSelect.removeChild(respondentSelect.firstChild); }
        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = groupRow ? '- Pilih Nama Pengisi -' : '- Pilih Kelompok Dulu -';
        respondentSelect.appendChild(placeholder);
        respondentSelect.disabled = hardDisabled || !groupRow;
        var members = groupRow && Array.isArray(groupRow.members) ? groupRow.members : [];
        members.forEach(function (memberRow) {
          if (!memberRow || !memberRow.id || !memberRow.name) { return; }
          var option = document.createElement('option');
          option.value = String(memberRow.id);
          option.textContent = String(memberRow.name);
          respondentSelect.appendChild(option);
        });
        if (keepMemberId && members.some(function (memberRow) { return memberRow && memberRow.id === keepMemberId; })) {
          respondentSelect.value = keepMemberId;
        } else {
          respondentSelect.value = '';
        }
        initialMemberId = '';
      };
      var updateLabels = function () {
        var groupRow = groupSelect ? findGroup(groupSelect.value || '') : null;
        var label = formatGroupLabel(groupRow);
        form.querySelectorAll('[data-feedback-leader-name]').forEach(function (node) { node.textContent = label; });
        if (groupMeta) {
          var members = groupRow && Array.isArray(groupRow.members) ? groupRow.members : [];
          groupMeta.textContent = groupRow ? label + ' - ' + members.length + ' anggota' : 'Pilih kelompok DG, lalu pilih nama Saudara sebagai pengisi.';
        }
      };
      var updateLockState = function () {
        var groupReady = groupSelect ? (groupSelect.value || '') !== '' : false;
        var respondentReady = respondentSelect ? (!respondentSelect.disabled && (respondentSelect.value || '') !== '') : false;
        var sessionReady = sessionSelect ? (sessionSelect.value === '3' || sessionSelect.value === '12') : false;
        var unlocked = !hardDisabled && groupReady && respondentReady && sessionReady;
        form.querySelectorAll('[data-feedback-requires-member]').forEach(function (input) {
          input.disabled = !unlocked;
        });
        if (submitBtn) { submitBtn.disabled = !unlocked; }
      };
      if (groupSelect) {
        groupSelect.addEventListener('change', function () {
          renderRespondents();
          updateLabels();
          updateLockState();
        });
      }
      if (respondentSelect) { respondentSelect.addEventListener('change', updateLockState); }
      if (sessionSelect) { sessionSelect.addEventListener('change', updateLockState); }
      renderRespondents();
      updateLabels();
      updateLockState();
    }());
    </script>
@endsection
