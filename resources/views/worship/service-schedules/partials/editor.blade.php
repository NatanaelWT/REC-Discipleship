<section class="card worship-steward-editor-card">
  <div class="steward-history" style="margin:0 0 12px 0;padding:10px 14px;background:#f8fafc;border-radius:6px;border:1px solid #e6eef8">
    <div style="font-weight:700;margin-bottom:6px;color:#0f172a">Jumlah melayani di bulan ini</div>
    <div data-steward-count-list style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
      @foreach ($displayStewardNames as $hn)
        @php $count = isset($serviceCounts[$hn]) ? (int) $serviceCounts[$hn] : 0; @endphp
        <span class="worship-steward-count-chip" data-steward-name="{{ $hn }}" style="display:inline-block;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#0f172a;font-size:13px">{{ $hn }} ({{ (string) $count }})</span>
      @endforeach
      @if (count($displayStewardNames) === 0)
        <span data-steward-count-empty style="color:#64748b;font-size:13px">Belum ada riwayat penatalayan.</span>
      @endif
    </div>
  </div>
  <form method="post" action="{{ route('worship.penatalayan.store') }}" id="worship-steward-form" class="worship-steward-form" autocomplete="off">
    @csrf
    <input type="hidden" name="action" value="save_worship_penatalayan">
    <input type="hidden" name="month" value="{{ $selectedMonth }}">
    <div class="table-wrap worship-steward-table-wrap">
      <table class="table worship-steward-planner-table">
        <thead><tr><th>Pelayanan</th>
          @foreach ($selectedWeekDates as $weekDate)
            <th>{{ format_short_indo_weekday_date((string) $weekDate) }}</th>
          @endforeach
        </tr></thead>
        <tbody>
          @foreach (($selectedSchedule['rows'] ?? []) as $rowIndex => $scheduleRow)
            @php
                $roleLabel = trim((string) ($scheduleRow['role'] ?? '-'));
                if ($roleLabel === '') {
                    $roleLabel = '-';
                }
                $assignments = is_array($scheduleRow['assignments'] ?? null) ? $scheduleRow['assignments'] : [];
                $roleKey = strtolower($roleLabel);
                $isDualRole = in_array($roleKey, ['singer', 'keyboard'], true);
                $isTrainingSchedule = $roleKey === 'jadwal latihan';
            @endphp
            <tr>
              <th><input type="hidden" name="row_labels[]" value="{{ $roleLabel }}"><span class="worship-steward-row-label">{{ $roleLabel }}</span></th>
              @for ($weekIndex = 0; $weekIndex < count($selectedWeekDates); $weekIndex++)
                @php $cellValue = (string) ($assignments[$weekIndex] ?? ''); @endphp
                @if ($isTrainingSchedule)
                  @php
                      $trainingValue = worship_penatalayan_training_field_value($cellValue, $selectedMonth);
                      $trainingLabel = $trainingValue !== '' ? format_short_indo_weekday_date($trainingValue) : 'Pilih tanggal latihan';
                  @endphp
                  <td><div class="worship-steward-cell-shell worship-steward-training-field"><input class="worship-steward-training-input" type="date" name="assignments[{{ (string) $rowIndex }}][{{ (string) $weekIndex }}]" value="{{ $trainingValue }}"><span class="worship-steward-training-preview" data-empty="Pilih tanggal latihan">{{ $trainingLabel }}</span></div></td>
                @elseif ($isDualRole)
                  @php
                      $cellLines = preg_split("/\r\n?|\n/", $cellValue) ?: [];
                      $firstValue = trim((string) ($cellLines[0] ?? ''));
                      $secondValue = trim((string) ($cellLines[1] ?? ''));
                  @endphp
                  <td><div class="worship-steward-cell-shell worship-steward-duo"><input autocomplete="off" class="worship-steward-duo-input" data-steward-count-field="1" type="text" name="assignments[{{ (string) $rowIndex }}][{{ (string) $weekIndex }}][]" value="{{ $firstValue }}" placeholder="Nama 1"><input autocomplete="off" class="worship-steward-duo-input" data-steward-count-field="1" type="text" name="assignments[{{ (string) $rowIndex }}][{{ (string) $weekIndex }}][]" value="{{ $secondValue }}" placeholder="Nama 2"></div></td>
                @else
                  <td><div class="worship-steward-cell-shell"><textarea autocomplete="off" class="worship-steward-cell" data-steward-count-field="1" name="assignments[{{ (string) $rowIndex }}][{{ (string) $weekIndex }}]" rows="1" placeholder="Nama">{{ $cellValue }}</textarea></div></td>
                @endif
              @endfor
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </form>
  <div class="worship-steward-action-bar">
    <button class="btn" form="worship-steward-form" type="submit">Simpan Jadwal</button>
    @if ($selectedExistingSchedule !== null)
      <form method="post" action="{{ route('worship.penatalayan.destroy', ['month' => $selectedMonth]) }}" class="worship-steward-danger-form" onsubmit="return confirm('Hapus jadwal penatalayan untuk bulan ini?');">
        @csrf
        @method('DELETE')
        <input type="hidden" name="action" value="delete_worship_penatalayan">
        <input type="hidden" name="month" value="{{ $selectedMonth }}">
        <button class="btn ghost" type="submit">Hapus Jadwal Bulan Ini</button>
      </form>
    @endif
  </div>
</section>
