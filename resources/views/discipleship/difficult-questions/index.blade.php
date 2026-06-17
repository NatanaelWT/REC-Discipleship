@extends('layouts.rec_app', [
    'title' => 'Pertanyaan Sulit',
    'settings' => $settings,
    'currentPage' => 'difficult_questions_admin',
    'showTitle' => false,
    'bodyClass' => 'page-difficult-questions-admin',
])

@section('content')
    @include('discipleship.difficult-questions.partials.alerts')
    @include('discipleship.difficult-questions.partials.hero')
    @include('discipleship.difficult-questions.partials.question-list')
@endsection
