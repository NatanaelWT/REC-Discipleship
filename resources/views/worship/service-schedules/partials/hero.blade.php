@include('discipleship.partials.page-header', [
    'header' => [
        'kicker' => 'Ibadah Umum',
        'title' => 'Penatalayan Ibadah Umum',
        'description' => 'Atur pembagian pelayanan setiap Minggu per bulan, isi nama pelayan langsung di tabel, lalu simpan jadwal penatalayan agar mudah dipakai saat koordinasi ibadah.',
        'stats_aria_label' => 'Ringkasan penatalayan ibadah umum',
        'attributes' => ['data-worship-header' => true],
        'stats' => [
            ['label' => 'Bulan Dipilih', 'value' => format_indo_month($selectedMonth)],
            ['label' => 'Minggu Ibadah', 'value' => (string) count($selectedWeekDates)],
            ['label' => 'Update Terakhir', 'value' => $lastUpdatedStatLabel],
            ['label' => 'Bulan Tersimpan', 'value' => (string) $totalStewardMonths],
        ],
        'tools' => [
            'element' => 'div',
            'attributes' => ['class' => 'table-tools worship-steward-hero-tools'],
            'partial' => 'worship.service-schedules.partials.header-controls',
            'data' => compact('selectedMonth', 'selectedSchedule'),
        ],
    ],
])
