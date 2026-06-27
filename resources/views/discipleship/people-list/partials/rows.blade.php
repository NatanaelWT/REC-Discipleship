@foreach ($people as $row)
  @include('discipleship.people-list.partials.row', ['row' => $row])
@endforeach
