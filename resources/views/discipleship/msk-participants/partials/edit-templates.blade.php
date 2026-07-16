@if (! $centralReadOnly)
  @foreach ($participantsFilteredByBatch as $participant)
    @php
        $templateId = trim((string) ($participant['id'] ?? ''));
        $templateTitle = trim((string) ($participant['full_name'] ?? 'Edit Peserta MSK'));
        if ($templateTitle === '') {
            $templateTitle = 'Edit Peserta MSK';
        }
        $templateContent = $templateId !== ''
            ? view('discipleship.msk-participants.partials.form', [
                'participant' => $participant,
                'batchMonth' => $batchMonthFilterParam,
                'closeActionAttr' => 'data-msk-edit-close',
                'mskStoreAction' => route('discipleship.msk-classes.store', current_user_branch_id() !== null ? ['branch_id' => current_user_branch_id()] : []),
            ])->render()
            : '';
    @endphp
    @if ($templateId !== '')
      <template data-msk-edit-template="{{ $templateId }}" data-msk-edit-template-title="Edit Peserta MSK: {{ $templateTitle }}">{!! $templateContent !!}</template>
    @endif
  @endforeach
@endif
