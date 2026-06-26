@include('discipleship.partials.page-header', [
    'header' => [
        'kicker' => 'Admin Pemuridan',
        'title' => 'Pertanyaan Sulit',
        'description' => 'Pantau pertanyaan dari halaman publik, lalu isi jawaban agar pengirim bisa membukanya dengan password yang mereka buat.',
        'stats' => [
            ['label' => 'Menunggu', 'value' => (string) $pendingQuestionCount],
            ['label' => 'Dijawab', 'value' => (string) $answeredQuestionCount],
            ['label' => 'Total', 'value' => (string) $totalQuestionCount],
        ],
    ],
])
