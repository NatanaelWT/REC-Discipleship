@extends('layouts.rec_app', [
    'title' => 'Pertanyaan Sulit',
    'settings' => $settings,
    'currentPage' => 'difficult_questions_admin',
    'showTitle' => false,
    'bodyClass' => 'page-difficult-questions-admin',
])

@section('content')
    @include('discipleship.difficult-questions.panel')
@endsection
