<article class="difficult-question-admin-item">
  <div class="difficult-question-admin-head">
    <div>
      <strong>{{ $questionItem['askerName'] }}</strong>
      <span>Dikirim: {{ $questionItem['createdAt'] }}</span>
    </div>
    <span class="{{ $questionItem['statusClass'] }}">{{ $questionItem['statusLabel'] }}</span>
  </div>
  <div class="difficult-question-admin-question">{!! nl2br(e($questionItem['questionText'])) !!}</div>
  @if ($questionItem['answerText'] !== '')
    <div class="difficult-question-admin-answer">
      <span>Jawaban terakhir{{ $questionItem['answeredAt'] !== '-' ? ': ' . $questionItem['answeredAt'] : '' }}{{ $questionItem['answeredBy'] !== '' ? ' oleh ' . $questionItem['answeredBy'] : '' }}</span>
      <div>{!! nl2br(e($questionItem['answerText'])) !!}</div>
    </div>
  @endif
  @if ($questionItem['publicId'] !== '')
    <form method="post" action="{{ route('discipleship.difficult-questions.answer', $questionItem['model']) }}" class="form-grid difficult-question-answer-form">
      <input type="hidden" name="action" value="save_difficult_question_answer">
      <input type="hidden" name="id" value="{{ $questionItem['publicId'] }}">
      <label>Jawaban<textarea name="answer_text" rows="5" maxlength="8000" required placeholder="Tulis jawaban untuk pertanyaan ini...">{{ $questionItem['answerText'] }}</textarea></label>
      <div class="form-actions member-form-actions is-right">
        <button class="btn" type="submit">{{ $questionItem['answerButtonLabel'] }}</button>
      </div>
    </form>
  @else
    <div class="panel-note">Pertanyaan ini tidak memiliki ID, jadi belum bisa dijawab.</div>
  @endif
</article>
