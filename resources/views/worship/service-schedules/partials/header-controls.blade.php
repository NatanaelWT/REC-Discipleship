<div class="worship-steward-hero-controls">
  <form method="get" action="{{ route('worship.penatalayan') }}" class="worship-steward-month-form">
    <input type="month" name="month" value="{{ $selectedMonth }}" required aria-label="Pilih bulan jadwal penatalayan" onchange="this.form.submit()">
  </form>

  <div class="worship-steward-hero-fields">
    <label class="worship-steward-hero-field">
      Catatan Update
      <input form="worship-steward-form" type="text" name="update_note" value="{{ (string) ($selectedSchedule['update_note'] ?? '') }}" placeholder="Contoh: update 23 Feb">
    </label>
  </div>
</div>
