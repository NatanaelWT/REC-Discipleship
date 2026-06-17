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
      <span class="badge muted">{{ (string) $totalQuestionCount }} total</span>
    </div>
  </div>
</section>
