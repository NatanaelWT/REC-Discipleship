<section class="card difficult-question-admin-card">
  <div class="card-row">
    <h2>Daftar Pertanyaan</h2>
    <span class="badge muted">{{ (string) $totalQuestionCount }} pertanyaan</span>
  </div>
  @if ($totalQuestionCount === 0)
    <div class="panel-note">Belum ada pertanyaan sulit yang masuk.</div>
  @else
    <div class="difficult-question-admin-list">
      @foreach ($questionItems as $questionItem)
        @include('discipleship.difficult-questions.partials.question-item', ['questionItem' => $questionItem])
      @endforeach
    </div>
  @endif
</section>
