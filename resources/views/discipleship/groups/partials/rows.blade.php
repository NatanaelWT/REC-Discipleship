@foreach ($groups as $groupRow)
  @include('discipleship.groups.partials.row', ['groupRow' => $groupRow])
@endforeach
