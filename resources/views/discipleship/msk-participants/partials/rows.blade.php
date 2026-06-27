@foreach ($participantsFilteredByBatch as $participant)
  @include('discipleship.msk-participants.partials.row', [
      'participant' => $participant,
      'centralReadOnly' => $centralReadOnly,
      'batchMonthFilterParam' => $batchMonthFilterParam,
  ])
@endforeach
