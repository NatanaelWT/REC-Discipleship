@extends('layouts.rec_app', [
    'title' => 'Pertanyaan Sulit',
    'settings' => $settings,
    'currentPage' => 'difficult_questions_admin',
    'showTitle' => false,
    'bodyClass' => 'page-difficult-questions-admin',
])

@section('content')
    @if ($answered)
        <div class="alert success">Jawaban pertanyaan berhasil disimpan.</div>
    @endif

    @php
        $errorMessages = [
            'missing_question' => 'Pertanyaan yang akan dijawab tidak ditemukan.',
            'missing_answer' => 'Isi jawaban terlebih dahulu.',
            'question_not_found' => 'Data pertanyaan tidak ditemukan.',
            'save_failed' => 'Jawaban gagal disimpan. Coba ulangi lagi.',
        ];
    @endphp

    @if ($errorCode !== '' && isset($errorMessages[$errorCode]))
        <div class="alert danger">{{ $errorMessages[$errorCode] }}</div>
    @endif

    <section class="card difficult-question-admin-hero">
      <div class="card-row">
        <div>
          <span class="badge warning">Admin</span>
          <h2>Pertanyaan Sulit</h2>
          <p class="panel-note">Pantau pertanyaan dari halaman publik, lalu isi jawaban agar pengirim bisa membukanya dengan password yang mereka buat.</p>
        </div>
        <div class="actions table-tools">
          <span class="badge warning">{{ (string) $pendingQuestionCount }} menunggu</span>
          <span class="badge success">{{ (string) $answeredQuestionCount }} dijawab</span>
          <span class="badge muted">{{ (string) $questions->count() }} total</span>
        </div>
      </div>
    </section>

    <section class="card difficult-question-admin-card">
      <div class="card-row">
        <h2>Daftar Pertanyaan</h2>
        <span class="badge muted">{{ (string) $questions->count() }} pertanyaan</span>
      </div>
      @if ($questions->count() === 0)
        <div class="panel-note">Belum ada pertanyaan sulit yang masuk.</div>
      @else
        <div class="difficult-question-admin-list">
          @foreach ($questions as $questionRow)
            @php
                $questionId = trim((string) $questionRow->public_id);
                $questionText = trim((string) $questionRow->question);
                $answerText = trim((string) $questionRow->answer);
                $askerName = trim((string) $questionRow->asker_name);
                $createdAt = format_datetime_id($questionRow->created_at ? $questionRow->created_at->toIso8601String() : '');
                $answeredAt = format_datetime_id($questionRow->answered_at ? $questionRow->answered_at->toIso8601String() : '');
                $answeredBy = trim((string) $questionRow->answered_by_username);
                $questionStatus = strtolower(trim((string) $questionRow->status));
                $statusLabel = app(\App\Services\DifficultQuestions\DifficultQuestionStatusLabel::class)->label($questionStatus);
                $statusClass = $questionStatus === 'answered' ? 'badge success' : 'badge warning';
                if ($askerName === '') {
                    $askerName = 'Anonim';
                }
                if ($questionText === '') {
                    $questionText = '(Pertanyaan kosong)';
                }
            @endphp

            <article class="difficult-question-admin-item">
              <div class="difficult-question-admin-head">
                <div>
                  <strong>{{ $askerName }}</strong>
                  <span>Dikirim: {{ $createdAt }}</span>
                </div>
                <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
              </div>
              <div class="difficult-question-admin-question">{!! nl2br(e($questionText)) !!}</div>
              @if ($answerText !== '')
                <div class="difficult-question-admin-answer">
                  <span>Jawaban terakhir{{ $answeredAt !== '-' ? ': ' . $answeredAt : '' }}{{ $answeredBy !== '' ? ' oleh ' . $answeredBy : '' }}</span>
                  <div>{!! nl2br(e($answerText)) !!}</div>
                </div>
              @endif
              @if ($questionId !== '')
                <form method="post" action="{{ route('discipleship.difficult-questions.answer', $questionRow) }}" class="form-grid difficult-question-answer-form">
                  <input type="hidden" name="action" value="save_difficult_question_answer">
                  <input type="hidden" name="id" value="{{ $questionId }}">
                  <label>Jawaban<textarea name="answer_text" rows="5" maxlength="8000" required placeholder="Tulis jawaban untuk pertanyaan ini...">{{ $answerText }}</textarea></label>
                  <div class="form-actions member-form-actions is-right">
                    <button class="btn" type="submit">{{ $answerText === '' ? 'Simpan Jawaban' : 'Perbarui Jawaban' }}</button>
                  </div>
                </form>
              @else
                <div class="panel-note">Pertanyaan ini tidak memiliki ID, jadi belum bisa dijawab.</div>
              @endif
            </article>
          @endforeach
        </div>
      @endif
    </section>
@endsection
