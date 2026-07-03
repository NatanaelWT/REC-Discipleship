@foreach ($participantsFilteredByBatch as $participant)
  @php
      $templateId = trim((string) ($participant['id'] ?? ''));
      $profile = $templateId !== '' && is_array($participantProfiles[$templateId] ?? null)
          ? $participantProfiles[$templateId]
          : null;
      $templateTitle = trim((string) ($participant['full_name'] ?? 'Detail Peserta MSK'));
      if ($templateTitle === '') {
          $templateTitle = 'Detail Peserta MSK';
      }
      $templateEditHref = '';
      if (! $centralReadOnly && $templateId !== '') {
          $editRouteParams = [
              'edit' => $templateId,
              'batch_month' => $batchMonthFilterParam,
          ];
          if (request()->filled('branch_id')) {
              $editRouteParams['branch_id'] = request()->query('branch_id');
          }
          if (request()->filled('q')) {
              $editRouteParams['q'] = request()->query('q');
          }
          $templateEditHref = route('discipleship.msk-classes', $editRouteParams);
      }
  @endphp
  @if ($templateId !== '' && $profile !== null)
    <template data-msk-view-template="{{ $templateId }}" data-msk-view-template-title="{{ $templateTitle }}" data-msk-view-template-edit="{{ $templateEditHref }}">{!! view('discipleship.msk-participants.profile', ['profile' => $profile])->render() !!}</template>
  @endif
@endforeach
