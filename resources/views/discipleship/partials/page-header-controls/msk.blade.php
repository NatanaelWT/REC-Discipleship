<div class="msk-hero-controls">
  @if (! $centralReadOnly)
    <button class="btn tiny icon-btn" type="button" data-msk-create-open aria-label="Tambah Peserta MSK" title="Tambah Peserta MSK"><?php echo icon_svg('plus'); ?></button>
  @endif

  <div class="msk-batch-actions">
    @if (count($batchMonthOptions) > 0)
      <form method="get" action="{{ $mskIndexAction }}" class="form-row cash-filter-form">
        @if (request()->filled('branch_id'))
          <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
        @endif
        @if ($editId !== '' && $editParticipant !== null)
          <input type="hidden" name="edit" value="{{ $editId }}">
        @endif
        @if ($autoOpenViewParticipantId !== '')
          <input type="hidden" name="view" value="{{ $autoOpenViewParticipantId }}">
        @endif
        @if ($participantsSearch !== '')
          <input type="hidden" name="q" value="{{ $participantsSearch }}">
        @endif
        <select name="batch_month" class="msk-batch-select" aria-label="Filter batch bulan MSK" required onchange="this.form.submit()">
          <option value="all" @selected($batchMonthFilterIsAll)>Semua Batch ({{ (string) $totalParticipantsAll }})</option>
          @foreach ($batchMonthOptions as $batchMonthOption)
            <option value="{{ $batchMonthOption }}" @selected(! $batchMonthFilterIsAll && $batchMonthOption === $batchMonthFilter)>{{ format_indo_month($batchMonthOption) }} ({{ (string) ($batchMonthMap[$batchMonthOption] ?? 0) }})</option>
          @endforeach
        </select>
      </form>
    @endif

    @if (! $centralReadOnly)
      <form method="post" action="{{ $mskImportAction }}" enctype="multipart/form-data" class="msk-import-inline-form">
        @csrf
        <input type="hidden" name="action" value="import_pemuridan_excel">
        <input type="hidden" name="return_page" value="msk_classes">
        <input type="hidden" name="batch_month" value="{{ $batchMonthFilterParam }}">
        <label class="btn tiny ghost msk-import-trigger msk-transfer-button" aria-label="Import Data Kelas MSK" title="Import Data Kelas MSK">
          <?php echo icon_svg('upload'); ?>
          <span>Import</span>
          <input class="msk-import-input" type="file" name="import_pemuridan_excel" accept=".xlsx" onchange="this.form.submit()">
        </label>
      </form>
    @endif

    <form method="post" action="{{ $mskExportAction }}" class="msk-export-inline-form">
      @csrf
      <input type="hidden" name="action" value="export_pemuridan_excel">
      <input type="hidden" name="batch_month" value="{{ $batchMonthFilterParam }}">
      <button class="btn tiny ghost msk-transfer-button" type="submit"><?php echo icon_svg('download'); ?><span>Export</span></button>
    </form>
  </div>
</div>

<div class="discipleship-page-header__search">
  <form method="get" action="{{ $mskIndexAction }}" class="form-row" data-msk-search-form>
    @if (request()->filled('branch_id'))
      <input type="hidden" name="branch_id" value="{{ request()->query('branch_id') }}">
    @endif
    <input type="hidden" name="batch_month" value="{{ $batchMonthFilterParam }}">
    <input type="search" name="q" value="{{ $participantsSearch }}" class="search msk-table-search" placeholder="Cari peserta MSK..." aria-label="Cari peserta MSK" autocomplete="off" data-msk-search-input>
  </form>
</div>
