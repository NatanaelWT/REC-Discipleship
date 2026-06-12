<section class="card table-card-plain">
  <div class="card-row">
    <h2>Arsip Jadwal Bulanan</h2>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Bulan</th><th>Judul</th><th>Catatan Update</th><th>Jumlah Minggu</th><th>Terakhir Disimpan</th><th class="actions-head">Aksi</th></tr></thead>
      <tbody>
        @foreach ($worshipPenatalayanSchedules as $savedSchedule)
          @php
              $scheduleMonth = normalize_month_value((string) ($savedSchedule['month'] ?? date('Y-m')));
              $scheduleTitle = trim((string) ($savedSchedule['title'] ?? default_worship_penatalayan_title($scheduleMonth)));
              if ($scheduleTitle === '') {
                  $scheduleTitle = default_worship_penatalayan_title($scheduleMonth);
              }
              $scheduleNote = trim((string) ($savedSchedule['update_note'] ?? ''));
              $updatedAtLabel = format_datetime_id((string) ($savedSchedule['updated_at'] ?? ''));
              $rowClass = $scheduleMonth === $selectedMonth ? 'row-highlight' : '';
          @endphp
          <tr @class([$rowClass => $rowClass !== ''])>
            <td>{{ format_indo_month($scheduleMonth) }}</td>
            <td><div class="worship-steward-saved-title"><strong>{{ $scheduleTitle }}</strong><span>{{ default_worship_penatalayan_title($scheduleMonth) }}</span></div></td>
            <td>{{ $scheduleNote !== '' ? $scheduleNote : '-' }}</td>
            <td>{{ (string) count(worship_penatalayan_week_dates($scheduleMonth)) }}</td>
            <td>{{ $updatedAtLabel }}</td>
            <td class="actions">
              <a class="btn tiny secondary icon-btn" href="{{ route('worship.penatalayan', ['month' => $scheduleMonth]) }}" aria-label="Buka" title="Buka">{!! icon_svg('eye') !!}</a>
              <a class="btn tiny ghost icon-btn" href="{{ route('worship.penatalayan.image', ['month' => $scheduleMonth]) }}" aria-label="Cetak" title="Cetak">{!! icon_svg('print') !!}</a>
              <form method="post" action="{{ route('worship.penatalayan.destroy', ['month' => $scheduleMonth]) }}" class="inline" onsubmit="return confirm('Hapus jadwal penatalayan bulan ini?');">
                @method('DELETE')
                <input type="hidden" name="action" value="delete_worship_penatalayan">
                <input type="hidden" name="month" value="{{ $scheduleMonth }}">
                <button class="btn tiny danger icon-btn" type="submit" aria-label="Hapus" title="Hapus">{!! icon_svg('trash') !!}</button>
              </form>
            </td>
          </tr>
        @endforeach
        @if (count($worshipPenatalayanSchedules) === 0)
          <tr><td colspan="6">Belum ada jadwal penatalayan yang disimpan. Pilih bulan, isi tabel pelayanan, lalu simpan jadwal pertama.</td></tr>
        @endif
      </tbody>
    </table>
  </div>
</section>
