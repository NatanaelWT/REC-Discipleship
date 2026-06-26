@extends('layouts.rec_plain', [
    'title' => 'Jawaban Pertanyaan Sulit',
    'settings' => $settings,
    'bodyClass' => 'page-dg-public page-public-difficult-answer',
])

@section('content')
    @if ($errorCode === 'password_required')
        <div class="alert danger">Masukkan password yang dibuat saat mengirim pertanyaan.</div>
    @endif

    <section class="card public-question-card">
      <div class="public-question-head">
        <span class="public-question-kicker">Jawaban</span>
        <h2>Jawaban Pertanyaan Sulit</h2>
        <p>Masukkan password yang dibuat saat mengirim pertanyaan untuk melihat status dan jawaban.</p>
      </div>
      <form method="post" action="{{ route('public.difficult-question.lookup') }}" class="form-grid public-question-form public-answer-lookup-form">
        @csrf
        <input type="hidden" name="action" value="lookup_difficult_answer">
        <div class="public-question-password-panel public-answer-password-panel">
          <div class="public-question-password-copy"><strong>Password Pertanyaan</strong><span>Gunakan password yang sama seperti saat mengirim pertanyaan.</span></div>
          <label class="public-question-field">Password <span class="required-mark">*</span><input type="password" name="question_password" minlength="4" required autocomplete="current-password"></label>
        </div>
        <div class="form-actions public-question-actions">
          <a class="btn ghost public-question-back-action" href="{{ url('/') }}">Kembali</a>
          <button class="btn" type="submit">Buka Jawaban</button>
        </div>
      </form>
    </section>

    @if ($hasLookup)
        <section class="card public-answer-results-card">
          <div class="card-row public-answer-results-head">
            <h2>Hasil Pencarian</h2>
            <span class="badge muted">{{ (string) $matchedQuestionCount }} pertanyaan</span>
          </div>
          @if ($matchedQuestionCount === 0)
            <div class="panel-note">Tidak ada pertanyaan dengan password tersebut.</div>
          @else
            <div class="public-answer-list">
              @foreach ($matchedQuestionItems as $questionItem)
                @include('public.difficult-questions.partials.answer-result-item', ['questionItem' => $questionItem])
              @endforeach
            </div>
          @endif
        </section>
    @endif
@endsection
