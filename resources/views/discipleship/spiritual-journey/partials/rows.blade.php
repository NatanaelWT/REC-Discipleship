@foreach ($rows as $row)
  @include('discipleship.spiritual-journey.partials.row', ['row' => $row])
@endforeach
