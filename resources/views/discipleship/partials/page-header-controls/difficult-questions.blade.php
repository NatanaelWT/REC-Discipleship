<div class="discipleship-page-header__filter">
  <input type="month" name="month" value="{{ $questionMonthFilter }}" class="search difficult-question-month-filter" aria-label="Filter bulan pertanyaan sulit" onchange="this.form.submit()">
</div>

<div class="discipleship-page-header__search">
  <input type="search" name="q" value="{{ $questionSearch }}" class="search difficult-question-table-search" placeholder="Cari nama, WA, pertanyaan, jawaban..." aria-label="Cari pertanyaan sulit" autocomplete="off" data-auto-submit-search-input @if ($questionSearch !== '') autofocus @endif>
</div>
