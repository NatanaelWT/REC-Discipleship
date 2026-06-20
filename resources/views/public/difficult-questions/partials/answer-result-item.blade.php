<article class="public-answer-item">
  <div class="public-answer-item-head"><span class="badge {{ $questionItem['statusClass'] }}">{{ $questionItem['statusLabel'] }}</span><span class="public-answer-date">Dikirim: {{ $questionItem['createdLabel'] }}</span></div>
  <div class="public-answer-question">{!! nl2br(e($questionItem['questionText'])) !!}</div>
  @if ($questionItem['status'] === 'answered' && $questionItem['answerText'] !== '')
    <div class="public-answer-response"><strong>Jawaban</strong><div>{!! nl2br(e($questionItem['answerText'])) !!}</div>@if ($questionItem['answeredLabel'] !== '')<span>Dijawab: {{ $questionItem['answeredLabel'] }}</span>@endif</div>
  @else
    <div class="panel-note">Pertanyaan ini belum dijawab oleh tim pengelola.</div>
  @endif
</article>
