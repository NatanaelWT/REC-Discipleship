@foreach ($mskClasses as $participant)
  @php
      $templateId = trim((string) ($participant['id'] ?? ''));
      $templateTitle = trim((string) ($participant['full_name'] ?? 'Profil Peserta'));
      if ($templateTitle === '') {
          $templateTitle = 'Profil Peserta';
      }
      $profile = $templateId !== '' && is_array($participantProfiles[$templateId] ?? null)
          ? $participantProfiles[$templateId]
          : null;
  @endphp

  @if ($templateId !== '' && $profile !== null)
    <template data-spiritual-journey-view-template="{{ $templateId }}" data-spiritual-journey-view-template-title="{{ $templateTitle }}">{!! view('discipleship.msk-participants.profile', ['profile' => $profile])->render() !!}</template>
  @endif
@endforeach
