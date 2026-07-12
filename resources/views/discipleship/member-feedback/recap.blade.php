@extends('layouts.rec_app', [
    'title' => 'Rekap Jurnal Umpan Balik Anggota',
    'settings' => $settings,
    'currentPage' => 'member_feedback_recap',
    'showTitle' => false,
    'bodyClass' => 'page-discipleship-table-scroll page-member-feedback-recap',
])

@section('content')
  @include('discipleship.member-feedback.panel')
@endsection