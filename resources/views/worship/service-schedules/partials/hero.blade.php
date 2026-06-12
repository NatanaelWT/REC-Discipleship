<section class="card msk-hero-card worship-hero-card worship-steward-hero-card">
  <div class="msk-hero-head">
    <div class="msk-hero-copy">
      <span class="msk-hero-kicker">Ibadah Umum</span>
      <h1>Penatalayan Ibadah Umum</h1>
      <p>Atur pembagian pelayanan setiap Minggu per bulan, isi nama pelayan langsung di tabel, lalu simpan jadwal penatalayan agar mudah dipakai saat koordinasi ibadah.</p>
    </div>
    <div class="msk-hero-stats" aria-label="Ringkasan penatalayan ibadah umum">
      <div class="msk-hero-stat"><span class="msk-hero-stat-label">Bulan Dipilih</span><strong class="msk-hero-stat-value">{{ format_indo_month($selectedMonth) }}</strong></div>
      <div class="msk-hero-stat"><span class="msk-hero-stat-label">Minggu Ibadah</span><strong class="msk-hero-stat-value">{{ (string) count($selectedWeekDates) }}</strong></div>
      <div class="msk-hero-stat"><span class="msk-hero-stat-label">Update Terakhir</span><strong class="msk-hero-stat-value">{{ $lastUpdatedStatLabel }}</strong></div>
      <div class="msk-hero-stat"><span class="msk-hero-stat-label">Bulan Tersimpan</span><strong class="msk-hero-stat-value">{{ (string) $totalStewardMonths }}</strong></div>
    </div>
  </div>
  <div class="actions table-tools msk-hero-tools worship-steward-hero-tools">
    <div class="msk-hero-controls worship-steward-hero-controls">
      <form method="get" action="{{ route('worship.penatalayan') }}" class="worship-steward-month-form">
        <input type="month" name="month" value="{{ $selectedMonth }}" required aria-label="Pilih bulan jadwal penatalayan" onchange="this.form.submit()">
      </form>
      <div class="worship-steward-hero-fields">
        <label class="worship-steward-hero-field">Judul Jadwal<input form="worship-steward-form" type="text" name="title" value="{{ (string) ($selectedSchedule['title'] ?? '') }}" placeholder="{{ default_worship_penatalayan_title($selectedMonth) }}"></label>
        <label class="worship-steward-hero-field">Catatan Update<input form="worship-steward-form" type="text" name="update_note" value="{{ (string) ($selectedSchedule['update_note'] ?? '') }}" placeholder="Contoh: update 23 Feb"></label>
      </div>
    </div>
  </div>
</section>
