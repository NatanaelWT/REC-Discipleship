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
        <input type="hidden" name="action" value="lookup_difficult_answer">
        <div class="public-question-password-panel public-answer-password-panel">
          <div class="public-question-password-copy"><strong>Password Pertanyaan</strong><span>Gunakan password yang sama seperti saat mengirim pertanyaan.</span></div>
          <label class="public-question-field">Password <span class="required-mark">*</span><input type="password" name="question_password" minlength="4" required autocomplete="current-password"></label>
        </div>
        <div class="form-actions public-question-actions">
          <button class="btn" type="submit">Buka Jawaban</button>
          <a class="btn ghost" href="{{ route('public.difficult-question.submit') }}">Kirim Pertanyaan Baru</a>
          <a class="btn ghost" href="{{ url('/') }}">Kembali</a>
        </div>
      </form>
    </section>

    @if ($hasLookup)
        <section class="card public-answer-results-card">
          <div class="card-row public-answer-results-head">
            <h2>Hasil Pencarian</h2>
            <span class="badge muted">{{ (string) $matchedQuestions->count() }} pertanyaan</span>
          </div>
          @if ($matchedQuestions->count() === 0)
            <div class="panel-note">Tidak ada pertanyaan dengan password tersebut.</div>
          @else
            <div class="public-answer-list">
              @foreach ($matchedQuestions as $questionRow)
                @php
                    $questionText = trim((string) $questionRow->question);
                    $answerText = trim((string) $questionRow->answer);
                    $status = strtolower(trim((string) $questionRow->status));
                    $statusLabel = app(\App\Services\DifficultQuestions\DifficultQuestionStatusLabel::class)->label($status);
                    $createdDate = normalize_ymd_date($questionRow->created_at ? $questionRow->created_at->format('Y-m-d') : '');
                    $createdLabel = $createdDate !== '' ? format_indo_date($createdDate) : '-';
                    $answeredDate = normalize_ymd_date($questionRow->answered_at ? $questionRow->answered_at->format('Y-m-d') : '');
                    $answeredLabel = $answeredDate !== '' ? format_indo_date($answeredDate) : '';
                    $statusClass = $status === 'answered' && $answerText !== '' ? 'success' : 'warning';
                @endphp
                <article class="public-answer-item">
                  <div class="public-answer-item-head"><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span><span class="public-answer-date">Dikirim: {{ $createdLabel }}</span></div>
                  <div class="public-answer-question">{!! nl2br(e($questionText)) !!}</div>
                  @if ($status === 'answered' && $answerText !== '')
                    <div class="public-answer-response"><strong>Jawaban</strong><div>{!! nl2br(e($answerText)) !!}</div>@if ($answeredLabel !== '')<span>Dijawab: {{ $answeredLabel }}</span>@endif</div>
                  @else
                    <div class="panel-note">Pertanyaan ini belum dijawab oleh admin pusat.</div>
                  @endif
                </article>
              @endforeach
            </div>
          @endif
        </section>
    @endif
@endsection
