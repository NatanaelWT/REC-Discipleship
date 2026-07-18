@foreach ($participantsFilteredByBatch as $participant)
  @include('discipleship.msk-participants.partials.row', [
      'participant' => $participant,
      'batchMonthFilterParam' => $batchMonthFilterParam,
  ])
@endforeach
